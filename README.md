# SilverStripe 3.x Database Migrations
Facilitates atomic database migrations in SilverStripe 3.x. Inspired by [Laravel's migration capability](http://laravel.com/docs/master/migrations), this relatively simple module was built to work as an augmentation to the existing `dev/build` that is already commonplace in SilverStripe. 

SilverStripe's database schema is *declarative*, which means that the code defines what the state of the database *currently should be* and therefore the system will do what it needs to do in order to change the current schema to match that declared structure at any given moment (by proxy of `dev/build`). In contrast, database migrations offer a method to *imperatively* define how that structure (as well as the data) should change progressively over time. The advantage to this is that it makes it easier for you to rename columns (while retaining data), combine multiple columns or even change the format of data over time without leaving behind legacy code which should no longer exist, helping keep things tidy.   


## Installation

### Composer 

1. Run `composer require "patricknelson/silverstripe-migrations:dev-master"`
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

1. Run migrations (i.e. "up").
	- `sake dev/tasks/MigrateTask up`
2. Reverse previous migrations (i.e. "down").
	- `sake dev/tasks/MigrateTask down`
3. Make a new migration file for you with boilerplate code.
	- `sake dev/tasks/MigrateTask make:migration_name`
	- **Note:** This file will be automatically placed in your project directory in the path `<project>/code/migrations`. You can customize this location by defining a `MIGRATIONS_PATH` constant which should be the absolute path to the desired directory (either in your `_ss_environment.php` or `_config.php` files). Also, migration files that are automatically generated will be pseudo-namespaced with a `Migration_` prefix to help reduce possible class name collisions.

### How it Works

**Up**

Each time you run an `up` migration, this task will look through all migration files that have been setup in your `<project>/code/migrations` folder (which you can customize) and then compare that to a list of migrations that it has already run in the `DatabaseMigrations` table. If it finds a new migration that has not yet been run, it will execute the `->up()` method on that migration file and keep a record of it in that table. Also, it will make sure to only run migrations in alphanumeric order based on their file name.  

**Down**

When multiple migrations are run together, they are considered a single batch and can be rolled back (or reversed) all together as well by using the `down` option. When `down` migrations are performed, they are done in reverse order one batch at a time to help ensure consistency. 

### Writing Migrations

You can easily generate migration files by running the task with the `make:migration_name` option (switching out `migration_name` with a concise description of your migration using only underscores, letters and numbers). 

**Example:**

`sake dev/tasks/MigrateTask make:change_serialize_to_json`

This will generate a file following the format `YYYY_MM_DD_HHMMSS_change_serialize_to_json.php`, using the current date to name the file (to ensure it is executed in order) and containing the class `Migration_ChangeSerializeToJson`. It should look like this:

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

### Running Your Migrations

It's important that before you ever migrate `up` or `down` that you make sure you run the SilverStripe `dev/build` task **first**. For example, you could do the following:

```bash
sake dev/build
sake dev/tasks/MigrateTask up
```

This will ensure that both the migration classes are available (in the class map) and that the new fields you've declared in your `DataObject`'s are accessible to your migration.  


## Known Issues

Due to the fact that the existing `dev/build` process runs independently from these migrations (instead of being based already on migrations), it is possible that you might end up running migrations that are no longer applicable to the given declared state in your current DataObject `::$db` static definitions. To help avoid issues in more complex websites, you can either perform checks within the migrations themselves or setup new migrations to coexist with code that is bundled into release branches and deploy them discretely (e.g. `release-1.2.0` or `hotfix-1.2.1`). The primary goal is to ensure that data/content in existing environments can be retained and changed selectively without losing a column, a table or replacing an entire table or database each time your schema has to change.


## To Do

- Method to obtain a hash as a signature for the current schema being defined by `DataObject` child classes. Will be helpful in creating migrations that should only be run if the current schema/state matches a certain hash. 
- Setup a `--pretend` option to allow the ability to preview all queries that will be executed by migrations (both up and down). 