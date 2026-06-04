<?php

namespace Tests\Unit\Cms\Jobs;

use Neuron\Cms\Jobs\CleanupLogsJob;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CleanupLogsJobTest extends TestCase
{
	private string $basePath;
	private string $logDir;

	protected function setUp(): void
	{
		$this->basePath = sys_get_temp_dir() . '/neuron_jobs_logs_' . uniqid();
		$this->logDir   = $this->basePath . '/storage/logs';
		mkdir( $this->logDir, 0777, true );

		$settings = new SettingManager( new Memory() );
		$settings->set( 'logging', 'file', 'storage/logs/app.log' );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, $this->basePath );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, null );

		foreach( glob( $this->logDir . '/*' ) ?: [] as $f )
		{
			unlink( $f );
		}
		@rmdir( $this->logDir );
		@rmdir( $this->basePath . '/storage' );
		@rmdir( $this->basePath );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cleanup_logs', ( new CleanupLogsJob() )->getName() );
	}

	public function testDeletesOldLogFilesOnly(): void
	{
		$oldFile    = $this->logDir . '/app-2020-01-01.log';
		$recentFile = $this->logDir . '/app.log';

		file_put_contents( $oldFile, 'old' );
		file_put_contents( $recentFile, 'recent' );

		// Make the old file 40 days old
		touch( $oldFile, time() - ( 40 * 86400 ) );

		$result = ( new CleanupLogsJob() )->run( [ 'max_age_days' => 30 ] );

		$this->assertTrue( $result );
		$this->assertFileDoesNotExist( $oldFile );
		$this->assertFileExists( $recentFile );
	}
}
