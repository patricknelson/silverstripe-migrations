<?php

namespace PatrickNelson\Migrations\Tests;

use Exception;
use PatrickNelson\Migrations\MigrateTask;
use PatrickNelson\Migrations\Migration;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

class MigrateTaskTest extends SapphireTest {

    protected static $fixture_file = 'MigrateTaskTest.yml';

    protected static $extra_dataobjects = [
        Migration_TestParent::class,
        Migration_TestChild::class,
        Migration_TestGrandchild::class,
        Migration_TestPage::class,
    ];

	/**
	 * @var MigrateTask
	 */
	protected $task;

	public function setUp(): void {
		parent::setUp();

		// Initialize task instance, ensure it's quiet.
		$this->task = new MigrateTask();
		$this->task->setSilent(true);
	}

	public function testEnsureWorking() {
		// Just a generic test to make sure unit testing is working.
		$task = new MigrateTask();
		$this->assertStringContainsString('migrations', $task->getMigrationPath());
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

	/**
     * Verify publish/unpublish while also ensuring we're not tripped up by permission issues (previously a problem in SS3)
     *
	 * @return void
	 */
	public function testPublishAndUnpublish(): void {
		Versioned::set_stage(Versioned::DRAFT);
		$page = Migration_TestPage::create(['Title' => 'Original draft']);
		$page->write();
		$titles = static function () use ($page): array {
			return [
				Versioned::get_by_stage(Migration_TestPage::class, Versioned::DRAFT)->byID($page->ID)->Title ?? null,
				Versioned::get_by_stage(Migration_TestPage::class, Versioned::LIVE)->byID($page->ID)->Title ?? null,
			];
		};
        
        // Prove access and lack thereof
		$this->logInWithPermission('ADMIN');
		$this->assertTrue($page->canPublish());
		$this->assertTrue($page->canUnpublish());
		$this->logOut();
		$this->assertFalse($page->canPublish());
		$this->assertFalse($page->canUnpublish());

		// ... the rest below proves we can still publish/unpublish despite the ->can*() capabilities on Migration_TestPage.

		// A never-published page stays on draft unless publishing is forced.
		$page->Title = 'Updated draft';
		Migration::publish($page);
		$this->assertSame(['Updated draft', null], $titles());
		Migration::publish($page, true);
		$this->assertSame(['Updated draft', 'Updated draft'], $titles());

		// Unsaved changes to a published page must reach both stages.
		$page->Title = 'Updated published page';
		Migration::publish($page);
		$this->assertSame(['Updated published page', 'Updated published page'], $titles());

		// An existing unpublished draft must not replace the live version.
		$page->Title = 'Pending draft';
		$page->write();
		$page->Title = 'Updated pending draft';
		Migration::publish($page);
		$this->assertSame(['Updated pending draft', 'Updated published page'], $titles());
		Migration::unpublish($page);
		$this->assertSame(['Updated pending draft', null], $titles());
		$page->Title = 'Updated after unpublishing';
		Migration::publish($page);
		$this->assertSame(['Updated after unpublishing', null], $titles());
		$this->assertNull(Security::getCurrentUser());
	}

	public function testTransitionField() {
		// TODO: Make sure values are retained from old -> new.

		// TODO: Make sure values are properly transformed from old -> new
	}

	/**
	 * TODO: SSv4: Expect mapped table names instead of namespaced class names.
	 */
	public function testGetTableForField() {
	    $this->assertEquals(Migration_TestParent::class, Migration::getTableForField(Migration_TestGrandchild::class, 'ParentField'));
	    $this->assertEquals(Migration_TestChild::class, Migration::getTableForField(Migration_TestGrandchild::class, 'ChildField'));
	    $this->assertEquals(Migration_TestGrandchild::class, Migration::getTableForField(Migration_TestGrandchild::class, 'Grandchild'));
	}

}

class Migration_UnitTestOnly extends Migration implements TestOnly {

	public static $throwException = false;

	public function up() {
		// TODO: Do stuff here.

		static::exceptionator();
	}

	public function down() {
		// TODO: Reverse stuff here.

		static::exceptionator();
	}

	public function isObsolete(): bool {
		return false;
	}

	protected static function exceptionator() {
		if (static::$throwException) throw new Exception('Test exception.');
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

/**
 * Require administrator permissions while allowing migrations to use the underlying lifecycle actions directly.
 */
class Migration_TestPage extends SiteTree implements TestOnly {

    private static $table_name = 'Migration_TestPage';

    /**
     * @param Member|null $member
     * @return bool
     */
    public function canPublish($member = null): bool {
        return (bool) Permission::checkMember($member, 'ADMIN');
    }

    /**
     * @param Member|null $member
     * @return bool
     */
    public function canUnpublish($member = null): bool {
        return $this->canPublish($member);
    }
}
