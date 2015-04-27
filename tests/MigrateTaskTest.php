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

}


class Migration_UnitTestOnly implements TestOnly {

	public static $throwException = false;

	public function up() {
		// TODO: Do stuff here.

		static::exceptionator();
	}

	public function down() {
		// TODO: Reverse stuff here.

		static::exceptionator();
	}

	protected static function exceptionator() {
		if (static::$throwException) throw new Exception("Test exception.");
	}

}
