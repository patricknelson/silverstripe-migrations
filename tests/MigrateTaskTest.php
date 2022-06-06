<?php

namespace PattricNelson\SilverStripeMigrations\Tests;

use Exception;

use PattricNelson\SilverStripeMigrations\MigrateTask;
use PattricNelson\SilverStripeMigrations\Migration;
use PattricNelson\SilverStripeMigrations\MigrationInterface;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class MigrateTaskTest extends SapphireTest {

    protected static $fixture_file = 'MigrateTaskTest.yml';

    protected static $extra_dataobjects = [
        Migration_TestParent::class,
        Migration_TestChild::class,
        Migration_TestGrandchild::class,
    ];

	/**
	 * @var MigrateTask
	 */
	protected $task;

	public function setUp() {
		parent::setUp();

		// Initialize task instance, ensure it's quiet.
		$this->task = new MigrateTask();
		$this->task->setSilent(true);
	}

	public function testEnsureWorking() {
		// Just a generic test to make sure unit testing is working.
		$task = new MigrateTask();
		$this->assertContains("migrations", $task->getMigrationPath());
	}

	public function testLatestBatch() {
		// Ensure that there are 2 migrations in the latest batch and 1 before that and that they both will run in the correct order.
		$this->assertEquals(2, MigrateTask::getLatestBatch());
		$this->task->down();
		$this->assertEquals(1, MigrateTask::getLatestBatch());
	}

	public function testTransactionRollback() {
		// TODO: Ensure changes are rolled back in case there is an exception.
	}

	public function testChangePageType() {
		// TODO: Ensure that page type can be changed properly and is set in both draft/published versions.

		// TODO: Ensure that only "Page" instances are allowed as PageType's.
	}

	public function testPublish() {
		// TODO: Ensure that changes performed on published pages are retained.

		// TODO: Ensure that changes performed on unpublished pages (with NO published version) are still at least retained in draft.

		// TODO: Ensure that changes performed on unpublished pages (WITH published version) are still retained in draft with published version unchanged.
	}

	public function testTransitionField() {
		// TODO: Make sure values are retained from old -> new.

		// TODO: Make sure values are properly transformed from old -> new
	}

	public function testGetTableForField() {
	    $this->assertEquals(Migration_TestParent::class,     Migration::getTableForField(Migration_TestGrandchild::class, 'ParentField'));
	    $this->assertEquals(Migration_TestChild::class,      Migration::getTableForField(Migration_TestGrandchild::class, 'ChildField'));
	    $this->assertEquals(Migration_TestGrandchild::class, Migration::getTableForField(Migration_TestGrandchild::class, 'Grandchild'));
	}

}

class Migration_UnitTestOnly extends Migration implements TestOnly, MigrationInterface {

	public static $throwException = false;

	public function up() {
		// TODO: Do stuff here.

		static::exceptionator();
	}

	public function down() {
		// TODO: Reverse stuff here.

		static::exceptionator();
	}

	public function isObsolete() {
		return false;
	}

	protected static function exceptionator() {
		if (static::$throwException) throw new Exception("Test exception.");
	}

}

class Migration_TestParent extends DataObject implements TestOnly {

    private static $db = [
        'ParentField' => 'Varchar(255)',
    ];
    private static $table_name = 'Migration_TestParent';
}

class Migration_TestChild extends Migration_TestParent implements TestOnly {

    private static $db = [
        'ChildField' => 'Varchar(255)',
    ];
    private static $table_name = 'Migration_TestChild';
}

class Migration_TestGrandchild extends Migration_TestChild implements TestOnly {

    private static $db = [
        'Grandchild' => 'Varchar(255)',
    ];
    private static $table_name = 'Migration_TestGrandchild';
}
