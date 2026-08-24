<?php

namespace Neuron\Cms\Services\Payment;

use DateTimeInterface;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Builds and emails a payment report for a completed-at date range.
 *
 * @package Neuron\Cms\Services\Payment
 */
class PaymentReportService
{
	private IPaymentRepository $_repository;
	private SettingManager $_settings;
	private ?Sender $_sender;
	private string $_basePath;

	/**
	 * @param IPaymentRepository $repository
	 * @param SettingManager $settings
	 * @param Sender|null $sender Optional Sender (injectable for testing)
	 * @param string|null $basePath Base path for email template resolution
	 */
	public function __construct(
		IPaymentRepository $repository,
		SettingManager $settings,
		?Sender $sender = null,
		?string $basePath = null
	)
	{
		$this->_repository = $repository;
		$this->_settings   = $settings;
		$this->_sender     = $sender;
		$this->_basePath   = $basePath
			?? ( $settings->get( 'system', 'base_path' ) ?: getcwd() );
	}

	/**
	 * Query completed payments and aggregate totals for the half-open range
	 * [ $start, $end ).
	 *
	 * @param DateTimeInterface $start Inclusive
	 * @param DateTimeInterface $end Exclusive
	 * @return array<string, mixed>
	 */
	public function build( DateTimeInterface $start, DateTimeInterface $end ): array
	{
		$rows = $this->_repository->findCompletedBetween(
			$start->format( 'Y-m-d H:i:s' ),
			$end->format( 'Y-m-d H:i:s' )
		);

		$totals    = [];
		$byPurpose = [];
		$byForm    = [];
		$payments  = [];

		foreach( $rows as $row )
		{
			$cents    = (int) ( $row['amount_cents'] ?? 0 );
			$currency = strtolower( (string) ( $row['currency'] ?? 'usd' ) );
			$purpose  = (string) ( $row['purpose'] ?? 'donation' );
			$formKey  = (string) ( $row['form_key'] ?? '' );

			$this->addBucket( $totals, $currency, $cents );
			$this->addGroupedBucket( $byPurpose, $purpose, $currency, $cents );
			$this->addGroupedBucket( $byForm, $formKey, $currency, $cents );

			$payments[] = array_merge( $row, [
				'amountFormatted' => $this->formatAmount( $cents, $currency ),
				'frequencyLabel'  => $this->frequencyLabel( (string) ( $row['frequency'] ?? 'one_time' ) ),
				'completedLabel'  => $this->formatCompletedAt( $row['completed_at'] ?? null )
			] );
		}

		return [
			'start'       => $start,
			'end'         => $end,
			'periodLabel' => $start->format( 'F Y' ),
			'payments'    => $payments,
			'count'       => count( $payments ),
			'totals'      => $this->finalizeTotals( $totals ),
			'byPurpose'   => $this->finalizeGroups( $byPurpose ),
			'byForm'      => $this->finalizeGroups( $byForm )
		];
	}

	/**
	 * Email a previously built report.
	 *
	 * @param string $to
	 * @param array<string, mixed> $report
	 * @param string|null $subject
	 * @return bool
	 */
	public function send( string $to, array $report, ?string $subject = null ): bool
	{
		if( $to === '' || !filter_var( $to, FILTER_VALIDATE_EMAIL ) )
		{
			Log::warning( 'PaymentReportService: invalid recipient; not sending.' );
			return false;
		}

		$period  = (string) ( $report['periodLabel'] ?? '' );
		$subject = $subject ?: ( 'Monthly Payment Report' . ( $period !== '' ? ' — ' . $period : '' ) );

		$sender = $this->_sender ?? new Sender( $this->_settings, $this->_basePath );

		try
		{
			$sender->to( $to );
			$sender->subject( $subject );

			try
			{
				$sender->template( 'emails/payment_monthly_report', $report );
			}
			catch( \Throwable $templateError )
			{
				Log::warning( 'PaymentReportService: template render failed, using plain body: ' . $templateError->getMessage() );
				$sender->body( $this->buildPlainBody( $report ), false );
			}

			return $sender->send();
		}
		catch( \Throwable $exception )
		{
			Log::error( 'PaymentReportService: failed to send report: ' . $exception->getMessage() );
			return false;
		}
	}

	/**
	 * Format an amount (minor units) as a display string.
	 *
	 * @param int $cents
	 * @param string $currency
	 * @return string
	 */
	public function formatAmount( int $cents, string $currency ): string
	{
		$symbols = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
		$symbol  = $symbols[ strtolower( $currency ) ] ?? '';

		return $symbol . number_format( $cents / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
	}

	/**
	 * Human label for a recurrence value.
	 *
	 * @param string $frequency
	 * @return string
	 */
	public function frequencyLabel( string $frequency ): string
	{
		$labels = [
			'one_time'   => 'One-time',
			'monthly'    => 'Monthly',
			'quarterly'  => 'Quarterly',
			'semiannual' => 'Semi-annually',
			'annual'     => 'Annually'
		];

		return $labels[ $frequency ] ?? ucfirst( $frequency );
	}

	/**
	 * @param array<string, array{cents: int, count: int}> $totals
	 * @param string $currency
	 * @param int $cents
	 * @return void
	 */
	private function addBucket( array &$totals, string $currency, int $cents ): void
	{
		if( !isset( $totals[ $currency ] ) )
		{
			$totals[ $currency ] = [ 'cents' => 0, 'count' => 0 ];
		}

		$totals[ $currency ]['cents'] += $cents;
		$totals[ $currency ]['count']++;
	}

	/**
	 * @param array<string, array{count: int, totals: array<string, array{cents: int, count: int}>}> $groups
	 * @param string $key
	 * @param string $currency
	 * @param int $cents
	 * @return void
	 */
	private function addGroupedBucket( array &$groups, string $key, string $currency, int $cents ): void
	{
		if( !isset( $groups[ $key ] ) )
		{
			$groups[ $key ] = [ 'count' => 0, 'totals' => [] ];
		}

		$groups[ $key ]['count']++;
		$this->addBucket( $groups[ $key ]['totals'], $currency, $cents );
	}

	/**
	 * @param array<string, array{cents: int, count: int}> $totals
	 * @return array<int, array{currency: string, cents: int, count: int, formatted: string}>
	 */
	private function finalizeTotals( array $totals ): array
	{
		$result = [];

		foreach( $totals as $currency => $bucket )
		{
			$result[] = [
				'currency'  => $currency,
				'cents'     => $bucket['cents'],
				'count'     => $bucket['count'],
				'formatted' => $this->formatAmount( $bucket['cents'], $currency )
			];
		}

		return $result;
	}

	/**
	 * @param array<string, array{count: int, totals: array<string, array{cents: int, count: int}>}> $groups
	 * @return array<int, array{key: string, count: int, totals: array<int, array{currency: string, cents: int, count: int, formatted: string}>}>
	 */
	private function finalizeGroups( array $groups ): array
	{
		$result = [];

		foreach( $groups as $key => $group )
		{
			$result[] = [
				'key'    => $key,
				'count'  => $group['count'],
				'totals' => $this->finalizeTotals( $group['totals'] )
			];
		}

		return $result;
	}

	/**
	 * @param mixed $completedAt
	 * @return string
	 */
	private function formatCompletedAt( mixed $completedAt ): string
	{
		if( $completedAt === null || $completedAt === '' )
		{
			return '';
		}

		$timestamp = strtotime( (string) $completedAt );

		return $timestamp === false ? (string) $completedAt : date( 'Y-m-d', $timestamp );
	}

	/**
	 * @param array<string, mixed> $report
	 * @return string
	 */
	private function buildPlainBody( array $report ): string
	{
		$lines   = [];
		$lines[] = 'Payment report: ' . ( $report['periodLabel'] ?? '' );
		$lines[] = 'Payments: ' . (string) ( $report['count'] ?? 0 );

		foreach( $report['totals'] ?? [] as $total )
		{
			$lines[] = 'Total (' . ( $total['currency'] ?? '' ) . '): ' . ( $total['formatted'] ?? '' );
		}

		foreach( $report['payments'] ?? [] as $payment )
		{
			$lines[] = implode( ' | ', [
				$payment['completedLabel'] ?? '',
				$payment['payer_name'] ?? '',
				$payment['amountFormatted'] ?? '',
				$payment['purpose'] ?? '',
				$payment['form_key'] ?? ''
			] );
		}

		if( ( $report['count'] ?? 0 ) === 0 )
		{
			$lines[] = 'No completed payments.';
		}

		return implode( "\n", $lines );
	}
}
