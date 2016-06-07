# SilverStripe 3.x Database Migrations

## Helper Methods

#### `(null) publish(SiteTree $page)`

Allows you to publish a page. Useful since SilverStripe requires permissions to publish a page. However...

**WARNING:** Because SilverStripe requires permissions to publish or un-publish a page, the system will temporarily log in as the default administrator in order to run the current batch of migrations if you use this feature. This is only temporary and will go away after the migration has completed. Note also that, from a security standpoint, migrations are setup to only allow access from the command line, so presumably any attacker who could execute migrations could also feasibly read the default admin's username/password from `_ss_environment.php` (if set).

#### `(null) unpublish(SiteTree $page)`

Allows you to un-publish a page.

**WARNING:** See warning for `::publish()` above.


#### `(boolean) tableExists(string $table)`

Returns true if a table exists in the database.

```php
if (static::tableExists('MyDataObject')) { /*.*/ }
```

#### `(boolean) tableColumnExists(string $table, string $column)`

Returns true if a single column exists on a database table.

```php
if (static::tableColumnExists('MyDataObject', 'FieldName')) { /*.*/ }
```

#### `(boolean) tableColumnsExist(string $table, array $column)`

Returns true if an array of columns exist on a database table.

```php
if (static::tableColumnsExist('MyDataObject', ['FieldName', 'OtherField'])) { /*.*/ }
```

#### `(array) getTableColumns(string $table)`

Returns an array of columns (and their properties) that exist on a database table.

```php
$columns = static::getTableColumns('MyDataObject');
var_dump($columns);

// output
array {
	'ID' => 'int(11) not null auto_increment',
	'Created' => 'datetime',
	'LastEdited' => 'datetime',
	'Title' => 'varchar(255) character set utf8 collate utf8_general_ci',
	'Content' => 'mediumtext character set utf8 collate utf8_general_ci'
}
```

#### `(boolean) dropColumnsFromTable(string $table, array $columns)`

Drops columns from a database table. Returns array of columns that were dropped.

```php
static::dropColumnsFromTable('MyDataObject', ['FieldName', 'OtherField']);
```

#### `(boolean) addColumnsToTable(string $table, array $columns)`

Adds columns with the specified properties to a database table, if they don't already exist. Returns array of columns that were added.

```php
static::addColumnsToTable('MyDataObject', [
	'FieldName' => 'VARCHAR(255) CHARACTER SET utf8',
	'OtherField' => 'INT(11) NOT NULL'
]);
```

#### `(string) getRowValueFromTable(string $table, string $field, int $id)`

Gets the value for a single column in a row from the database by the ID column. Useful when a field has been removed from the class' `$db` property, and therefore is no longer accessible through the ORM. Returns `null` if the table, column or row does not exist.

```php
$FieldName = static::getRowValueFromTable('MyDataObject', 'FieldName', 15);
var_dump($FieldName);

// output
string 'Foo'
```

#### `(array) getRowValuesFromTable(string $table, array $fields, int $id)`

Gets the raw values for multiple columns on a database table by the ID column. Useful when fields have been removed from the class' `$db` property, and therefore are no longer accessible through the ORM. Returns an empty array if the table, any of the columns or the row do not exist.

```php
$values = static::getRowValuesFromTable('MyDataObject', ['FieldName', 'OtherField'], 15);
var_dump($values);

// output
array {
	'FieldName' => 'Foo'
	'OtherField' => '123'
}
```

#### `(boolean) setRowValuesOnTable(string $table, array $values, int $id = null, $insert = false)`

Sets the raw values for multiple columns on a database table by the ID column. Useful when fields have been removed from the class' `$db` property, and therefore are no longer accessible through the ORM. Returns false if the table or any of the rows do not exist. Returns true if the SQL query was executed successfully.

```php
static::setRowValuesOnTable('MyDataObject', [
	'FieldName' => 'Bar',
	'OtherField' => 321
], $id = 15, $insert = true);
```


#### `copyTable($fromTable, $toTable, array $fieldMapping = null, $purgeDest = false, $where = null)`

Copies all values from one table to another. Useful when renaming a `DataObject` or renaming a table since this will allow you to carry over old values and translate between old and new column names (if needed). Will override any existing values with matching ID's. Also have the ability to clear out the destination table completely via `$purgeDest`. To prevent copying the entire table, provide a value for `$where` which will be passed directly to the `->setWhere()` method on `SQLSelect`.

**Note:** When passing a `$fieldMapping`, you can also specify `key => value` pairs to map between old/new names (instead of just values). Leave empty (or pass null) to automatically assume ALL fields from source table (including ID).

```php
$fromTable = 'SourceTable';
$toTable = 'DestinationTable';
static::copyTable($fromTable, $toTable, [
	'ID',
	'OldColumnName' => 'NewColumnName',
	'AnotherField',
	'Sort',
], true);
```
