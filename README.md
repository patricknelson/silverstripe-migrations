# SilverStripe 3.x Database Migrations
Facilitates atomic database migrations in SilverStripe 3.x. Inspired by [Laravel's migration capability](http://laravel.com/docs/master/migrations), this relatively simple module was built to work as an augmentation to the existing `dev/build` that is already commonplace in SilverStripe. 

SilverStripe's database schema is *declarative*, which means that the code defines what the state of the database *currently should be* and therefore the system will do what it needs to do in order to change the current schema to match that declared structure at any given moment (by proxy of `dev/build`). In contrast, database migrations offer a method to *imperatively* define how that structure (as well as the data) should change progressively over time. The advantage to this is that it makes it easier for you to rename columns (while retaining data), combine multiple columns or even change the format of data over time without leaving behind legacy code which should no longer exist, helping keep things tidy.   

## Installation

### Composer 

1. Setup composer dependency: `composer require "patricknelson/silverstripe-migrations:*"`
2. Run `sake dev/build` from the command line to ensure it is properly loaded into SilverStripe.

### Manual Installation

1. Download the latest [repository zip file](https://github.com/patricknelson/silverstripe-migrations/archive/master.zip).
2. Extract the folder `silverstripe-migrations-master` and rename it `migrations`.
3. Copy that folder to the root of your website.
4. Run `sake dev/build` from the command line to ensure it is properly loaded into SilverStripe.



## How to Use

This module sets up a task called `MigrateTask` which is available from the command line only via either:

- `sake dev/tasks/MigrateTask [option]`
- `php framework/cli-script.php dev/tasks/MigrateTask [option]`

Using this task, you can do the following:

1. Run migrations (i.e. "up"). Example:
	- `sake dev/tasks/MigrateTask up`
2. Reverse previous migrations (i.e. "down"). Example:
	- `sake dev/tasks/MigrateTask down`
3. Make a new migration file for you with boilerplate code. Example:
	- `sake dev/tasks/MigrateTask make:adding_column_to_table`

### Writing your migration file

You can simplify the generation of your migration files by running the task with the `make:migration_name` option (switching out `migration_name` with a concise description of your migration using only underscores, letters and numbers). The example in #3 above will generate a file following the format `YYYY_MM_DD_HHMMSS_adding_column_to_table.php`, using the current date to name the file (to ensure it is executed in order) and containing the class `Migration_AddingColumnToTable`. It should look like this:

```php
<?php

class Migration_AddingColumnToTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		// TODO: Implement up() method.
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		// TODO: Implement down() method.
	}

}
```

**IMPORTANT:** This file will be automatically placed in your project directory in the path `<project>/code/migrations`. This can be overridden by defining an absolute path in the constant `MIGRATION_PATH` in your `_ss_environment.php` file. Migration files that are automatically generated will be pseudo-namespaced with a `Migration_` prefix to help reduce possible class name collisions.


### Running Your Migrations

It's important that before you ever migrate `up` or `down` that you make sure you run the SilverStripe `dev/build` task **first**. For example, you could do the following:

```bash
sake dev/build
sake dev/tasks/MigrateTask up
```

This will ensure that both the migration classes are available (in the class map) and that the new fields you've declared in your `DataObject`'s are accessible to your migration.  


## Known Issues

Due to the fact that the existing `dev/build` process runs independently from these migrations (instead of being based already on migrations), it is possible that you might end up running migrations that are no longer applicable to the given declared state in your current DataObject `::$db` static definitions. To help avoid issues in more complex websites, setup new migrations to coexist with code that is bundled into release branches and deploy them discretely (e.g. `release-1.2.0` or `hotfix-1.2.1`). The primary goal is to ensure that data/content in existing environments can be retained and changed selectively without losing a column, a table or replacing an entire table or database each time your schema has to change.


## To Do

- Setup a `--pretend` option to allow the ability to preview all queries that will be executed by migrations (both up and down). 