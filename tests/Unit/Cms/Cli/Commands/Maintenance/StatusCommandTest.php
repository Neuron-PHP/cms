<?php

namespace Tests\Unit\Cms\Cli\Commands\Maintenance;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\Maintenance\StatusCommand;
use PHPUnit\Framework\TestCase;

class StatusCommandTest extends TestCase
{
	private StatusCommand $command;
	private Output $mockOutput;
	private Input $mockInput;
	private string $tempDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->command = new StatusCommand();
		$this->mockOutput = $this->createMock( Output::class );
		$this->mockInput = $this->createMock( Input::class );

		// Create temp directory for testing
		$this->tempDir = sys_get_temp_dir() . '/neuron_cli_test_' . uniqid();
		mkdir( $this->tempDir );
		mkdir( $this->tempDir . '/config' );

		// Use setOutput and setInput methods
		$this->command->setOutput( $this->mockOutput );
		$this->command->setInput( $this->mockInput );
	}

	protected function tearDown(): void
	{
		// Clean up temp directory
		if( is_dir( $this->tempDir ) )
		{
			$this->removeDirectory( $this->tempDir );
		}

		parent::tearDown();
	}

	private function removeDirectory( string $dir ): void
	{
		if( !is_dir( $dir ) )
		{
			return;
		}

		$files = array_diff( scandir( $dir ), ['.', '..'] );
		foreach( $files as $file )
		{
			$path = $dir . '/' . $file;
			is_dir( $path ) ? $this->removeDirectory( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:maintenance:status', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'Show current maintenance mode status', $this->command->getDescription() );
	}

	public function testExecuteWithInvalidConfigPath(): void
	{
		$this->mockInput
			->method( 'getOption' )
			->willReturn( '/invalid/path' );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' );

		$result = $this->command->execute();

		$this->assertEquals( 1, $result );
	}

	public function testExecuteWithMaintenanceDisabled(): void
	{
		$this->mockInput
			->method( 'getOption' )
			->willReturn( $this->tempDir . '/config' );

		$this->mockInput
			->method( 'hasOption' )
			->with( 'json' )
			->willReturn( false );

		$this->mockOutput
			->expects( $this->atLeastOnce() )
			->method( 'success' )
			->with( 'Maintenance mode is DISABLED' );

		$result = $this->command->execute();

		$this->assertEquals( 0, $result );
	}

	public function testExecuteWithMaintenanceEnabled(): void
	{
		// Create maintenance file
		$maintenanceData = [
			'enabled' => true,
			'message' => 'Site under maintenance',
			'enabled_at' => date( 'Y-m-d H:i:s' ),
			'enabled_by' => 'admin'
		];
		file_put_contents(
			$this->tempDir . '/.maintenance.json',
			json_encode( $maintenanceData )
		);

		$this->mockInput
			->method( 'getOption' )
			->willReturn( $this->tempDir . '/config' );

		$this->mockInput
			->method( 'hasOption' )
			->with( 'json' )
			->willReturn( false );

		$this->mockOutput
			->expects( $this->atLeastOnce() )
			->method( 'warning' )
			->with( 'Maintenance mode is ENABLED' );

		$result = $this->command->execute();

		$this->assertEquals( 0, $result );
	}

	public function testExecuteWithJsonOutput(): void
	{
		$this->mockInput
			->method( 'getOption' )
			->willReturn( $this->tempDir . '/config' );

		$this->mockInput
			->method( 'hasOption' )
			->with( 'json' )
			->willReturn( true );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'write' )
			->with( $this->stringContains( '"enabled"' ) );

		$result = $this->command->execute();

		$this->assertEquals( 0, $result );
	}
}
