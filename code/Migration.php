<?php

/**
 * All migrations that must be executed must be descended from this class and define both an ->up() and a ->down()
 * method. Migrations will be executed in alphanumeric order
 *
 * @author	Patrick Nelson, pat@catchyour.com
 * @since	2015-02-17
 */

abstract class Migration {

	abstract public function up();

	abstract public function down();

}
