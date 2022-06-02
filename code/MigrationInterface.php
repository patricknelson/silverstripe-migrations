<?php

namespace SilverStripe\Migrations;




/**
 * Needed for consistency between unit tests and actual migrations.
 *
 * @author   Patrick Nelson, pat@catchyour.com
 * @since    2016-04-25
 */

interface MigrationInterface {

	public function up();

	public function down();

	/**
	 * @return bool
	 */
	public function isObsolete();

}
