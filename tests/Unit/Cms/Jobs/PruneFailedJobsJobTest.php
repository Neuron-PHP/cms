<?php

namespace Tests\Unit\Cms\Jobs;

use Neuron\Cms\Jobs\PruneFailedJobsJob;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PruneFailedJobsJobTest extends TestCase
{
	private string $dbPath;
	private PDO $pdo;

	protected function setUp(): void
	{
		$this->dbPath = sys_get_temp_dir() . '/neuron_jobs_failed_' . uniqid() . '.db';

		$settings = new SettingManager( new Memory() );
		$settings->set( 'database', 'adapter', 'sqlite' );
		$settings->set( 'database', 'name', $this->dbPath );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$this->pdo = new PDO( "sqlite:{$this->dbPath}" );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		unset( $this->pdo );

		if( file_exists( $this->dbPath ) )
		{
			unlink( $this->dbPath );
		}
	}

	private function createTable(): void
	{
		$this->pdo->exec(
			"CREATE TABLE failed_jobs (
				id VARCHAR(255) PRIMARY KEY,
				queue VARCHAR(255) NOT NULL,
				payload TEXT NOT NULL,
				exception TEXT NOT NULL,
				failed_at INTEGER NOT NULL
			)"
		);
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'prune_failed_jobs', ( new PruneFailedJobsJob() )->getName() );
	}

	public function testNoOpWhenTableMissing(): void
	{
		// Table intentionally not created
		$this->assertTrue( ( new PruneFailedJobsJob() )->run() );
	}

	public function testDeletesOldFailedJobsOnly(): void
	{
		$this->createTable();

		$old    = time() - ( 40 * 86400 );
		$recent = time() - ( 5 * 86400 );

		$stmt = $this->pdo->prepare( "INSERT INTO failed_jobs (id, queue, payload, exception, failed_at) VALUES (?, 'default', '{}', '', ?)" );
		$stmt->execute( [ 'old', $old ] );
		$stmt->execute( [ 'recent', $recent ] );

		$result = ( new PruneFailedJobsJob() )->run( [ 'max_age_days' => 30 ] );

		$this->assertTrue( $result );

		$ids = $this->pdo->query( "SELECT id FROM failed_jobs" )->fetchAll( PDO::FETCH_COLUMN );
		$this->assertEquals( [ 'recent' ], $ids );
	}
}
