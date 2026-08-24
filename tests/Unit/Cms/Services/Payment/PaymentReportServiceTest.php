<?php

namespace Tests\Unit\Cms\Services\Payment;

use DateTimeImmutable;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Cms\Services\Payment\PaymentReportService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use PHPUnit\Framework\TestCase;

class PaymentReportServiceTest extends TestCase
{
	private function service( IPaymentRepository $repository, ?Sender $sender = null ): PaymentReportService
	{
		$settings = new SettingManager( new Memory( [
			'system' => [ 'base_path' => sys_get_temp_dir() ]
		] ) );

		return new PaymentReportService( $repository, $settings, $sender, sys_get_temp_dir() );
	}

	private function payment( array $overrides = [] ): array
	{
		return array_merge( [
			'id'            => 1,
			'purpose'       => 'donation',
			'form_key'      => 'general',
			'amount_cents'  => 5000,
			'currency'      => 'usd',
			'frequency'     => 'one_time',
			'status'        => 'completed',
			'payer_name'    => 'Alice',
			'payer_email'   => 'alice@example.com',
			'completed_at'  => '2026-07-15 10:00:00'
		], $overrides );
	}

	public function testBuildAggregatesTotalsAndGroups(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$repository->expects( $this->once() )
			->method( 'findCompletedBetween' )
			->with( '2026-07-01 00:00:00', '2026-08-01 00:00:00' )
			->willReturn( [
				$this->payment(),
				$this->payment( [
					'id'           => 2,
					'purpose'      => 'order',
					'form_key'     => 'store',
					'amount_cents' => 2500,
					'frequency'    => 'monthly',
					'payer_name'   => 'Bob',
					'completed_at' => '2026-07-20 09:00:00'
				] )
			] );

		$report = $this->service( $repository )->build(
			new DateTimeImmutable( '2026-07-01 00:00:00' ),
			new DateTimeImmutable( '2026-08-01 00:00:00' )
		);

		$this->assertSame( 'July 2026', $report['periodLabel'] );
		$this->assertSame( 2, $report['count'] );
		$this->assertSame( '$75.00', $report['totals'][0]['formatted'] );
		$this->assertSame( 7500, $report['totals'][0]['cents'] );
		$this->assertCount( 2, $report['byPurpose'] );
		$this->assertSame( 'donation', $report['byPurpose'][0]['key'] );
		$this->assertSame( 1, $report['byPurpose'][0]['count'] );
		$this->assertSame( 'order', $report['byPurpose'][1]['key'] );
		$this->assertSame( 'general', $report['byForm'][0]['key'] );
		$this->assertSame( 'store', $report['byForm'][1]['key'] );
		$this->assertSame( '$50.00', $report['payments'][0]['amountFormatted'] );
		$this->assertSame( 'One-time', $report['payments'][0]['frequencyLabel'] );
		$this->assertSame( '2026-07-15', $report['payments'][0]['completedLabel'] );
		$this->assertSame( 'Monthly', $report['payments'][1]['frequencyLabel'] );
	}

	public function testBuildGroupsTotalsByCurrency(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$repository->method( 'findCompletedBetween' )->willReturn( [
			$this->payment( [ 'currency' => 'usd', 'amount_cents' => 1000 ] ),
			$this->payment( [ 'id' => 2, 'currency' => 'eur', 'amount_cents' => 2000 ] )
		] );

		$report = $this->service( $repository )->build(
			new DateTimeImmutable( '2026-07-01 00:00:00' ),
			new DateTimeImmutable( '2026-08-01 00:00:00' )
		);

		$this->assertCount( 2, $report['totals'] );
		$this->assertSame( 'usd', $report['totals'][0]['currency'] );
		$this->assertSame( '$10.00', $report['totals'][0]['formatted'] );
		$this->assertSame( 'eur', $report['totals'][1]['currency'] );
		$this->assertSame( '€20.00', $report['totals'][1]['formatted'] );
	}

	public function testBuildEmptyPeriod(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$repository->method( 'findCompletedBetween' )->willReturn( [] );

		$report = $this->service( $repository )->build(
			new DateTimeImmutable( '2026-07-01 00:00:00' ),
			new DateTimeImmutable( '2026-08-01 00:00:00' )
		);

		$this->assertSame( 0, $report['count'] );
		$this->assertSame( [], $report['payments'] );
		$this->assertSame( [], $report['totals'] );
		$this->assertSame( [], $report['byPurpose'] );
		$this->assertSame( [], $report['byForm'] );
		$this->assertSame( 'July 2026', $report['periodLabel'] );
	}

	public function testSendRejectsInvalidRecipient(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$sender     = $this->createMock( Sender::class );
		$sender->expects( $this->never() )->method( 'send' );

		$this->assertFalse(
			$this->service( $repository, $sender )->send( 'not-an-email', [
				'periodLabel' => 'July 2026',
				'count'       => 0,
				'payments'    => [],
				'totals'      => []
			] )
		);
	}

	public function testSendUsesTemplateAndCustomSubject(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$sender     = $this->createMock( Sender::class );
		$sender->expects( $this->once() )->method( 'to' )->with( 'finance@example.com' )->willReturnSelf();
		$sender->expects( $this->once() )->method( 'subject' )->with( 'Custom Subject' )->willReturnSelf();
		$sender->expects( $this->once() )->method( 'template' )->with(
			'emails/payment_monthly_report',
			$this->arrayHasKey( 'periodLabel' )
		)->willReturnSelf();
		$sender->expects( $this->once() )->method( 'send' )->willReturn( true );

		$result = $this->service( $repository, $sender )->send(
			'finance@example.com',
			[ 'periodLabel' => 'July 2026', 'count' => 0, 'payments' => [], 'totals' => [] ],
			'Custom Subject'
		);

		$this->assertTrue( $result );
	}

	public function testSendFallsBackToPlainBodyWhenTemplateMissing(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$sender     = $this->createMock( Sender::class );
		$sender->method( 'to' )->willReturnSelf();
		$sender->method( 'subject' )->willReturnSelf();
		$sender->method( 'template' )->willThrowException( new \RuntimeException( 'missing' ) );
		$sender->expects( $this->once() )
			->method( 'body' )
			->with( $this->stringContains( 'No completed payments.' ), false )
			->willReturnSelf();
		$sender->method( 'send' )->willReturn( true );

		$this->assertTrue(
			$this->service( $repository, $sender )->send( 'finance@example.com', [
				'periodLabel' => 'July 2026',
				'count'       => 0,
				'payments'    => [],
				'totals'      => []
			] )
		);
	}

	public function testFormatAmountAndFrequencyLabel(): void
	{
		$service = $this->service( $this->createMock( IPaymentRepository::class ) );

		$this->assertSame( '$12.50', $service->formatAmount( 1250, 'usd' ) );
		$this->assertSame( '10.00 JPY', $service->formatAmount( 1000, 'jpy' ) );
		$this->assertSame( 'One-time', $service->frequencyLabel( 'one_time' ) );
		$this->assertSame( 'Weekly', $service->frequencyLabel( 'weekly' ) );
	}
}
