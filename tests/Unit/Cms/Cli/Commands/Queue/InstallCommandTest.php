<?php

namespace Tests\Unit\Cms\Cli\Commands\Queue;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\Queue\InstallCommand;
use PHPUnit\Framework\TestCase;

class InstallCommandTest extends TestCase
{
	private string $_tempDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->_tempDir = sys_get_temp_dir() . '/neuron_queue_install_' . uniqid();
		mkdir( $this->_tempDir . '/db/migrate', 0777, true );
	}

	protected function tearDown(): void
	{
		$this->removeDir( $this->_tempDir );
		parent::tearDown();
	}

	public function testGetName(): void
	{
		$this->assertSame( 'queue:install', ( new InstallCommand() )->getName() );
	}

	public function testGetDescription(): void
	{
		$description = ( new InstallCommand() )->getDescription();
		$this->assertStringContainsString( 'queue', strtolower( $description ) );
	}

	public function testEnsureMigrationCopiesCmsMigration(): void
	{
		$command = $this->makeCommand();
		$path = $command->ensureMigration();

		$this->assertNotNull( $path );
		$this->assertFileExists( $path );
		$this->assertStringContainsString( "'null' => false", (string) file_get_contents( $path ) );
		$this->assertStringContainsString( 'jobs', (string) file_get_contents( $path ) );
		$this->assertStringContainsString( 'failed_jobs', (string) file_get_contents( $path ) );
	}

	public function testEnsureMigrationPatchesJobsStub(): void
	{
		$stub = $this->_tempDir . '/db/migrate/20260910150000_create_queue_tables.php';
		file_put_contents(
			$stub,
			"<?php\n\$jobs->addColumn( 'id', 'string', [ 'limit' => 255 ] )\n"
		);

		$command = $this->makeCommand();
		$path = $command->ensureMigration();

		$this->assertSame( $stub, $path );
		$contents = (string) file_get_contents( $stub );
		$this->assertStringContainsString( "[ 'limit' => 255, 'null' => false ]", $contents );
		$this->assertStringNotContainsString( "addColumn( 'id', 'string', [ 'limit' => 255 ] )", $contents );
	}

	public function testIsMysqlSafeRequiresNotNullPrimaryKey(): void
	{
		$command = $this->makeCommand();
		$unsafe = $this->_tempDir . '/unsafe.php';
		file_put_contents( $unsafe, "addColumn( 'id', 'string', [ 'limit' => 255 ] )" );

		$this->assertFalse( $command->isMysqlSafe( $unsafe ) );

		$safe = $this->_tempDir . '/safe.php';
		file_put_contents( $safe, "addColumn( 'id', 'string', [ 'limit' => 255, 'null' => false ] )" );

		$this->assertTrue( $command->isMysqlSafe( $safe ) );
	}

	public function testCmsMigrationIsMysqlSafe(): void
	{
		$migration = dirname( __DIR__, 6 ) . '/resources/database/migrate/20260910180000_create_queue_tables.php';
		$this->assertFileExists( $migration );

		$contents = (string) file_get_contents( $migration );
		$this->assertStringContainsString( "addColumn( 'id', 'string', [ 'limit' => 255, 'null' => false ] )", $contents );
		$this->assertStringContainsString( 'hasTable', $contents );
	}

	private function makeCommand(): InstallCommand
	{
		$component = dirname( __DIR__, 6 );
		$command = new InstallCommand( $this->_tempDir, $component );
		$output = $this->createMock( Output::class );
		$command->setOutput( $output );
		$command->setInput( new Input( [ '--skip-migrate' ] ) );
		$command->configure();

		return $command;
	}

	private function removeDir( string $dir ): void
	{
		if( !is_dir( $dir ) )
		{
			return;
		}

		$items = scandir( $dir );

		if( $items === false )
		{
			return;
		}

		foreach( $items as $item )
		{
			if( $item === '.' || $item === '..' )
			{
				continue;
			}

			$path = $dir . '/' . $item;

			if( is_dir( $path ) )
			{
				$this->removeDir( $path );
			}
			else
			{
				unlink( $path );
			}
		}

		rmdir( $dir );
	}
}
