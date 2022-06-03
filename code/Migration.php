<?php

namespace PattricNelson\SilverStripeMigrations;









use Exception;





use SilverStripe\ORM\DB;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\ORM\Queries\SQLInsert;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Control\Session;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\Versioned\Versioned;



/**
 * All migrations that must be executed must be descended from this class and define both an ->up() and a ->down()
 * method. Migrations will be executed in alphanumeric order
 *
 * @author    Patrick Nelson, pat@catchyour.com
 * @since    2015-02-17
 */

abstract class Migration implements MigrationInterface {

    protected $obsolete = false;

    abstract public function up();

    abstract public function down();

    /**
     * Indicates that the migration is no longer relevant or able to be run from scratch due to backward
     * incompatibilities, which may occur as the codebase changes over time. Useful when wanting to retain old migration
     * files for reference purposes and consistency.
     *
     * @return bool
     */
    public function isObsolete() {
        return $this->obsolete;
    }


    #######################################
    ## DATABASE MIGRATION HELPER METHODS ##
    #######################################

    /**
     * Returns true if the table exists in the database
     *
     * @param   string  $table
     * @return  bool
     */
    public static function tableExists($table) {
        $tables = DB::tableList();
        return array_key_exists(strtolower($table), $tables);
    }

    /**
     * Returns true if a column exists in a database table
     *
     * @param   string  $table
     * @param   string  $column
     * @return  bool
     */
    public static function tableColumnExists($table, $column) {
        if (!self::tableExists($table)) return false;
        $columns = self::getTableColumns($table);
        return array_key_exists($column, $columns);
    }

    /**
     * Returns true if an array of columns exist on a database table
     *
     * @param   string     $table
     * @param   array      $columns
     * @return  bool
     */
    public static function tableColumnsExist($table, array $columns) {
        if (!self::tableExists($table)) return false;
        return count(array_intersect($columns, array_keys(self::getTableColumns($table)))) === count($columns);
    }

    /**
     * Returns an array of columns for a database table
     *
     * @param   string  $table
     * @return  array   (empty if table doesn't exist) e.g. array('ID' => 'int(11) not null auto_increment')
     */
    public static function getTableColumns($table) {
        if (!self::tableExists($table)) return array();
        return DB::fieldList($table);
    }

    /**
     * Allows to you fetch the specific table (and thus DataObject) that a particular field exists on, since they're
     * merged at runtime. This will actually iterate through the class ancestry in order to determine the table in which
     * a field actually exists.
     *
     * @param	string	$className
     * @param	string	$field
     * @return	string
     */
    public static function getTableForField($className, $field) {
        // Let's get our hands dirty on this ancestry filth and reference the database because the private static ::$db isn't reliable (seriously).
        $ancestors = ClassInfo::ancestry($className, true);
        foreach($ancestors as $ancestor) {
            if ($tableName = DataObject::getSchema()->tableName($ancestor)) {
                if (DB::get_schema()->hasField($tableName, $field)) return $ancestor;
            }
        }

        // Still not found.
        return '';
    }

    /**
     * Drops columns from a database table.
     * Returns array of columns that were dropped
     *
     * @param   string     $table
     * @param   array      $columns
     * @return  array
     */
    public static function dropColumnsFromTable($table, array $columns) {
        $droppedColumns = array();
        $columnsInTable = array_intersect($columns, array_keys(self::getTableColumns($table)));
        if (!$columnsInTable) return $droppedColumns;
        $table = Convert::raw2sql($table);
        foreach ($columnsInTable as $column) {
            DB::query("ALTER TABLE $table DROP COLUMN " . Convert::raw2sql($column) . ";");
            $droppedColumns[] = $column;
        }
        return $droppedColumns;
    }

    /**
     * Add columns to a database table if they don't exist.
     * Returns array of columns that were added
     *
     * @param   string      $table
     * @param   array       $columns    e.g. array('MyColumn' => 'VARCHAR(255) CHARACTER SET utf8')
     * @return  array
     */
    public static function addColumnsToTable($table, array $columns) {
        $addedColumns = array();
        $existingColumns = self::getTableColumns($table);
        if (!$existingColumns) return $addedColumns;
        $table = Convert::raw2sql($table);
        foreach ($columns as $column => $properties) {
            if (!array_key_exists($column, $existingColumns)) {
                DB::query(
                    "ALTER TABLE $table" . " ADD " . Convert::raw2sql($column)
                    . " " . Convert::raw2sql($properties) . ";"
                );
                $addedColumns[] = $column;
            }
        }
        return $addedColumns;
    }

    /**
     * Gets the value for a single column in a row from the database by the ID column.
     * Useful when a field has been removed from the class' `$db` property,
     * and therefore is no longer accessible through the ORM.
     * Returns `null` if the table, column or row does not exist.
     *
     * @param   string  $table
     * @param   string  $field
     * @param   int     $id
     * @return  string
     */
    public static function getRowValueFromTable($table, $field, $id) {
        $value = null;
        if (self::tableColumnExists($table, $field)) {
            $id = (int)$id;
            $query = new SQLSelect();
            $query->setFrom($table)->setSelect(array($field))->setWhere("ID = $id");
            $results = $query->execute();
            if ($results) {
                foreach ($results as $result) {
                    $value = $result[$field];
                    break;
                }
            }
        }
        return $value;
    }

    /**
     * Gets the values for multiple rows on a database table by the ID column.
     * Useful when fields have been removed from the class' `$db` property,
     * and therefore are no longer accessible through the ORM.
     * Returns an empty array if the table, any of the columns or the row do not exist.
     *
     * @param    string         $table
     * @param    array          $fields
     * @param    string|int     $id
     * @return   array          array('FieldName' => value)
     */
    public static function getRowValuesFromTable($table, array $fields, $id) {
        $values = array();
        if (self::tableColumnsExist($table, $fields)) {
            $id = (int)$id;
            $query = new SQLSelect();
            $query->setFrom($table)->setSelect($fields)->setWhere("ID = $id");
            $results = $query->execute();
            if ($results) {
                foreach ($results as $result) {
                    foreach ($fields as $field) {
                        $values[$field] = $result[$field];
                    }
                    break;
                }
            }
        }
        return $values;
    }

    /**
     * Sets the values for multiple rows on a database table by the ID column.
     * Useful when fields have been removed from the class' `$db` property,
     * and therefore are no longer accessible through the ORM.
     * Returns false if the table or any of the rows do not exist.
     * Returns true if the SQL query was executed.
     *
     * @param   string      $table
     * @param   array       $values     Ex: array('FieldName' => value)
     * @param   int|null    $id         Note: Null only works here if $insert = true.
     * @param   bool        $insert     Allows insertion of a new record if the ID provided is null or doesn't exist.
     *                                  NOTE: If an "ID" field is passed, that ID value will be retained.
     * @return  bool                 Will return true if anything was changed, false otherwise.
     */
    public static function setRowValuesOnTable($table, array $values, $id = null, $insert = false) {
        // TODO: This should maybe throw an exception instead.
        if (!self::tableColumnsExist($table, array_keys($values))) return false;

        // Assume it exists unless we're actually going to allow inserting. Then we'll really check for sure.
        $exists = true;
        if ($insert) {
            // Ensure the ID we're checking now is the same as the one we're inserting to help prevent issues with duplicate keys.
            $checkID = $id;
            if (isset($values['ID'])) $checkID = $values['ID'];
            $select = new SQLSelect('COUNT(*)', $table, array('ID' => $checkID));
            $result = $select->execute();
            $exists = (bool) (int) $result->value();
        }

        // Pull out an ID (if applicable).
        if ($id === null && array_key_exists('ID', $values)) $id = $values['ID'];

        if ($exists) {
            // Generate an execute an UPDATE query.
            $update = new SQLUpdate($table, $values, array('ID' => $id));
            $update->execute();
            return true;

        } elseif($insert) {
            // Generate an INSERT query instead.
            $insert = new SQLInsert($table, $values);
            $insert->execute();
            return true;
        }

        // Nothing was done.
        return false;
    }

    /**
     * Simplifies publishing of an actual page instance (since migrations are run from command line).
     *
     * @param    SiteTree   $page
     * @param    bool       $force  If set to false, will not publish if the page has a draft version to prevent
     *                              accidentally publishing a draft page.
     *
     * TODO: Possibly change default for $force to false, but will need to start versioning this module to help prevent issues with backward compatibility.
     *
     * @throws    MigrationException
     */
    public static function publish(SiteTree $page, $force = true) {
        try {
            static::whileAdmin(function () use ($page, $force) {
                if (!$page->isModifiedOnDraft() || $force) {
                    $page->publishRecursive();
                } else {
                    $page->write();
                }
            });

        } catch (Exception $e) {
            throw new MigrationException("Cannot publish page: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Simplifies UN-publishing of an actual page instance (since migrations are run from command line).
     *
     * @param   SiteTree            $page
     * @throws  MigrationException
     */
    public static function unpublish(SiteTree $page) {
        try {
            static::whileAdmin(function () use ($page) {
                $page->doUnpublish();
            });
        } catch (Exception $e) {
            throw new MigrationException("Cannot unpublish page: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * The intent with this function is to allow it to maintain it's own state while allowing you to execute your own
     * arbitrary code within that state (i.e. while logged in as an administrator).
     *
     * Ensures we have permissions to manipulate pages (gets around access issues with global state). Unfortunately, the
     * creation of a default admin account below is necessary because SilverStripe will reference global state via
     * Member::currentUser() and the only surefire way around this is to login as a default admin with full access.
     *
     * @param    callable $closure      The closure (or class/method array) that you'd like to execute while logged in
     *                                  as an admin.
     *
     * @throws    MigrationException|Exception
     */
    protected static function whileAdmin(callable $closure) {
        // Keeps track of the fact that a temporary admin was just created so we can delete it later.
        $tempAdmin = false;
        $admin = null;

        if (!Member::currentUserID()) {
            // See if a default admin is setup yet.
            if (!Security::has_default_admin()) {
                // Generate a randomized user/pass and use that as the default administrator just for this session.
                $tempAdmin = true;
                $user = substr(str_shuffle(sha1("u" . microtime())), 0, 20);
                $pass = substr(str_shuffle(sha1("p" . microtime())), 0, 20);
                Security::setDefaultAdmin($user, $pass);
            }

            $admin = Member::default_admin();
            if (!$admin) throw new MigrationException("Cannot login: No default administrator found.");

            Session::start();
            Session::set("loggedInAs", $admin->ID);
        }

        // Call passed closure.
        try {
            call_user_func($closure);
        } catch (Exception $e) {
            // Deferred. Throwing exception below (need to support PHP 5.4, so no "finally").
        }

        // Clean up.
        Session::set("loggedInAs", null);
        if ($tempAdmin && $admin) $admin->delete();

        // Throw the exception if one occurred (in lieu of a "finally" block in older PHP versions).
        if (isset($e)) throw $e;
    }

    /**
     * Ensures we have permissions to manipulate pages (gets around access issues with global state). Unfortunately, the
     * creation of a default admin account below is necessary because SilverStripe will reference global state via
     * Member::currentUser() and the only surefire way around this is to login as a default admin with full access.
     *
     * CAUTION: Since migrations can only be run from the command line, it's assumed that if you're accessing this, then
     * you're already an admin or you've got an incorrectly configured site!
     *
     * TODO: This should be removed soon.
     *
     * @throws      MigrationException
     * @deprecated  Use ::whileAdmin() instead.
     */
    protected static function loginAsAdmin() {
        Deprecation::notice('0', 'Use ::whileAdmin() instead. This method will be removed soon.');

        if (!Member::currentUserID()) {
            // See if a default admin is setup yet.
            if (!Security::has_default_admin()) {
                // Generate a randomized user/pass and use that as the default administrator just for this session.
                $user = substr(str_shuffle(sha1("u" . microtime())), 0, 20);
                $pass = substr(str_shuffle(sha1("p" . microtime())), 0, 20);
                Security::setDefaultAdmin($user, $pass);
            }

            $admin = Member::default_admin();
            if (!$admin) throw new MigrationException("Cannot login: No default administrator found.");

            Session::start();
            Session::set("loggedInAs", $admin->ID);
        }
    }

    /**
     * Shorthand to make it easier to update the page type, since SilverStripe has a very specific method for
     * accomplishing this.
     *
     * @param    SiteTree $page
     * @param    string $pageType
     * @throws    MigrationException
     */
    public static function setPageType(SiteTree $page, $pageType) {
        if (!is_a($pageType, SiteTree::class, true)) throw new MigrationException("The specifed page type '$pageType' must be an instance (or child) of 'SiteTree'.");
        $page = $page->newClassInstance($pageType);
        static::publish($page);
    }

    /**
     * Allows you to easily transition data from one field name to the next. Works with generic data objects as well as
     * instances of the SiteTree.
     *
     * CAUTION: This method is quite abstract, so it could take a very long time to run if you have many objects to
     * transition, especially SiteTree instances.
     *
     * TODO: An issue with SiteTree objects is unpublished instances will not update the currently published version (if one exists).
     *
     * @param   DataObject      $dataObject
     * @param   string          $oldFieldName
     * @param   string          $newFieldName
     * @param   callable|null   $transformation
     * @throws  MigrationException|ValidationException
     */
    public static function transitionField(DataObject $dataObject, $oldFieldName, $newFieldName, callable $transformation = null) {
        // Get and transform data (if applicable).
        $value = static::getRowValueFromTable(get_class($dataObject), $oldFieldName, $dataObject->ID);
        if ($transformation) $value = call_user_func($transformation, $value);

        // Set transformed value and save (varies depending on if this is a page or not).
        $dataObject->setField($newFieldName, $value);
        if ($dataObject instanceof SiteTree) {
            // Save + publish update if already published, being careful not to publish if currently unpublished.
            static::publish($dataObject, false);
        } else {
            $dataObject->write();
        }
    }

    /**
     * Copies all values from one table to another. Will override any existing values with matching ID's.
     *
     * @param   string      $fromTable      Name of SOURCE table to copy values from.
     * @param   string      $toTable        Name of DESTINATION table to copy values to.
     * @param   array|null  $fieldMapping   Array of fields to copy (and ONLY these fields). Can also specify key => value
     *                                      pairs to map between old/new names (instead of just values). Note: Leave
     *                                      empty (or pass null) to automatically assume ALL fields from source table (including ID).
     * @param   bool        $purgeDest      Ensures all data in the DESTINATION table matches the source.
     * @param   mixed|null  $where          An optional filter passed directly to ->setWhere() method on SQLSelect.
     * @throws  MigrationException
     */
    public static function copyTable($fromTable, $toTable, array $fieldMapping = null, $purgeDest = false, $where = null) {
        if (!static::tableExists($fromTable)) throw new MigrationException("Table '$fromTable' does not exist.");
        if (!static::tableExists($toTable)) throw new MigrationException("Table '$fromTable' does not exist.");

        // Initialize defaults.
        if ($fieldMapping === null) $fieldMapping = array(); // Normalize to empty.
        if ($fieldMapping === array()) {
            // If empty: Use all fields from the source.
            $fieldMapping = array_keys(static::getTableColumns($fromTable));
        }

        // Since an ID is required to prevent duplication of data, add it now if it's not already setup.
        // TODO: Should this be optional?
        if (!in_array('ID', $fieldMapping)) $fieldMapping[] = 'ID';

        // Separate out the source/destination fields from the field mapping to help with selection and validation (correspondingly).
        $sourceFields = array_map(function($key, $value) {
            if (!is_numeric($key)) return $key;
            return $value;
        }, array_keys($fieldMapping), array_values($fieldMapping));
        $destFields = array_values($fieldMapping);

        // Validate columns in the destination first and ensure they exist first before moving forward, since you
        // don't want to perform a DELETE on an entire table unless you're sure the entire operation will complete.
        $destActualFields = array_keys(self::getTableColumns($toTable));
        $destFieldDiff = array_diff($destFields, $destActualFields);
        if (count($destFieldDiff) !== 0) throw new MigrationException("The field(s) '" . join(', ', $destFieldDiff) . "' do not exist in the destination table '$toTable'.");

        // Purge now, if specified.
        if ($purgeDest) {
            $delete = new SQLDelete($toTable);
            $delete->execute();
        }

        // Begin fetching rows and copying them over now.
        $select = new SQLSelect($sourceFields, $fromTable);
        if ($where !== null) $select->setWhere($where);
        $result = $select->execute();
        while($sourceRow = $result->next()) {
            // Convert row fields based on our mapping.
            $destRow = array();
            foreach($sourceRow as $field => $value) {
                if (array_key_exists($field, $fieldMapping)) $field = $fieldMapping[$field];
                $destRow[$field] = $value;
            }

            // Update table.
            static::setRowValuesOnTable($toTable, $destRow, null, true);
        }
    }

    /**
     * Same exact purpose as ::copyTable(), however, also applies changes to other tables associated with versioned
     * objects. Also, this could be potentially much slower due to the extra tables being copied.
     *
     * NOTE: Please see ::copyTable() for more details on the parameters below.
     *
     * @param   string      $fromObject     The name of SOURCE versioned object to copy field data from.
     * @param   string      $toObject       The name of DESTINATION versioned object to copy field data to.
     * @param   array|null  $fieldMapping
     * @param   bool        $purgeDest
     * @param   mixed|null  $where
     * @throws  MigrationException
     */
    public static function copyVersionedTable($fromObject, $toObject, array $fieldMapping = null, $purgeDest = false, $where = null) {
        // Quick validation.
        foreach(array($fromObject, $toObject) as $validateObject) {
            if (!class_exists($validateObject)) throw new MigrationException("'$validateObject' doesn't appear to be an object.");
            if (is_a($validateObject, DataObject::class)) throw new MigrationException("'$validateObject' must be an instance of DataObject.");

            /** @var $validateInstance DataObject */
            $validateInstance = singleton($validateObject);
            if (!$validateInstance->hasExtension(Versioned::class)) throw new MigrationException("'$validateObject' must be a versioned object (i.e. have the Versioned extension).");
        }

        // Repeat on each instance of the objects' tables.
        $suffixes = array('', '_Live', '_versions');
        foreach($suffixes as $suffix) {
            self::copyTable($fromObject . $suffix, $toObject . $suffix, $fieldMapping, $purgeDest, $where);
        }
    }

}
