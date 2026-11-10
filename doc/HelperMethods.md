# Silverstripe Database Migrations (Helper Methods)

These helper methods are available to all `Migration` objects that you create.

- [Page operations](#page-operations)
- [Copying tables](#copying-tables)
- [Reading and updating data](#reading-and-updating-data)
- [Schema changes](#schema-changes)
- [Table and field inspection](#table-and-field-inspection)

## Page operations

### publish

Saves a page and recursively publishes it if there are no pending draft changes, or when `$force = true`.

```php
static::publish($page);
```

---

### unpublish

Unpublishes a page while retaining its draft.

```php
static::unpublish($page);
```

---

### setPageType

Changes a page's type and publishes it, including pending draft changes.

```php
static::setPageType($page, \MyCustomPage::class);
```

<br>

## Copying tables

### copyTable

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

---

### copyVersionedTable

Copies the base, `_Live` and `_Versions` tables using `copyTable()`. All source and destination tables must exist.

```php
static::copyVersionedTable('SourceTable', 'DestinationTable');
```

<br>

## Reading and updating data

### getRowValueFromTable

Gets the value for a single column in a row from the database by the ID column. Useful when a field has been removed from the class' `$db` property, and therefore is no longer accessible through the ORM. Returns `null` if the table, column or row does not exist.

```php
$FieldName = static::getRowValueFromTable('MyDataObject', 'FieldName', 15);
var_dump($FieldName);

// output
string 'Foo'
```

---

### getRowValuesFromTable

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

---

### setRowValuesOnTable

Sets raw column values by ID. Returns `false` if the table or columns do not exist, otherwise `true` after executing the query. Set `$insert = true` to insert a missing row; include `ID` in `$values` to specify its ID.

```php
static::setRowValuesOnTable('MyDataObject', [
	'FieldName' => 'Bar',
	'OtherField' => 321
], $id = 15, $insert = true);
```

---

### transitionField

Copies an old field's value to a new field, optionally transforming it, then saves the object. Works for fields removed from `$db`; pages use `publish()`.

```php
static::transitionField($record, 'OldField', 'NewField', static function ($value) {
	return strtoupper($value ?? '');
});
```

<br>

## Schema changes

### addColumnsToTable

Adds columns with the specified properties to a database table, if they don't already exist. Returns array of columns that were added.

```php
static::addColumnsToTable('MyDataObject', [
	'FieldName' => 'VARCHAR(255) CHARACTER SET utf8',
	'OtherField' => 'INT(11) NOT NULL'
]);
```

---

### dropColumnsFromTable

Drops columns from a database table. Returns array of columns that were dropped.

```php
static::dropColumnsFromTable('MyDataObject', ['FieldName', 'OtherField']);
```

<br>

## Table and field inspection

### tableExists

Returns true if a table exists in the database.

```php
if (static::tableExists('MyDataObject')) { /*.*/ }
```

---

### tableColumnExists

Returns true if a single column exists on a database table.

```php
if (static::tableColumnExists('MyDataObject', 'FieldName')) { /*.*/ }
```

---

### tableColumnsExist

Returns true if an array of columns exist on a database table.

```php
if (static::tableColumnsExist('MyDataObject', ['FieldName', 'OtherField'])) { /*.*/ }
```

---

### getTableColumns

Returns an array of columns (and their properties) that exist on a database table, or an empty array if the table does not exist.

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

---

### getTableForField

Returns the table containing a field in the class hierarchy, or an empty string if not found. Works for fields removed from `$db`.

```php
$table = static::getTableForField(\MyDataObject::class, 'OldField');
```

---

### getDataExtensionTables

Returns possible tables for DataObjects with the extension applied directly, including `_Live` and `_Versions` tables for versioned classes.

```php
$tables = static::getDataExtensionTables(\MyDataExtension::class);
```
