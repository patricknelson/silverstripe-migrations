<?php

namespace PatrickNelson\Migrations\Tests\Fixtures;

use PatrickNelson\Migrations\Migration;

/**
 * A migration outside the default global namespace.
 *
 * @author   Patrick Nelson, pat@catchyour.com
 * @since    2026-11-02
 */
class Migration_Namespaced extends Migration {

    /**
     * @return void
     */
    public function up() {
    }

    /**
     * @return void
     */
    public function down() {
    }
}
