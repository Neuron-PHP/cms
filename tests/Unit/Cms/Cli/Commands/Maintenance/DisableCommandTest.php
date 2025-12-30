<?php

namespace Tests\Unit\Cms\Cli\Commands\Maintenance;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\Maintenance\DisableCommand;
use PHPUnit\Framework\TestCase;

class DisableCommandTest extends TestCase
{
	private DisableCommand $command;
	private Output $mockOutput;
	private Input $mockInput;

	protected function setUp(): void
	{
		parent::setUp();

		$this->command = new DisableCommand();
		$this->mockOutput = $this->createMock( Output::class );
		$this->mockInput = $this->createMock( Input::class );

		// Use setOutput and setInput methods
		$this->command->setOutput( $this->mockOutput );
		$this->command->setInput( $this->mockInput );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:maintenance:disable', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'Disable maintenance mode for the CMS', $this->command->getDescription() );
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
					return $default;
				});

			// Expect success or warning (if maintenance not enabled)
			$this->mockOutput
				->expects( $this->atLeastOnce() )
				->method( $this->logicalOr( 'success', 'warning' ) );

			$result = $this->command->execute();

			// Should succeed (0) or gracefully handle (0 = cancelled/not enabled)
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

	public function testExecuteUserCancelsConfirmation(): void
	{
		// Create temp directory with maintenance file
		$tempDir = sys_get_temp_dir() . '/neuron_cms_test_' . uniqid();
		mkdir( $tempDir );
		mkdir( $tempDir . '/config' );
		file_put_contents( $tempDir . '/.maintenance', json_encode( ['enabled' => true] ) );

		try {
			$this->mockInput
				->method( 'getOption' )
				->willReturnCallback( function( $option, $default = null ) use ( $tempDir ) {
					if( $option === 'config' ) return $tempDir . '/config';
					if( $option === 'force' ) return false;
					return $default;
				});

			// Mock confirm to return false (user cancels)
			$command = $this->getMockBuilder( DisableCommand::class )
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
			if( file_exists( $tempDir . '/.maintenance' ) ) {
				unlink( $tempDir . '/.maintenance' );
			}
			if( is_dir( $tempDir . '/config' ) ) {
				rmdir( $tempDir . '/config' );
			}
			if( is_dir( $tempDir ) ) {
				rmdir( $tempDir );
			}
		}
	}
}
