<?php

/**
 * Task which allows you to do the following:
 *
 * 1. Run migrations (i.e. "up"). Example:
 *
 * 		sake dev/tasks/MigrateTask up
 *
 * 2. Reverse previous migrations (i.e. "down"). Example:
 *
 * 		sake dev/tasks/MigrateTask down
 *
 * 3. Make a new migration file for you with boilerplate code. Example:
 *
 * 		sake dev/tasks/MigrateTask make:adding_column_to_table
 *
 * This generates a file like the following, containing the class "Migration_AddingColumnToTable":
 *
 * 		2015_01_01_12345678_name_of_your_migration.php
 *
 * IMPORTANT: This file will be automatically placed in your project directory in the path "<project>/code/migrations".
 * This can be overridden by defining an absolute path in the constant "MIGRATION_PATH" in your _ss_environment.php file.
 * Migration files that are automatically generated will be pseudo-namespaced with a "Migration_" prefix to help reduce
 * possible class name collisions.
 *
 * @author	Patrick Nelson, pat@catchyour.com
 * @since	2015-02-17
 */

class MigrateTask extends BuildTask {
	protected $title = 'Database Migrations (Module)';

	protected $description = 'Performs atomic database migrations.';

	protected $enabled = true;

	/**
	 * @param	SS_HTTPRequest $request
	 */
	public function run($request) {
		// Only allow execution from the command line (for simplicity).
		if (!Director::is_cli()) {
			echo "<p>Sorry, but this can only be run from the command line.</p>";
			return;
		}

		// Get and pre-process arguments. Format: ["argument" => true, "make" => "filename", ... ]
		$getVars = $request->getVars();
		$args = [];
		if (isset($getVars["args"]) && is_array($getVars["args"])) {
			foreach($getVars["args"] as $arg) {
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
		set_error_handler(function($errno , $errstr) {
			throw new Exception($errstr, $errno);
		});

		// Determine action to take. Wrap everything in a transaction so it can be rolled back in case of error.
		DB::getConn()->transactionStart();
		try {
			if (isset($args["up"])) {
				$this->up();

			} elseif (isset($args["down"])) {
				$this->down();

			} elseif (isset($args["make"])) {
				$this->make($args["make"]);

			} else {
				throw new Exception("Invalid or no migration arguments provided. Please specify either: 'up', 'down' or 'make:name_of_your_migration'.");
			}

			// Commit.
			DB::getConn()->transactionEnd();

		} catch(Exception $e) {
			// Rollback and notify user.
			DB::getConn()->transactionRollback();
			$this->output("ERROR (" . $e->getCode() . "): " . $e->getMessage());
			$this->output("\nNote: Any database changes have been rolled back.");
		}

		// Revert back to previous error handling.
		restore_error_handler();
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
		foreach($queue as $baseName => $className) {
			/* @var $instance Migration */
			$instance = new $className();
			$instance->up();

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
		foreach($lastMigrations as $baseName => $className) {
			/* @var $instance Migration */
			$instance = new $className();
			$instance->down();

			// Remove this migration from the database now.
			DatabaseMigrations::get()->filter("BaseName", $baseName)->first()->delete();

			$this->output("Reversed: $baseName");
		}
	}


	/**
	 * Generates a new migration.
	 *
	 * @param	string	$baseName
	 * @throws	Exception
	 */
	public function make($baseName) {
		// Get the migration path.
		$migrationPath = static::getMigrationPath();

		// Ensure determine migration path exists and is writable.
		if (!is_dir($migrationPath)) {
			throw new Exception("Cannot find the directory '$migrationPath'. Please ensure that it exists and is writeable.");

		} elseif (!is_writeable($migrationPath)) {
			throw new Exception("Cannot write to '$migrationPath'. Please ensure that it is writeable.");
		}

		// Setup a filename based on the current timestamp with the prefix.
		$baseName = strtolower($baseName);
		$filename = date("Y_m_d_His") . "_" . $baseName . ".php";
		$filePath = $migrationPath . DIRECTORY_SEPARATOR . $filename;

		// Generate a camel cased class name based on the snake cased base name provided. Basically we're just taking
		// underscores, replacing with spaces and using "ucwords()" to capitalize what we need and then removing those spaces.
		$camelCase = "Migration_" . str_replace(" ", "", ucwords(strtolower(trim(str_replace("_", " ", $baseName)))));

		// Do a quick check to make sure this class doesn't already exist...
		if (class_exists($camelCase)) {
			throw new Exception("Cannot automatically generate a migration class called '$camelCase' (derived from '$baseName'), since that class already appears to exist.");
		}

		// Get boilerplate file contents, find/replace some contents and write to file path.
		$sourceFile = __DIR__ . DIRECTORY_SEPARATOR . "MigrationBoilerplate.php";
		$sourceData = file_get_contents($sourceFile);
		$sourceData = str_replace("MigrationBoilerplate", $camelCase, $sourceData);
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
	 * @param $text
	 */
	protected function output($text) {
		echo "$text\n";
	}


	/**
	 * Determines the path to store new migration files.
	 *
	 * @return	string
	 * @throws	Exception
	 */
	public static function getMigrationPath() {
		if (defined("MIGRATION_PATH")) {
			// Migration path defined by constant.
			$migrationPath = MIGRATION_PATH;

		} else {
			// Attempt to infer this path automatically based on the project name.
			$project = project();
			if (empty($project)) throw new Exception("Please either define a global '\$project' variable or define a MIGRATION_PATH constant in order to setup a path for migration files to live.");

			// Build path.
			$migrationPath = join(DIRECTORY_SEPARATOR, array(BASE_PATH, $project, "code", "migrations"));
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
		$manifest = SS_ClassLoader::instance()->getManifest();
		$classes = array_diff($manifest->getDescendantsOf("Migration"), array("MigrationBoilerplate"));
		$classesOrdered = array();
		foreach($classes as $className) {
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
	 * @param	bool	$latest
	 * @return	array
	 */
	public static function getRunMigrations($latest = false) {
		$query = DatabaseMigrations::get();
		if ($latest) $query = $query->filter("Batch", static::getLatestBatch());

		// Return an array of migrations as the base name (filename) as the key and associated migration class as the value.
		$migrations = array();
		foreach($query->toArray() as $migration) {
			$migrations[$migration->BaseName] = $migration->MigrationClass;
		}
		return $migrations;
	}


	/**
	 * Returns the number of the latest batch in the database.
	 *
	 * @return int
	 */
	public static function getLatestBatch() {
		return (int) DatabaseMigrations::get()->max("Batch");
	}

}
