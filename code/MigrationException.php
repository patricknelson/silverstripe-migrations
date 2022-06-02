<?php

namespace PattricNelson\SilverStripeMigrations;

use Exception;

/**
 * Setup only to better differentiate between exceptions coming directly from migration validation and any other
 * exceptions.
 *
 * @author	Patrick Nelson, pat@catchyour.com
 * @since	2015-08-21
 */

class MigrationException extends Exception {}
