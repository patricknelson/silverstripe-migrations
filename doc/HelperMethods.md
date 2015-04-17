# SilverStripe 3.x Database Migrations

## Helper Methods

### `(boolean) tableExists(string $table)`

Returns true if a table exists in the database.

```php
if (self::tableExists('MyDataObject')) { /*.*/ }
```

### `(boolean) tableColumnExists(string $table, string $column)`

Returns true if a single column exists on a database table.

```php
if (self::tableColumnExists('MyDataObject', 'FieldName')) { /*.*/ }
```

### `(boolean) tableColumnsExist(string $table, array $column)`

Returns true if an array of columns exist on a database table.

```php
if (self::tableColumnsExist('MyDataObject', ['FieldName', 'OtherField'])) { /*.*/ }
```

### `(array) getTableColumns(string $table)`

Returns an array of columns (and their properties) that exist on a database table.

```php
$columns = self::getTableColumns('MyDataObject');
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

### `(boolean) dropColumnsFromTable(string $table, array $columns)`

Drops columns from a database table. Returns false if the table or any of the columns do not exist. Returns true if the SQL query was executed.

```php
self::dropColumnsFromTable('MyDataObject', ['FieldName', 'OtherField']);
```

### `(boolean) addColumnsToTable(string $table, array $columns)`

Adds columns with the specified properties to a database table if they don't already exist. Returns false if the table does not exist. Returns true if the SQL query was executed.

```php
self::addColumnsToTable('MyDataObject', [
	'FieldName' => 'VARCHAR(255) CHARACTER SET utf8', 
	'OtherField' => 'INT(11) NOT NULL'
]);
```

### `(string) getRowValueFromTable(string $table, string $field, int $id)`

Gets the value for a single column in a row from the database by the ID column. Useful when a field has been removed from the class' `$db` property, and therefore is no longer accessible through the ORM. Returns `null` if the table, column or row does not exist.

```php
$FieldName = self::getRowValueFromTable('MyDataObject', 'FieldName', 15);
var_dump($FieldName);

// output
string 'Foo'
```

### `(array) getRowValuesFromTable(string $table, array $fields, int $id)`

Gets the values for multiple rows on a database table by the ID column. Useful when fields have been removed from the class' `$db` property, and therefore are no longer accessible through the ORM. Returns an empty array if the table, any of the columns or the row do not exist.

```php
$values = self::getRowValuesFromTable('MyDataObject', ['FieldName', 'OtherField'], 15);
var_dump($values);

// output
array {
	'FieldName' => 'Foo'
	'OtherField' => '123'
}
```

### `(boolean) setRowValuesOnTable(string $table, array $values, int $id)`

Sets the values for multiple rows on a database table by the ID column. Useful when fields have been removed from the class' `$db` property, and therefore are no longer accessible through the ORM. Returns false if the table or any of the rows do not exist. Returns true if the SQL query was executed.

```php
self::setRowValuesOnTable('MyDataObject', [
	'FieldName' => 'Bar', 
	'OtherField' => 321
], 15);
```

