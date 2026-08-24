<?php

namespace Neuron\Cms\Jobs;

use DateTimeImmutable;
use Neuron\Cms\Repositories\DatabasePaymentRepository;
use Neuron\Cms\Services\Payment\PaymentReportService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Throwable;

/**
 * Emails a completed-payment report for the previous calendar month.
 *
 * Enabled per site via payments.monthly_report in neuron.yaml. The scheduler
 * instantiates this job with no constructor arguments, so dependencies are
 * resolved from the Registry.
 *
 * Arguments:
 *  - to:    override recipient email
 *  - year:  report year (used with month)
 *  - month: report month 1-12 (used with year)
 *
 * @package Neuron\Cms\Jobs
 */
class MonthlyPaymentReportJob extends MaintenanceJob
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'monthly_payment_report';
	}

	/**
	 * Build and send the monthly payment report.
	 *
	 * @param array $argv
	 * @return bool True when sent or intentionally skipped.
	 */
	public function run( array $argv = [] ): mixed
	{
		$settings = $this->getSettings();

		if( $settings === null )
		{
			Log::warning( "{$this->getName()}: settings unavailable; skipping." );
			return false;
		}

		$config  = $this->reportConfig( $settings );
		$enabled = $this->isEnabled( $config['enabled'] ?? false );
		$to      = trim( (string) ( $argv['to'] ?? $config['to'] ?? '' ) );
		$subject = isset( $config['subject'] ) ? (string) $config['subject'] : null;

		if( !$enabled )
		{
			Log::info( "{$this->getName()}: disabled; skipping." );
			return true;
		}

		if( $to === '' || !filter_var( $to, FILTER_VALIDATE_EMAIL ) )
		{
			Log::warning( "{$this->getName()}: no valid recipient configured; skipping." );
			return true;
		}

		try
		{
			$period  = $this->resolvePeriod( $argv );
			$service = $this->createService( $settings );
			$report  = $service->build( $period['start'], $period['end'] );
			$sent    = $service->send( $to, $report, $subject !== '' ? $subject : null );

			if( $sent )
			{
				Log::info( "{$this->getName()}: sent {$report['periodLabel']} report ({$report['count']} payment(s)) to {$to}." );
			}
			else
			{
				Log::error( "{$this->getName()}: failed to send {$report['periodLabel']} report to {$to}." );
			}

			return $sent;
		}
		catch( Throwable $exception )
		{
			Log::error( "{$this->getName()}: failed - {$exception->getMessage()}" );
			return false;
		}
	}

	/**
	 * @param SettingManager $settings
	 * @return array<string, mixed>
	 */
	protected function reportConfig( SettingManager $settings ): array
	{
		$config = $settings->get( 'payments', 'monthly_report' );

		return is_array( $config ) ? $config : [];
	}

	/**
	 * @param mixed $value
	 * @return bool
	 */
	protected function isEnabled( mixed $value ): bool
	{
		if( is_bool( $value ) )
		{
			return $value;
		}

		if( is_int( $value ) )
		{
			return $value === 1;
		}

		if( is_string( $value ) )
		{
			return in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true );
		}

		return false;
	}

	/**
	 * Previous calendar month, or year/month from argv.
	 *
	 * @param array $argv
	 * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
	 */
	protected function resolvePeriod( array $argv ): array
	{
		if( isset( $argv['year'], $argv['month'] ) && is_numeric( $argv['year'] ) && is_numeric( $argv['month'] ) )
		{
			$year  = (int) $argv['year'];
			$month = (int) $argv['month'];

			if( $year >= 1970 && $month >= 1 && $month <= 12 )
			{
				$start = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ) );

				return [
					'start' => $start,
					'end'   => $start->modify( 'first day of next month' )
				];
			}
		}

		$start = ( new DateTimeImmutable( 'first day of this month midnight' ) )->modify( '-1 month' );

		return [
			'start' => $start,
			'end'   => $start->modify( 'first day of next month' )
		];
	}

	/**
	 * @param SettingManager $settings
	 * @return PaymentReportService
	 */
	protected function createService( SettingManager $settings ): PaymentReportService
	{
		return new PaymentReportService(
			new DatabasePaymentRepository( $settings ),
			$settings,
			null,
			$this->getBasePath()
		);
	}
}
