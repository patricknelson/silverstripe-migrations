<?php

class MigrateTaskTest extends SapphireTest {

	protected static $fixture_file = 'MigrateTaskTest.yml';

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
