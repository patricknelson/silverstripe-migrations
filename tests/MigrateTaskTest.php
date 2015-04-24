<?php

class MigrateTaskTest extends SapphireTest {

	public function testEnsureWorking() {
		// Just a generic test to make sure unit testing is working.
		$task = new MigrateTask();
		$this->assertContains("migrations", $task->getMigrationPath());
	}

}
