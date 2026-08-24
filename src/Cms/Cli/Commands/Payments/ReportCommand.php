<?php

namespace Neuron\Cms\Cli\Commands\Payments;

use DateTimeImmutable;
use Neuron\Cli\Commands\Command;
use Neuron\Cms\Repositories\DatabasePaymentRepository;
use Neuron\Cms\Services\Payment\PaymentReportService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;

/**
 * CLI command for sending the monthly payment report immediately.
 */
class ReportCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:payments:report';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Email a completed-payment report for a calendar month';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'month', 'm', true, 'Report month as YYYY-MM (defaults to previous calendar month)' );
		$this->addOption( 'to', 't', true, 'Recipient email (defaults to payments.monthly_report.to)' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		if( !$settings instanceof SettingManager )
		{
			$this->output->error( 'Application not initialized: Settings not found in Registry' );
			return 1;
		}

		$period = $this->resolvePeriod();

		if( $period === null )
		{
			return 1;
		}

		$recipient = $this->resolveRecipient( $settings );

		if( $recipient === null )
		{
			return 1;
		}

		$subject = $this->reportSubject( $settings );

		$this->output->title( 'Payment Report' );
		$this->output->info( 'Period: ' . $period['start']->format( 'F Y' ) );
		$this->output->info( 'To:     ' . $recipient );

		$service = $this->createService( $settings );
		$report  = $service->build( $period['start'], $period['end'] );
		$sent    = $service->send( $recipient, $report, $subject );

		if( !$sent )
		{
			$this->output->error( 'Send failed. Check the application log for details.' );
			return 1;
		}

		$this->output->success(
			'Sent ' . $report['periodLabel'] . ' report (' . $report['count'] . ' payment(s)) to ' . $recipient . '.'
		);

		return 0;
	}

	/**
	 * @return array{start: DateTimeImmutable, end: DateTimeImmutable}|null
	 */
	private function resolvePeriod(): ?array
	{
		$month = trim( (string) $this->input->getOption( 'month', '' ) );

		if( $month === '' )
		{
			$start = ( new DateTimeImmutable( 'first day of this month midnight' ) )->modify( '-1 month' );

			return [
				'start' => $start,
				'end'   => $start->modify( 'first day of next month' )
			];
		}

		if( !preg_match( '/^(\d{4})-(\d{2})$/', $month, $matches ) )
		{
			$this->output->error( 'Invalid --month value. Use YYYY-MM, for example 2026-07.' );
			return null;
		}

		$year     = (int) $matches[1];
		$monthNum = (int) $matches[2];

		if( $monthNum < 1 || $monthNum > 12 )
		{
			$this->output->error( 'Invalid --month value. Month must be between 01 and 12.' );
			return null;
		}

		$start = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $monthNum ) );

		return [
			'start' => $start,
			'end'   => $start->modify( 'first day of next month' )
		];
	}

	/**
	 * @param SettingManager $settings
	 * @return string|null
	 */
	private function resolveRecipient( SettingManager $settings ): ?string
	{
		$to = trim( (string) $this->input->getOption( 'to', '' ) );

		if( $to === '' )
		{
			$config = $settings->get( 'payments', 'monthly_report' );
			$to     = is_array( $config ) ? trim( (string) ( $config['to'] ?? '' ) ) : '';
		}

		if( $to === '' )
		{
			$this->output->error( 'No recipient. Pass --to or set payments.monthly_report.to.' );
			return null;
		}

		if( !filter_var( $to, FILTER_VALIDATE_EMAIL ) )
		{
			$this->output->error( "Invalid recipient email address: {$to}" );
			return null;
		}

		return $to;
	}

	/**
	 * @param SettingManager $settings
	 * @return string|null
	 */
	private function reportSubject( SettingManager $settings ): ?string
	{
		$config = $settings->get( 'payments', 'monthly_report' );

		if( !is_array( $config ) )
		{
			return null;
		}

		$subject = trim( (string) ( $config['subject'] ?? '' ) );

		return $subject !== '' ? $subject : null;
	}

	/**
	 * Build the report service. Extracted for testability.
	 *
	 * @param SettingManager $settings
	 * @return PaymentReportService
	 */
	protected function createService( SettingManager $settings ): PaymentReportService
	{
		$basePath = Registry::getInstance()->get( RegistryKeys::BASE_PATH );

		return new PaymentReportService(
			new DatabasePaymentRepository( $settings ),
			$settings,
			null,
			is_string( $basePath ) ? $basePath : null
		);
	}
}
