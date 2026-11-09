# Silverstripe Database Migrations
Facilitates atomic database migrations in Silverstripe. Inspired by [Laravel's migration capability](http://laravel.com/docs/master/migrations), this relatively simple module was built to work as an augmentation to the existing `dev/build` that is already commonplace in Silverstripe.

Silverstripe's database schema is *declarative*, which means that the code defines what the state of the database *currently should be* and therefore the system will do what it needs to do in order to change the current schema to match that declared structure at any given moment (by proxy of `dev/build`). In contrast, database migrations offer a method to *imperatively* define how that structure (as well as the data) should change progressively over time. The advantage to this is that it makes it easier for you to rename columns (while retaining data), combine multiple columns or even change the format of data over time without leaving behind legacy code which should no longer exist, helping keep things tidy.


## Installation

Install with `composer`:

```bash
composer require patricknelson/silverstripe-migrations
sake dev/build
```


## How to Use

This module sets up a task called `MigrateTask` which is available from the command line only via:

```bash
sake dev/tasks/MigrateTask [option]
```

### Commands

Run any pending migrations that haven't been run yet (multiple are clustered together as a "batch"):

```bash
sake dev/tasks/MigrateTask up
```

Reverse previous batch of migrations:

```bash
sake dev/tasks/MigrateTask down
```

Make a new migration file for you with boilerplate code.

```
sake dev/tasks/MigrateTask make:migration_name
```

### Configuration

To customize generated migrations, add `<project>/_config/migrations.yml`:

```yaml
---
Name: project-migrations
After:
  - '#patricknelson-migrations'
---
PatrickNelson\Migrations\MigrateTask:
  # Empty selects <project>/src/migrations; relative paths are resolved against BASE_PATH.
  migration_path: 'app/src/custom/path'

  # Empty selects the global namespace. Class names always retain the Migration_ prefix.
  migration_namespace: 'App\Migrations'
```

### How it Works

**Up**

<!-- TODO: SSv4: Rewrite -->

Each time you run an `up` migration, this task will look through all migration files that have been setup in your `<project>/code/migrations` folder (which you can customize) and then compare that to a list of migrations that it has already run in the `DatabaseMigrations` table. If it finds a new migration that has not yet been run, it will execute the `->up()` method on that migration file and keep a record of it in that table. Also, it will make sure to only run migrations in alphanumeric order based on their file name.

**Down**

When multiple migrations are run together, they are considered a single batch and can be rolled back (or reversed) all together as well by using the `down` option. When `down` migrations are performed, they are done in reverse order one batch at a time to help ensure consistency.

### Writing Migrations

You can easily generate migration files by running the task with the `make:migration_name` option (switching out `migration_name` with a concise description of your migration using only underscores, letters and numbers).

**Example:**

`sake dev/tasks/MigrateTask make:change_serialize_to_json`

This will generate a file following the format `YYYY_MM_DD_HHMMSS_change_serialize_to_json.php`, using the current date to name the file (to ensure it is executed in order) and containing the class `Migration_ChangeSerializeToJson`. It should look like this:

<!-- TODO: SSv4: Revisit and rewrite once dust settles -->

```php
<?php

class Migration_ChangeSerializeToJson extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		// Go through each DataObject and convert the column format from serialized to JSON.
		foreach(MyDataObject::get() as $instance) {
			$instance->EncodedField = json_encode(unserialize($instance->EncodedField));
		}
	}

	/**
	 * Reverse the migration (this does the opposite of the method above).
	 *
	 * @return void
	 */
	public function down() {
		// Go through each DataObject and convert the column format back from JSON to serialized again.
		foreach(MyDataObject::get() as $instance) {
			$instance->EncodedField = serialize(json_decode($instance->EncodedField));
		}
	}
}

```

A collection of helper methods are also available on the `Migration` class that perform common database interactions, such as dropping columns from a table or retrieving a value from a column that is no longer available through the ORM but still exists in the database table.

### Helper Methods

Documentation for helper methods can be found [here](doc/HelperMethods.md).

### Running Your Migrations

It's important that before you ever migrate `up` or `down` that you make sure you run the `dev/build` task **first**. For example, you could do the following:

```bash
sake dev/build
sake dev/tasks/MigrateTask up
```

This will ensure that both the migration classes are available (in the class map) and that the new fields you've declared in your `DataObject`'s are accessible to your migration.


## Known Issues

<!-- TODO: SSv4: Rewrite; not very clear -->

Due to the fact that the existing `dev/build` process runs independently from these migrations (instead of being based already on migrations), it is possible that you might end up running migrations that are no longer applicable to the given declared state in your current DataObject `::$db` static definitions. To help avoid issues in more complex websites, you can either perform checks within the migrations themselves or setup new migrations to coexist with code that is bundled into release branches and deploy them discretely (e.g. `release-1.2.0` or `hotfix-1.2.1`). The primary goal is to ensure that data/content in existing environments can be retained and changed selectively without losing a column, a table or replacing an entire table or database each time your schema has to change.


## Change Log

- v0.x
- v1:
  - Add support for Silverstripe 4
  - Moved to `PatrickNelson\Migrations` namespace
  - Removed both `Migration::loginAsAdmin()` and `::whileAdmin()` helper methods. They are no longer required in SS4 for supporting the core `::publish()` and `::unpublish()` methods.
  - Added parameter and return types to migration helpers while retaining PHP 7.4 compatible syntax.
  - Moved library classes to `src/` with PSR-4 autoloading.
  - Table and column names are now quoted as identifiers via `Convert::symbol2sql()` instead of escaped with `Convert::raw2sql()`. Remove manual identifier quoting passed to methods, such as backticks around column names passed to `addColumnsToTable()`, e.g. ``addColumnsToTable('MyObject', ['`FooBar`' => 'INT'])`` should be cleaned up
  - Replaced `MIGRATION_PATH` with YAML configuration for the generation path and optional namespace. New migrations remain global by default and always keep the `Migration_` prefix.


## To Do

<!-- TODO: SSv4: Probably best to just remove these and/or migrate to issues for better visibility (plus this is clutter) -->

- Add ability to "prune" database fields and tables, which not only removes defunct fields/tables which are no longer used, but also re-orders them. Would be a highly destructive feature but potentially very helpful if used regularly (and responsibly).
- Method to obtain a hash as a signature for the current schema being defined by `DataObject` child classes. Will be helpful in creating migrations that should only be run if the current schema/state matches a certain hash.
- Setup a `--pretend` option to allow the ability to preview all queries that will be executed by migrations (both up and down).
