<?php

namespace Tests\Unit\Cms\Jobs;

use DateTimeImmutable;
use Neuron\Cms\Jobs\MonthlyPaymentReportJob;
use Neuron\Cms\Services\Payment\PaymentReportService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MonthlyPaymentReportJobTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, sys_get_temp_dir() );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, null );
		parent::tearDown();
	}

	private function settings( array $report = [] ): SettingManager
	{
		$source = new Memory();
		$source->set( 'payments', 'monthly_report', $report );

		return new SettingManager( $source );
	}

	/**
	 * @param array<string, mixed> $reportConfig
	 * @param PaymentReportService|null $service
	 */
	private function job( array $reportConfig = [], ?PaymentReportService $service = null ): MonthlyPaymentReportJob
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $this->settings( $reportConfig ) );

		if( $service === null )
		{
			return new MonthlyPaymentReportJob();
		}

		return new class( $service ) extends MonthlyPaymentReportJob {
			private PaymentReportService $_service;

			public function __construct( PaymentReportService $service )
			{
				$this->_service = $service;
			}

			protected function createService( SettingManager $settings ): PaymentReportService
			{
				return $this->_service;
			}
		};
	}

	public function testGetName(): void
	{
		$this->assertSame( 'monthly_payment_report', ( new MonthlyPaymentReportJob() )->getName() );
	}

	public function testSkipsWhenDisabled(): void
	{
		$service = $this->createMock( PaymentReportService::class );
		$service->expects( $this->never() )->method( 'build' );
		$service->expects( $this->never() )->method( 'send' );

		$result = $this->job( [
			'enabled' => false,
			'to'      => 'finance@example.com'
		], $service )->run();

		$this->assertTrue( $result );
	}

	public function testSkipsWhenRecipientMissing(): void
	{
		$service = $this->createMock( PaymentReportService::class );
		$service->expects( $this->never() )->method( 'send' );

		$result = $this->job( [ 'enabled' => true ], $service )->run();

		$this->assertTrue( $result );
	}

	public function testSkipsWhenRecipientInvalid(): void
	{
		$service = $this->createMock( PaymentReportService::class );
		$service->expects( $this->never() )->method( 'send' );

		$result = $this->job( [
			'enabled' => true,
			'to'      => 'not-an-email'
		], $service )->run();

		$this->assertTrue( $result );
	}

	public function testSendsPreviousMonthWhenEnabled(): void
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
				'count'       => 2,
				'payments'    => [],
				'totals'      => []
			] );
		$service->expects( $this->once() )
			->method( 'send' )
			->with( 'finance@example.com', $this->arrayHasKey( 'periodLabel' ), 'Monthly Payment Report' )
			->willReturn( true );

		$result = $this->job( [
			'enabled' => true,
			'to'      => 'finance@example.com',
			'subject' => 'Monthly Payment Report'
		], $service )->run();

		$this->assertTrue( $result );
	}

	public function testArgvOverridesRecipientAndPeriod(): void
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
				'count'       => 0,
				'payments'    => [],
				'totals'      => []
			] );
		$service->expects( $this->once() )
			->method( 'send' )
			->with( 'other@example.com', $this->anything(), null )
			->willReturn( true );

		$result = $this->job( [
			'enabled' => true,
			'to'      => 'finance@example.com'
		], $service )->run( [
			'to'    => 'other@example.com',
			'year'  => 2026,
			'month' => 7
		] );

		$this->assertTrue( $result );
	}

	public function testReturnsFalseWhenSettingsMissing(): void
	{
		$this->assertFalse( ( new MonthlyPaymentReportJob() )->run() );
	}
}
