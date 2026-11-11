# Silverstripe Database Migrations
Facilitates atomic database migrations in Silverstripe. Inspired by [Laravel's migration capability](http://laravel.com/docs/master/migrations), this relatively simple module works as an augmentation to the existing `dev/build` in Silverstripe.

Silverstripe's database schema is ***declarative***, where code defines what the state of the database _currently should be_ and the system will take steps to match that declared state (done via `dev/build`). Database migrations offer a method to extend this by ***imperatively*** changing database state progressively _over time_. This makes it easier to rename columns without losing data, combine multiple columns, or even change the format of data without leaving behind legacy code. It helps you keep things tidy!


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

Run any new/pending migrations (multiple are clustered together as a "batch"):

```bash
sake dev/tasks/MigrateTask up
```

Reverse the previous batch of migrations:

```bash
sake dev/tasks/MigrateTask down
```

Make a new migration with boilerplate code:

```bash
# Creates class called Migration_MigrationName
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

Each time you run an `up` migration, this task will look through all migration files that have been setup in your configured migrations folder and then compare that to a list of migrations that it has already run in the `DatabaseMigrations` table. If it finds a new migration that has not yet been run, it will execute the `->up()` method on that migration class and keep a record of it in that table. Also, it will make sure to only run migrations in alphanumeric order based on their file name.

**Down**

When multiple migrations are run together, they are considered a single batch and can be rolled back (or reversed) all together as well by using the `down` option. When `down` migrations are performed, the corresponding `->down()` methods are executed in reverse order, one batch at a time to help ensure consistency.


### Writing Migrations

You can easily generate migration files by running the task with the `make:migration_name` option (switching out `migration_name` with a concise description of your migration using only underscores, letters and numbers).

**Example:**

```bash
sake dev/tasks/MigrateTask make:change_serialize_to_json
```

This will generate a filename formatted like `YYYY_MM_DD_HHMMSS_change_serialize_to_json.php`, using the current date to ensure it is executed in order. That file will contain a class named `Migration_ChangeSerializeToJson` (converting `snake_case` to `CamelCase`).

Once modified, your migration might look like this:

```php
<?php

namespace App\Migrations;

use App\MyDataObject;
use PatrickNelson\Migrations\Migration;

class Migration_ChangeSerializeToJson extends Migration {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up() {
		// Go through each DataObject and convert the column format from serialized to JSON.
		foreach(MyDataObject::get() as $instance) {
			$instance->EncodedField = json_encode(unserialize($instance->EncodedField));
			$instance->write();
		}
	}

	/**
	 * Reverse the migration (this should do the exact opposite of the method above).
	 *
	 * @return void
	 */
	public function down() {
		// Go through each DataObject and convert the column format back from JSON to serialized again.
		foreach(MyDataObject::get() as $instance) {
			$instance->EncodedField = serialize(json_decode($instance->EncodedField));
			$instance->write();
		}
	}
}

```

A collection of helper methods are also available on the `Migration` class that perform common database interactions, such as dropping columns from a table or retrieving a value from a column that is no longer available through the ORM but still exists in the database table.

### Obsolete migrations

You can flag old migrations that should no longer be run (but maybe kept in code for reference) as obsolete by simply adding `protected $obsolete = true` to your class, e.g.


```php
class Migration_OutdatedMigration extends Migration {

    protected $obsolete = true;

    // ...
}
```

Alternatively, you can create a subdirectory such as `migrations/obsolete/` and add the special `_manifest_exclude` file (see [Silverstripe documentation](https://docs.silverstripe.org/en/developer_guides/execution_pipeline/manifests/#php-class-manifest)) and relocate obsolete migrations there. This will prevent `MigrateTask` from seeing the migrations and attempting to run them.

### Helper Methods

Documentation for helper methods can be found [here](doc/HelperMethods.md).

### Tip: Running New Migrations

It's important that before you ever migrate `up` or `down` that you make sure you run the `dev/build` task **first**. For example, you could do the following:

```bash
sake dev/build
sake dev/tasks/MigrateTask up
```

This will ensure that both the migration classes are available (in the class map) and that the new fields you've declared in your `DataObject`'s are accessible to your migration.

<!-- TODO: Add another helpful tip later about removing old/outdated schema in a way that is safe, e.g. break it up into multiple deploys -->


## Changelog

A history of all changes can be found on the [releases page](https://github.com/patricknelson/silverstripe-migrations/releases).


## To Do

<!-- TODO: Probably best to just remove these and/or migrate to issues for better visibility (plus this is clutter) -->

- Add ability to "prune" database fields and tables, which not only removes defunct fields/tables which are no longer used, but also re-orders them. Would be a highly destructive feature but potentially very helpful if used regularly (and responsibly).
- Setup a `--pretend` option to allow the ability to preview all queries that will be executed by migrations (both up and down).
