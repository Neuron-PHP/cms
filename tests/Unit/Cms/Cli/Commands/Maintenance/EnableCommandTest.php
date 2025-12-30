<?php

namespace Tests\Unit\Cms\Cli\Commands\Maintenance;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\Maintenance\EnableCommand;
use PHPUnit\Framework\TestCase;

class EnableCommandTest extends TestCase
{
	private EnableCommand $command;
	private Output $mockOutput;
	private Input $mockInput;

	protected function setUp(): void
	{
		parent::setUp();

		$this->command = new EnableCommand();
		$this->mockOutput = $this->createMock( Output::class );
		$this->mockInput = $this->createMock( Input::class );

		// Use setOutput and setInput methods
		$this->command->setOutput( $this->mockOutput );
		$this->command->setInput( $this->mockInput );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:maintenance:enable', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'Enable maintenance mode for the CMS', $this->command->getDescription() );
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

	public function testExecuteWithValidTempDirectoryAndForce(): void
	{
		// Create temp directory
		$tempDir = sys_get_temp_dir() . '/neuron_cms_test_' . uniqid();
		mkdir( $tempDir );
		mkdir( $tempDir . '/config' );

		try {
			$this->mockInput
				->method( 'getOption' )
				->willReturnCallback( function( $option, $default = null ) use ( $tempDir ) {
					if( $option === 'config' ) return $tempDir . '/config';
					if( $option === 'force' ) return true;
					if( $option === 'message' ) return 'Test maintenance';
					return $default;
				});

			// Expect success message
			$this->mockOutput
				->expects( $this->atLeastOnce() )
				->method( 'success' );

			$result = $this->command->execute();

			// Should succeed or fail gracefully (permissions dependent)
			$this->assertContains( $result, [0, 1] );
		} finally {
			// Cleanup
			if( file_exists( $tempDir . '/.maintenance' ) ) {
				unlink( $tempDir . '/.maintenance' );
			}
			if( file_exists( $tempDir . '/.maintenance.json' ) ) {
				unlink( $tempDir . '/.maintenance.json' );
			}
			if( is_dir( $tempDir . '/config' ) ) {
				// Check for any files in config directory
				$files = glob( $tempDir . '/config/*' );
				foreach( $files as $file ) {
					if( is_file( $file ) ) {
						unlink( $file );
					}
				}
				rmdir( $tempDir . '/config' );
			}
			if( is_dir( $tempDir ) ) {
				// Delete any remaining files
				$files = glob( $tempDir . '/*' );
				foreach( $files as $file ) {
					if( is_file( $file ) ) {
						unlink( $file );
					}
				}
				rmdir( $tempDir );
			}
		}
	}

	public function testExecuteUserCancelsConfirmation(): void
	{
		// Create temp directory
		$tempDir = sys_get_temp_dir() . '/neuron_cms_test_' . uniqid();
		mkdir( $tempDir );
		mkdir( $tempDir . '/config' );

		try {
			$this->mockInput
				->method( 'getOption' )
				->willReturnCallback( function( $option, $default = null ) use ( $tempDir ) {
					if( $option === 'config' ) return $tempDir . '/config';
					if( $option === 'force' ) return false;
					if( $option === 'message' ) return 'Test maintenance';
					return $default;
				});

			// Mock confirm to return false (user cancels)
			$command = $this->getMockBuilder( EnableCommand::class )
				->onlyMethods( ['confirm'] )
				->getMock();
			$command->method( 'confirm' )->willReturn( false );
			$command->setOutput( $this->mockOutput );
			$command->setInput( $this->mockInput );

			$result = $command->execute();

			// Should return 0 when user cancels
			$this->assertEquals( 0, $result );
		} finally {
			// Cleanup
			if( is_dir( $tempDir . '/config' ) ) {
				rmdir( $tempDir . '/config' );
			}
			if( is_dir( $tempDir ) ) {
				rmdir( $tempDir );
			}
		}
	}
}
