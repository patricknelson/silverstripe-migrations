<?php

namespace PatrickNelson\Migrations\Tests;

use Exception;
use PatrickNelson\Migrations\MigrateTask;
use PatrickNelson\Migrations\Migration;
use PatrickNelson\Migrations\MigrationException;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Core\Manifest\ClassManifest;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Core\Manifest\ModuleManifest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

class MigrateTaskTest extends SapphireTest {

    // Schema-helper tests use DDL, which implicitly commits MySQL transactions.
    protected $usesTransactions = false;

    /**
     * @var string
     */
    private $migrationDirectory;

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
		$this->migrationDirectory = tempnam(sys_get_temp_dir(), 'migrations-');
		unlink($this->migrationDirectory);
		mkdir($this->migrationDirectory);
		Config::modify()->set(MigrateTask::class, 'migration_path', $this->migrationDirectory);
	}

    protected function tearDown(): void {
        foreach(glob($this->migrationDirectory . '/*') as $file) unlink($file);
        rmdir($this->migrationDirectory);
        parent::tearDown();
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

	/*public function testTransactionRollback() {
		// TODO: Ensure changes are rolled back in case there is an exception.
	}*/

	/*public function testChangePageType() {
		// TODO: Ensure that page type can be changed properly and is set in both draft/published versions.

		// TODO: Ensure that only "Page" instances are allowed as PageType's.
	}*/

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
		$record = Migration_TestChild::create(['SourceField' => 'legacy value']);
		$record->write();
		$db = Config::inst()->get(Migration_TestParent::class, 'db');
		unset($db['SourceField']);
		Config::modify()->set(Migration_TestParent::class, 'db', $db);
		DataObject::getSchema()->reset();
		Migration::transitionField($record, 'SourceField', 'NewField', 'strtoupper');
		$this->assertSame('LEGACY VALUE', Migration::getRowValueFromTable('Migration_TestParent', 'NewField', $record->ID));
		$this->assertSame('legacy value', Migration::getRowValueFromTable('Migration_TestParent', 'SourceField', $record->ID));
	}

	public function testGetTableForField() {
	    $this->assertSame('Migration_TestParent', Migration::getTableForField(Migration_TestGrandchild::class, 'ParentField'));
	    $this->assertSame('Migration_TestChild', Migration::getTableForField(Migration_TestGrandchild::class, 'ChildField'));
	    $this->assertSame('Migration_TestGrandchild', Migration::getTableForField(Migration_TestGrandchild::class, 'Grandchild'));
	    Config::modify()->set(Migration_TestChild::class, 'db', []);
	    DataObject::getSchema()->reset();
	    $this->assertSame('Migration_TestChild', Migration::getTableForField(Migration_TestGrandchild::class, 'ChildField'));
	    $this->assertSame('', Migration::getTableForField(Migration_TestGrandchild::class, 'MissingField'));
	}

    public function testDatabaseHelpers(): void {
        $table = 'Migration_TestParent';
        $this->assertTrue(Migration::tableExists($table));
        $this->assertFalse(Migration::tableExists('NoSuchMigrationTable'));
        $this->assertSame([], Migration::getTableColumns('NoSuchMigrationTable'));
        $this->assertSame(['order'], Migration::addColumnsToTable($table, ['order' => 'VARCHAR(50) DEFAULT NULL']));
        $this->assertSame([], Migration::addColumnsToTable($table, ['order' => 'VARCHAR(50)']));
        $this->assertTrue(Migration::setRowValuesOnTable($table, ['ID' => 123, 'order' => 'inserted'], null, true));
        $this->assertTrue(Migration::setRowValuesOnTable($table, ['order' => 'updated'], 123));
        $this->assertSame(['order' => 'updated'], Migration::getRowValuesFromTable($table, ['order'], 123));
        $this->assertNull(Migration::getRowValueFromTable($table, 'NewField', 123));
        $this->assertNull(Migration::getRowValueFromTable($table, 'Missing', 123));
        $this->assertNull(Migration::getRowValueFromTable($table, 'order', 456));
        $this->assertSame(['order'], Migration::dropColumnsFromTable($table, ['order', 'Missing']));
    }

    public function testCopyTable(): void {
        Migration_TestParent::create(['ID' => 123, 'ParentField' => 'copied'])->write();
        Migration_TestChild::create(['ParentField' => 'untouched', 'ChildField' => 'old'])->write();
        $source = 'Migration_TestParent';
        $dest = 'Migration_TestChild';
        Migration::copyTable($source, $dest, ['ID', 'ParentField' => 'ChildField'], true, ['"ID"' => 123]);
        $this->assertSame('copied', Migration::getRowValueFromTable($dest, 'ChildField', 123));
        $this->assertSame(1, (int) DB::query('SELECT COUNT(*) FROM "Migration_TestChild"')->value());
        try {
            Migration::copyTable($source, $dest, ['Missing' => 'ChildField'], true);
            $this->fail('Invalid source fields must be rejected before purging.');
        } catch (MigrationException $e) {
            $this->assertStringContainsString('Missing', $e->getMessage());
        }
        $this->assertSame('copied', Migration::getRowValueFromTable($dest, 'ChildField', 123));
    }

    public function testCopyVersionedTable(): void {
        $schema = DB::get_schema();
        $schema->schemaUpdate(static function () use ($schema) {
            foreach(['MigrationCopySource', 'MigrationCopyDest'] as $table) {
                foreach(['', '_Versions'] as $suffix) $schema->requireTable($table . $suffix, ['Value' => 'Varchar']);
            }
        });
        Migration::setRowValuesOnTable('MigrationCopySource', ['ID' => 1, 'Value' => 'draft'], null, true);
        Migration::setRowValuesOnTable('MigrationCopySource_Versions', ['ID' => 2, 'Value' => 'history'], null, true);
        Migration::setRowValuesOnTable('MigrationCopyDest', ['ID' => 1, 'Value' => 'retained'], null, true);
        try {
            Migration::copyVersionedTable('MigrationCopySource', 'MigrationCopyDest', null, true);
            $this->fail('Missing tables must be rejected before purging any table.');
        } catch (MigrationException $e) {
            $this->assertStringContainsString('MigrationCopySource_Live', $e->getMessage());
        }
        $this->assertSame('retained', Migration::getRowValueFromTable('MigrationCopyDest', 'Value', 1));
        $schema->schemaUpdate(static function () use ($schema) {
            foreach(['MigrationCopySource', 'MigrationCopyDest'] as $table) {
                $schema->requireTable($table . '_Live', ['Value' => 'Varchar']);
            }
        });
        Migration::setRowValuesOnTable('MigrationCopySource_Live', ['ID' => 1, 'Value' => 'live'], null, true);
        Migration::copyVersionedTable('MigrationCopySource', 'MigrationCopyDest', null, true);
        $this->assertSame('draft', Migration::getRowValueFromTable('MigrationCopyDest', 'Value', 1));
        $this->assertSame('live', Migration::getRowValueFromTable('MigrationCopyDest_Live', 'Value', 1));
        $this->assertSame('history', Migration::getRowValueFromTable('MigrationCopyDest_Versions', 'Value', 2));
    }

    public function testConfiguredAndAutomaticPaths(): void {
        Config::modify()->set(ModuleManifest::class, 'project', null);
        $this->assertSame($this->migrationDirectory, MigrateTask::getMigrationPath());
        Config::modify()->set(MigrateTask::class, 'migration_path', 'custom/migrations');
        $this->assertSame(BASE_PATH . '/custom/migrations', MigrateTask::getMigrationPath());
        Config::modify()->set(MigrateTask::class, 'migration_path', '');
        Config::modify()->set(ModuleManifest::class, 'project', 'patricknelson/silverstripe-migrations');
        $this->assertSame(dirname(__DIR__) . '/src/migrations', MigrateTask::getMigrationPath());
    }

    /**
     * @return void
     */
    public function testMissingProject(): void {
        Config::modify()->set(MigrateTask::class, 'migration_path', '');
        Config::modify()->set(ModuleManifest::class, 'project', null);
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('No project module is configured.');
        MigrateTask::getMigrationPath();
    }

    /**
     * @return void
     */
    public function testUnresolvableProject(): void {
        Config::modify()->set(MigrateTask::class, 'migration_path', '');
        Config::modify()->set(ModuleManifest::class, 'project', 'missing-project');
        $manifest = $this->getMockBuilder(ModuleManifest::class)->disableOriginalConstructor()->onlyMethods(['getModule'])->getMock();
        $manifest->method('getModule')->with('missing-project')->willReturn(null);
        ModuleLoader::inst()->pushManifest($manifest);
        try {
            $this->expectException(MigrationException::class);
            $this->expectExceptionMessage('Cannot resolve the project module.');
            MigrateTask::getMigrationPath();
        } finally {
            ModuleLoader::inst()->popManifest();
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function generationNamespaces(): array {
        return [
            'global' => ['', 'global_example', 'Migration_GlobalExample'],
            'namespaced' => ['Example\\Migrations', 'namespaced_example', 'Example\\Migrations\\Migration_NamespacedExample'],
            'trimmed' => ['\\Example\\Migrations\\', 'trimmed_example', 'Example\\Migrations\\Migration_TrimmedExample'],
        ];
    }

    /**
     * @dataProvider generationNamespaces
     * @param string $namespace
     * @param string $baseName
     * @param string $className
     */
    public function testGenerateMigration(string $namespace, string $baseName, string $className): void {
        Config::modify()->set(MigrateTask::class, 'migration_namespace', $namespace);
        $this->task->make($baseName);
        $files = glob($this->migrationDirectory . '/*.php');
        $this->assertCount(1, $files);
        require $files[0];
        $class = new \ReflectionClass($className);
        $this->assertSame(trim($namespace, '\\'), $class->getNamespaceName());
        $this->assertStringStartsWith('Migration_', $class->getShortName());
        $this->assertSame(Migration::class, $class->getParentClass()->getName());
        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_' . $baseName . '\.php$/', basename($files[0]));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidConfiguration(): array {
        return ['path type' => ['migration_path', []], 'namespace type' => ['migration_namespace', []], 'namespace syntax' => ['migration_namespace', 'Example; die()'], 'empty namespace segment' => ['migration_namespace', 'Example\\\\Invalid']];
    }

    /**
     * @dataProvider invalidConfiguration
     * @param string $setting
     * @param mixed $value
     */
    public function testInvalidConfiguration(string $setting, $value): void {
        Config::modify()->set(MigrateTask::class, $setting, $value);
        $this->expectException(MigrationException::class);
        $this->task->make('invalid_configuration');
    }

    public function testMissingOutputDirectory(): void {
        Config::modify()->set(MigrateTask::class, 'migration_path', $this->migrationDirectory . '/missing');
        $this->expectException(MigrationException::class);
        $this->task->make('missing_directory');
    }

    public function testDiscoveryAcrossNamespaces(): void {
        require_once __DIR__ . '/fixtures/migrations/2020_01_01_000000_global.php';
        require_once __DIR__ . '/fixtures/migrations/2020_01_02_000000_namespaced.php';
        $classes = [Fixtures\Migration_Namespaced::class, 'Migration_Global'];
        $this->assertSame([
            '2020_01_01_000000_global' => 'Migration_Global',
            '2020_01_02_000000_namespaced' => Fixtures\Migration_Namespaced::class,
        ], $this->discover($classes));
    }

    /**
     * @param string[] $classes Controlled descendants without changing the real class manifest.
     * @return array
     */
    private function discover(array $classes): array {
        $manifest = $this->getMockBuilder(ClassManifest::class)->disableOriginalConstructor()->onlyMethods(['getDescendantsOf'])->getMock();
        $manifest->method('getDescendantsOf')->with(Migration::class)->willReturn($classes);
        ClassLoader::inst()->pushManifest($manifest);
        try {
            return MigrateTask::getAllMigrations();
        } finally {
            ClassLoader::inst()->popManifest();
        }
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
        'SourceField' => 'Varchar(255)',
        'NewField' => 'Varchar(255)',
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
