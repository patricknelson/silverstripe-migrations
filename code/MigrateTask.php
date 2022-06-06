<?php

namespace PattricNelson\SilverStripeMigrations;

use Exception;

use PattricNelson\SilverStripeMigrations\Migration;
use PattricNelson\SilverStripeMigrations\MigrationBoilerplate;
use ReflectionClass;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Task which allows you to do the following:
 *
 * 1. Run migrations (i.e. "up"). Example:
 *
 *        sake dev/tasks/MigrateTask up
 *
 * 2. Reverse previous migrations (i.e. "down"). Example:
 *
 *        sake dev/tasks/MigrateTask down
 *
 * 3. Make a new migration file for you with boilerplate code. Example:
 *
 *        sake dev/tasks/MigrateTask make:change_serialize_to_json
 *
 * This generates a file like the following, containing the class "Migration_ChangeSerializeToJson":
 *
 *        YYYY_MM_DD_HHMMSS_change_serialize_to_json.php
 *
 * IMPORTANT: This file will be automatically placed in your project directory in the path "<project>/src/migrations".
 * This can be overridden by defining an absolute path in the constant "MIGRATION_PATH" in your _ss_environment.php file.
 * Migration files that are automatically generated will be pseudo-namespaced with a "Migration_" prefix to help reduce
 * possible class name collisions.
 *
 * @author   Patrick Nelson, pat@catchyour.com
 * @since    2015-02-17
 */

class MigrateTask extends BuildTask {

    protected $title = 'Database Migrations (Module)';

    protected $description = 'Performs atomic database migrations.';

    protected $enabled = true;

    protected $silent = false;

    protected $error = false;

    protected $shutdown = false;

    // Used for error reporting purposes.
    protected $lastMigrationFile = '';

    private static $segment = 'MigrateTask';

    /**
     * @param     HTTPRequest $request
     * @throws    MigrationException
     */
    public function run($request) {
        // Only allow execution from the command line (for simplicity and security).
        if (!Director::is_cli()) {
            echo "<p>Sorry, but this can only be run from the command line.</p>";
            return;
        }

        // Get and pre-process arguments. Format: ["argument" => true, "make" => "filename", ... ]
        $getVars = $request->getVars();
        $args = array();
        if (isset($getVars["args"]) && is_array($getVars["args"])) {
            foreach ($getVars["args"] as $arg) {
                // Separate keys/values.
                $argVals = explode(":", $arg, 2);
                $key = $argVals[0];
                $value = true;
                if (count($argVals) > 1) $value = $argVals[1];
                $args[$key] = $value;
            }
        }

        // Unfortunately, SilverStripe is not using exceptions for database errors for some reason, so we must
        // temporarily setup our own global error handler as a stop gap so we can properly handle transactions.
        set_error_handler(function ($errno, $errstr) {
            throw new MigrationException($errstr, $errno);
        });

        // Use a shutdown function to help clean up and track final exit status, in case an unexpected fatal error occurs.
        $this->error = true;
        register_shutdown_function(array($this, "shutdown"));

        // Determine action to take. Wrap everything in a transaction so it can be rolled back in case of error.
        DB::get_conn()->transactionStart();
        try {
            if (isset($args["up"])) {
                $this->up();

            } elseif (isset($args["down"])) {
                $this->down();

            } elseif (isset($args["make"])) {
                $this->make($args["make"]);

            } else {
                throw new MigrationException("Invalid or no migration arguments provided. Please specify either: 'up', 'down' or 'make:name_of_your_migration'.");
            }

            // Commit and clean up error state..
            DB::get_conn()->transactionEnd();
            $this->error = false;

        } catch (Exception $e) {
            $this->shutdown($e);
        }

        // Shutdown method below will run next.
    }

    /**
     * Will always execute after any/all migrations have run. The purpose of this is to clean up and to handle any
     * unexpected errors which may occur.
     *
     * @param   Exception|null $e
     */
    public function shutdown(Exception $e = null) {
        // Run once.
        if ($this->shutdown) return;
        $this->shutdown = true;

        // Revert back to previous error handling.
        restore_error_handler();

        // If there's an error but no exception, setup an exception now for reporting purposes.
        if ($this->error && !$e) $e = new MigrationException("The migration" . ($this->lastMigrationFile ? " '$this->lastMigrationFile.php'" : "") . " terminated unexpectedly.");
        if ($e) {
            // Rollback database changes and notify user.
            DB::get_conn()->transactionRollback();
            $this->output("ERROR" . ($e->getCode() != 0 ? " (" . $e->getCode() . ")" : "") . ": " . $e->getMessage());
            $this->output("\nNote: Any database changes have been rolled back.");
            $this->output("\nStack Trace:");
            $this->output($e->getTraceAsString());
            exit(1);
        }
    }

    /**
     * Ensure it's only visible in the CLI.
     *
     * @return bool
     */
    public function isEnabled() {
        return Director::is_cli();
    }

    ########################
    ## MAIN FUNCTIONALITY ##
    ########################

    /**
     * Runs all currently outstanding migrations in a single batch, in order (by filename).
     */
    public function up() {
        // Get all already executed migrations and queue up only the ones that haven't run (based on filename).
        $queue = array_diff_key($this->getAllMigrations(), $this->getRunMigrations());
        if (empty($queue)) {
            $this->output("There are no new migrations.");
            return;
        }

        // Go through queue now with an updated batch number.
        $batch = static::getLatestBatch() + 1;
        foreach ($queue as $baseName => $className) {
            // Keep track of last one to execute (for error reporting purposes).
            $this->lastMigrationFile = $baseName;

            // Run migration.
            /* @var $instance Migration */
            $instance = new $className();
            if (!$instance->isObsolete()) {
                $instance->up();
            }

            // Track this migration.
            $migration = new DatabaseMigrations();
            $migration->BaseName = $baseName;
            $migration->MigrationClass = $className;
            $migration->Batch = $batch;
            $migration->Stamp = time();
            $migration->write();

            $this->output("Migrated: $baseName");
        }
    }

    /**
     * Reverses the most recent batch of migrations.
     */
    public function down() {
        $lastMigrations = static::getRunMigrations(true);
        if (empty($lastMigrations)) {
            $this->output("There are no migrations to reverse.");
            return;
        }

        // Execute them in reverse order...
        krsort($lastMigrations);

        // Go through each of the most recent migrations and run their ->down() method.
        foreach ($lastMigrations as $baseName => $className) {
            /* @var $instance Migration */
            $instance = new $className();
            if (!$instance->isObsolete()) {
                $instance->down();
            }

            // Remove this migration from the database now.
            DatabaseMigrations::get()->filter("BaseName", $baseName)->first()->delete();

            $this->output("Reversed: $baseName");
        }
    }

    /**
     * Generates a new migration.
     *
     * @param   string              $baseName
     * @throws  MigrationException
     */
    public function make($baseName) {
        // Get the migration path.
        $migrationPath = static::getMigrationPath();

        // Ensure determine migration path exists and is writable.
        if (!is_dir($migrationPath)) {
            throw new MigrationException("Cannot find the directory '$migrationPath'. Please ensure that it exists and is writeable.");

        } elseif (!is_writeable($migrationPath)) {
            throw new MigrationException("Cannot write to '$migrationPath'. Please ensure that it is writeable.");
        }

        // Normalize the base name to strip out unexpected characters.
        $baseName = strtolower($baseName);
        $baseName = trim(preg_replace("#[^a-z0-9]+#", "_", $baseName), "_");

        // Ensure a valid base name was provided.
        if ($baseName === "") throw new MigrationException("Please provide a valid basename. It can contain only numbers, letters and underscores.");

        // Setup a filename based on the current timestamp with the prefix.
        $filename = date("Y_m_d_His") . "_" . $baseName . ".php";
        $filePath = $migrationPath . DIRECTORY_SEPARATOR . $filename;

        // Generate a camel cased class name based on the snake cased base name provided. Basically we're just taking
        // underscores, replacing with spaces and using "ucwords()" to capitalize what we need and then removing those spaces.
        $camelCase = "Migration_" . str_replace(" ", "", ucwords(strtolower(trim(str_replace("_", " ", $baseName)))));

        // Do a quick check to make sure this class doesn't already exist...
        if (class_exists($camelCase)) {
            throw new MigrationException("Cannot automatically generate a migration class called '$camelCase' (derived from '$baseName'), since that class already appears to exist.");
        }

        // Get boilerplate file contents, find/replace some contents and write to file path.
        $reflect = new ReflectionClass(MigrationBoilerplate::class);
        $sourceData = str_replace(
            [$reflect->getShortName(), 'namespace '.$reflect->getNamespaceName()],
            [$camelCase, 'use '.Migration::class],
            file_get_contents($reflect->getFileName())
        );
        file_put_contents($filePath, $sourceData);

        // Output status and exit.
        $this->output("Created new migration: $filePath");
    }

    ####################
    ## HELPER METHODS ##
    ####################

    /**
     * Output helper.
     *
     * @param   string  $text
     */
    protected function output($text) {
        if (!$this->silent) echo "$text\n";
    }

    /**
     * Squelches output.
     *
     * @param    bool $silent
     */
    public function setSilent($silent) {
        $this->silent = (bool)$silent;
    }

    /**
     * Determines the path to store new migration files.
     *
     * @return    string
     * @throws    MigrationException
     */
    public static function getMigrationPath() {
        // TODO: SSv4: Move to YAML configuration instead.
        if (defined("MIGRATION_PATH")) {
            // Migration path defined by constant.
            $migrationPath = MIGRATION_PATH;

        } else {
            // Attempt to infer this path automatically based on the project name.
            $project = project();
            if (empty($project)) throw new MigrationException("Please either define a global '\$project' variable or define a MIGRATION_PATH constant in order to setup a path for migration files to live.");

            // Build path.
            $migrationPath = join(DIRECTORY_SEPARATOR, array(BASE_PATH, $project, "src", "migrations"));
        }

        return $migrationPath;
    }

    /**
     * Returns an array of all possible migration classes that are currently on the filesystem.
     *
     * @return array
     */
    public static function getAllMigrations() {
        // Get all descendants of the abstract "Migration" class but ensure the class "MigrationBoilerplate" is skipped.
        $manifest = ClassLoader::inst()->getManifest();
        $classes = array_diff($manifest->getDescendantsOf(Migration::class), array(MigrationBoilerplate::class));
        $classesOrdered = array();
        foreach ($classes as $className) {
            // Get actual filename of migration class and use that as the key.
            $reflect = new ReflectionClass($className);
            $filename = basename($reflect->getFileName(), ".php");
            $classesOrdered[$filename] = $className;
        }
        ksort($classesOrdered);
        return $classesOrdered;
    }

    /**
     * Returns all already run migrations or only the latest batch (if specified).
     *
     * @param   bool   $latest
     * @return  array
     */
    public static function getRunMigrations($latest = false) {
        $query = DatabaseMigrations::get();
        if ($latest) $query = $query->filter("Batch", static::getLatestBatch());

        // Return an array of migrations as the base name (filename) as the key and associated migration class as the value.
        $migrations = array();
        foreach ($query->toArray() as $migration) {
            $migrations[$migration->BaseName] = $migration->MigrationClass;
        }
        return $migrations;
    }

    /**
     * Returns the number of the latest batch in the database.
     *
     * @return  int
     */
    public static function getLatestBatch() {
        return (int)DatabaseMigrations::get()->max("Batch");
    }

}
