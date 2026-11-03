<?php

namespace PatrickNelson\Migrations;

/**
 * Needed for consistency between unit tests and actual migrations.
 *
 * TODO: SSv4: Consider removal since *all* migrations must descend from an abstract class and this is no longer necessary for unit testing anyway.
 *
 * @author   Patrick Nelson, pat@catchyour.com
 * @since    2016-04-25
 */
interface MigrationInterface {

	public function up();

	public function down();

	public function isObsolete(): bool;

}
