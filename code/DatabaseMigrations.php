<?php

namespace PattricNelson\SilverStripeMigrations;

use SilverStripe\ORM\DataObject;

/**
 * DataObject used to keep track of previously run migrations.
 *
 * @author    Patrick Nelson, pat@catchyour.com
 * @since    2015-02-17
 */

class DatabaseMigrations extends DataObject {

    private static $db = array(
        "BaseName"          => "Varchar(255)",
        "MigrationClass"    => "Varchar(255)",
        "Batch"             => "Int",
        "Stamp"             => "DBDatetime",
    );
    private static $table_name = 'DatabaseMigrations';
}
