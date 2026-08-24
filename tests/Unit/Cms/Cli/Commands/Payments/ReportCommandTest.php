<?php

namespace Tests\Unit\Cms\Cli\Commands\Payments;

use DateTimeImmutable;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\Payments\ReportCommand;
use Neuron\Cms\Services\Payment\PaymentReportService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class ReportCommandTest extends TestCase
{
	private Output $mockOutput;

	protected function setUp(): void
	{
		parent::setUp();

		$this->mockOutput = $this->createMock( Output::class );

		$source = new Memory();
		$source->set( 'payments', 'monthly_report', [
			'enabled' => true,
			'to'      => 'finance@example.com',
			'subject' => 'Monthly Payment Report'
		] );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, new SettingManager( $source ) );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, sys_get_temp_dir() );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, null );
		parent::tearDown();
	}

	private function makeCommand( ?PaymentReportService $service = null ): ReportCommand
	{
		if( $service === null )
		{
			$command = new ReportCommand();
		}
		else
		{
			$command = $this->getMockBuilder( ReportCommand::class )
				->onlyMethods( [ 'createService' ] )
				->getMock();
			$command->method( 'createService' )->willReturn( $service );
		}

		$command->setOutput( $this->mockOutput );

		return $command;
	}

	private function makeInput( ReportCommand $command, array $argv ): Input
	{
		$command->configure();
		$input = new Input( $argv );
		$input->parse( $command );

		return $input;
	}

	public function testGetName(): void
	{
		$this->assertSame( 'cms:payments:report', ( new ReportCommand() )->getName() );
	}

	public function testExecuteRejectsInvalidMonth(): void
	{
		$command = $this->makeCommand();
		$command->setInput( $this->makeInput( $command, [ '--month=2026-13' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'Invalid --month' ) );

		$this->assertSame( 1, $command->execute() );
	}

	public function testExecuteRejectsMalformedMonth(): void
	{
		$command = $this->makeCommand();
		$command->setInput( $this->makeInput( $command, [ '--month=July' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'YYYY-MM' ) );

		$this->assertSame( 1, $command->execute() );
	}

	public function testExecuteFailsWithoutRecipient(): void
	{
		$source = new Memory();
		Registry::getInstance()->set( RegistryKeys::SETTINGS, new SettingManager( $source ) );

		$command = $this->makeCommand();
		$command->setInput( $this->makeInput( $command, [] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'No recipient' ) );

		$this->assertSame( 1, $command->execute() );
	}

	public function testExecuteRejectsInvalidRecipient(): void
	{
		$command = $this->makeCommand();
		$command->setInput( $this->makeInput( $command, [ '--to=not-an-email' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'Invalid recipient' ) );

		$this->assertSame( 1, $command->execute() );
	}

	public function testExecuteFailsWithoutSettings(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		$command = $this->makeCommand();
		$command->setInput( $this->makeInput( $command, [] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'Settings not found' ) );

		$this->assertSame( 1, $command->execute() );
	}

	public function testExecuteSendsConfiguredRecipientForPreviousMonth(): void
	{
		$expectedStart = ( new DateTimeImmutable( 'first day of this month midnight' ) )->modify( '-1 month' );
		$expectedEnd   = $expectedStart->modify( 'first day of next month' );

		$service = $this->createMock( PaymentReportService::class );
		$service->expects( $this->once() )
			->method( 'build' )
			->with(
				$this->callback( fn( $start ) => $start->format( 'Y-m-d H:i:s' ) === $expectedStart->format( 'Y-m-d H:i:s' ) ),
				$this->callback( fn( $end ) => $end->format( 'Y-m-d H:i:s' ) === $expectedEnd->format( 'Y-m-d H:i:s' ) )
			)
			->willReturn( [
				'periodLabel' => $expectedStart->format( 'F Y' ),
				'count'       => 3,
				'payments'    => [],
				'totals'      => []
			] );
		$service->expects( $this->once() )
			->method( 'send' )
			->with( 'finance@example.com', $this->arrayHasKey( 'periodLabel' ), 'Monthly Payment Report' )
			->willReturn( true );

		$command = $this->makeCommand( $service );
		$command->setInput( $this->makeInput( $command, [] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'success' )
			->with( $this->stringContains( '3 payment' ) );

		$this->assertSame( 0, $command->execute() );
	}

	public function testExecuteSendsExplicitMonthAndRecipient(): void
	{
		$service = $this->createMock( PaymentReportService::class );
		$service->expects( $this->once() )
			->method( 'build' )
			->with(
				$this->callback( fn( $start ) => $start->format( 'Y-m-d' ) === '2026-07-01' ),
				$this->callback( fn( $end ) => $end->format( 'Y-m-d' ) === '2026-08-01' )
			)
			->willReturn( [
				'periodLabel' => 'July 2026',
				'count'       => 1,
				'payments'    => [],
				'totals'      => []
			] );
		$service->expects( $this->once() )
			->method( 'send' )
			->with( 'other@example.com', $this->anything(), 'Monthly Payment Report' )
			->willReturn( true );

		$command = $this->makeCommand( $service );
		$command->setInput( $this->makeInput( $command, [ '--month=2026-07', '--to=other@example.com' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'success' );

		$this->assertSame( 0, $command->execute() );
	}

	public function testExecuteReportsSendFailure(): void
	{
		$service = $this->createMock( PaymentReportService::class );
		$service->method( 'build' )->willReturn( [
			'periodLabel' => 'July 2026',
			'count'       => 0,
			'payments'    => [],
			'totals'      => []
		] );
		$service->method( 'send' )->willReturn( false );

		$command = $this->makeCommand( $service );
		$command->setInput( $this->makeInput( $command, [ '--month=2026-07' ] ) );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( $this->stringContains( 'Send failed' ) );

		$this->assertSame( 1, $command->execute() );
	}
}
