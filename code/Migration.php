<?php

/**
 * All migrations that must be executed must be descended from this class and define both an ->up() and a ->down()
 * method. Migrations will be executed in alphanumeric order
 *
 * @author	Patrick Nelson, pat@catchyour.com
 * @since	2015-02-17
 */

abstract class Migration {

	abstract public function up();

	abstract public function down();


	#######################################
	## DATABASE MIGRATION HELPER METHODS ##
	#######################################

	/**
	 * Returns true if table exists in the database
	 *
	 * @param string $table
	 * @return boolean
	 */
	protected static function tableExists($table) {
		$tables = DB::tableList();
		return array_key_exists(strtolower($table), $tables);
	}

	/**
	 * Returns true if a column currently exists in a database table
	 *
	 * @param string $table
	 * @param string $column
	 * @return boolean
	 */
	protected static function tableColumnExists($table, $column) {
		if (!self::tableExists($table)) return false;
		$columns = self::getTableColumns($table);
		return array_key_exists($column, $columns);
	}

	/**
	 * Returns true if an array of columns currently exist on a database table
	 *
	 * @param string $table
	 * @param array $columns
	 * @return boolean
	 */
	protected static function tableColumnsExist($table, array $columns) {
		if (!self::tableExists($table)) return false;
		return count(array_intersect($columns, array_keys(self::getTableColumns($table)))) === count($columns);
	}

	/**
	 * Returns an array of columns for a database table
	 *
	 * @param string $table
	 * @return array (empty if table doesn't exist)
	 */
	protected static function getTableColumns($table) {
		if (!self::tableExists($table)) return [];
		return DB::fieldList($table);
	}

	/**
	 * Drop columns from a database table if they exist
	 *
	 * @param string $table
	 * @param array $columns
	 * @return boolean true if any query was executed
	 */
	protected static function dropColumnsFromTable($table, array $columns) {
		$queried = false;
		$existingColumns = self::getTableColumns($table);
		if ($existingColumns) {
			foreach ($columns as $column) {
				if (array_key_exists($column, $existingColumns)) {
					DB::query("ALTER TABLE $table DROP COLUMN $column;");
					$queried = true;
				}
			}
		}
		return $queried;
	}

	/**
	 * Add columns to a database table if they don't exist
	 *
	 * @param string $table
	 * @param array $columns e.g. ['MyColumn' => 'VARCHAR(255) CHARACTER SET utf8']
	 * @return boolean true if any query was executed
	 */
	protected static function addColumnsToTable($table, array $columns) {
		$queried = false;
		$existingColumns = self::getTableColumns($table);
		if ($existingColumns) {
			foreach ($columns as $column => $properties) {
				if (!array_key_exists($column, $existingColumns)) {
					DB::query("ALTER TABLE $table ADD $column $properties;");
					$queried = true;
				}
			}
		}
		return $queried;
	}

	/**
	 * Get a single field value from a row in a database table by ID
	 * For those times when you can't use the ORM
	 * e.g. the fields have been removed from $db
	 *
	 * @param string $table
	 * @param string $columns
	 * @param string||int $id
	 * @return mixed
	 */
	protected static function getRowValueFromTable($table, $field, $id) {
		$value = null;
		if (self::tableColumnExists($table, $field)) {
			$query = new SQLQuery();
			$query->setFrom($table)->setSelect([$field])->setWhere("ID = $id");
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
	 * Set field values for a row in a database table by ID
	 *
	 * @param string $table
	 * @param array $values ['FieldName' => value]
	 * @param string||int $id
	 * @return boolean true if query was executed
	 */
	protected static function setRowValuesOnTable($table, array $values, $id) {
		$queried = false;
		if (self::tableColumnsExist($table, array_keys($values))) {
			$query = "UPDATE $table SET";
			$valuesCount = count($values);
			$i = 0;
			foreach ($values as $field => $value) {
				if (is_string($value)) $value = "'" . Convert::raw2sql($value) . "'";
				$query .= " $field = " . $value;
				if ($i < $valuesCount - 1) $query .= ",";
				$i++;
			}
			$query .= " WHERE ID = $id;";
			DB::query($query);
			$queried = true;
		}
		return $queried;
	}

}
