<?php

namespace Tests\Unit\Cms\Cli\Commands\Email;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\Email\TestCommand;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class TestCommandTest extends TestCase
{
	private Output $mockOutput;

	protected function setUp(): void
	{
		parent::setUp();

		$this->mockOutput = $this->createMock( Output::class );

		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'mail', 'driver', 'smtp' );
		$settings->set( 'mail', 'host', 'smtp.example.com' );
		$settings->set( 'mail', 'from_address', 'noreply@example.com' );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, new SettingManager( $settings ) );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		parent::tearDown();
	}

	private function makeCommand( ?Sender $sender = null ): TestCommand
	{
		if( $sender === null )
		{
			$command = new TestCommand();
		}
		else
		{
			$command = $this->getMockBuilder( TestCommand::class )
				->onlyMethods( [ 'createSender' ] )
				->getMock();
			$command->method( 'createSender' )->willReturn( $sender );
		}

		$command->setOutput( $this->mockOutput );

		return $command;
	}

	private function makeInput( TestCommand $command, array $argv ): Input
	{
		$command->configure();
		$input = new Input( $argv );
		$input->parse( $command );

		return $input;
	}

	private function makeSender( bool $sendResult ): Sender
	{
		$sender = $this->createMock( Sender::class );
		$sender->method( 'to' )->willReturnSelf();
		$sender->method( 'subject' )->willReturnSelf();
		$sender->method( 'body' )->willReturnSelf();
		$sender->method( 'send' )->willReturn( $sendResult );

		return $sender;
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:email:test', ( new TestCommand() )->getName() );
	}

	public function testExecuteRejectsInvalidRecipient(): void
	{
		$command = $this->makeCommand();
		$command->setInput( $this->makeInput( $command, [ 'not-an-email' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'Invalid recipient' ) );

		$this->assertEquals( 1, $command->execute() );
	}

	public function testExecuteFailsWithoutSettings(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		$command = $this->makeCommand();
		$command->setInput( $this->makeInput( $command, [ 'user@example.com' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'Settings not found' ) );

		$this->assertEquals( 1, $command->execute() );
	}

	public function testExecuteSendsEmail(): void
	{
		$sender = $this->makeSender( true );
		$command = $this->makeCommand( $sender );
		$command->setInput( $this->makeInput( $command, [ 'user@example.com' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'success' )
			->with( $this->stringContains( 'user@example.com' ) );

		$this->assertEquals( 0, $command->execute() );
	}

	public function testExecuteReportsSendFailure(): void
	{
		$sender = $this->makeSender( false );
		$command = $this->makeCommand( $sender );
		$command->setInput( $this->makeInput( $command, [ 'user@example.com' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'Send failed' ) );

		$this->assertEquals( 1, $command->execute() );
	}

	public function testExecuteWarnsWhenLogDriverActive(): void
	{
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'mail', 'driver', 'log' );
		$settings->set( 'mail', 'from_address', 'noreply@example.com' );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, new SettingManager( $settings ) );

		$sender = $this->makeSender( true );
		$command = $this->makeCommand( $sender );
		$command->setInput( $this->makeInput( $command, [ 'user@example.com' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'warning' )
			->with( $this->stringContains( 'log' ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'success' )
			->with( $this->stringContains( 'logged' ) );

		$this->assertEquals( 0, $command->execute() );
	}
}
