<?php

namespace Neuron\Cms\Services\Donation;

use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Resolves donation form definitions from configuration and sends the donor
 * receipt and internal notification emails.
 *
 * This service is deliberately free of any payment-gateway types: the optional
 * neuron-php/payments package is only touched by {@see PaymentGatewayFactory}
 * and the Donations controller, so the CMS works unchanged when donations are
 * not enabled.
 *
 * Form definitions live under the `donations` settings section.
 *
 * @package Neuron\Cms\Services\Donation
 */
class DonationService
{
	private SettingManager $_settings;
	private ?Sender $_sender;
	private string $_basePath;

	/**
	 * @param SettingManager $settings
	 * @param Sender|null $sender Optional Sender (injectable for testing)
	 * @param string|null $basePath Base path for email template resolution
	 */
	public function __construct( SettingManager $settings, ?Sender $sender = null, ?string $basePath = null )
	{
		$this->_settings = $settings;
		$this->_sender   = $sender;
		$this->_basePath = $basePath
			?? ( $settings->get( 'system', 'base_path' ) ?: getcwd() );
	}

	/**
	 * The form key rendered by a bare [donation] shortcode.
	 *
	 * @return string
	 */
	public function getDefaultFormKey(): string
	{
		return (string) ( $this->_settings->get( 'donations', 'default_form' ) ?? 'general' );
	}

	/**
	 * Configuration for a single donation form.
	 *
	 * @param string $key
	 * @return array|null Null when the form key is unknown
	 */
	public function getFormConfig( string $key ): ?array
	{
		$forms = $this->_settings->get( 'donations', 'forms' ) ?? [];

		if( !is_array( $forms ) || !isset( $forms[ $key ] ) || !is_array( $forms[ $key ] ) )
		{
			return null;
		}

		return $forms[ $key ];
	}

	/**
	 * Donor field definitions for a form.
	 *
	 * @param string $key
	 * @return array
	 */
	public function getFields( string $key ): array
	{
		$config = $this->getFormConfig( $key );

		return $config['fields'] ?? [];
	}

	/**
	 * Absolute or path success URL Stripe returns to after payment.
	 *
	 * @return string
	 */
	public function getSuccessUrl(): string
	{
		return (string) ( $this->_settings->get( 'donations', 'success_url' ) ?? '/donations/success' );
	}

	/**
	 * Absolute or path cancel URL Stripe returns to when the donor cancels.
	 *
	 * @return string
	 */
	public function getCancelUrl(): string
	{
		return (string) ( $this->_settings->get( 'donations', 'cancel_url' ) ?? '/donations/cancel' );
	}

	/**
	 * Configured currency code ( lowercase ISO 4217 ).
	 *
	 * @return string
	 */
	public function getCurrency(): string
	{
		return strtolower( (string) ( $this->_settings->get( 'payments', 'currency' ) ?? 'usd' ) );
	}

	/**
	 * The allowed recurrence values for a form, defaulting to one-time only.
	 *
	 * @param string $key
	 * @return array<int, string>
	 */
	public function allowedFrequencies( string $key ): array
	{
		$config = $this->getFormConfig( $key );
		$values = $config['frequencies'] ?? [ 'one_time' ];

		if( !is_array( $values ) || $values === [] )
		{
			return [ 'one_time' ];
		}

		return array_values( array_map( 'strval', $values ) );
	}

	/**
	 * Preset amount tiers ( major units ) for a form.
	 *
	 * @param string $key
	 * @return array<int, float>
	 */
	public function presetAmounts( string $key ): array
	{
		$config = $this->getFormConfig( $key );
		$amounts = $config['amounts'] ?? [];

		if( !is_array( $amounts ) )
		{
			return [];
		}

		return array_values( array_map( 'floatval', array_filter( $amounts, 'is_numeric' ) ) );
	}

	/**
	 * Minimum accepted amount in major units.
	 *
	 * @param string $key
	 * @return float
	 */
	public function minimumAmount( string $key ): float
	{
		$config = $this->getFormConfig( $key );

		return (float) ( $config['min_amount'] ?? 1 );
	}

	/**
	 * Whether a custom amount input should be offered.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function allowsCustomAmount( string $key ): bool
	{
		$config = $this->getFormConfig( $key );

		return (bool) ( $config['allow_custom_amount'] ?? true );
	}

	/**
	 * Extract the donor's email and name from submitted values using the same
	 * reply_to / sender_name conventions as the contact form.
	 *
	 * @param array $fieldDefs
	 * @param array $values
	 * @return array{email: ?string, name: ?string}
	 */
	public function resolveDonor( array $fieldDefs, array $values ): array
	{
		$email = null;
		$name  = null;

		foreach( $fieldDefs as $field )
		{
			$fieldName = $field['name'] ?? null;

			if( $fieldName === null )
			{
				continue;
			}

			$value = $values[ $fieldName ] ?? null;

			if( !empty( $field['reply_to'] ) && $email === null )
			{
				$email = $value;
			}

			if( !empty( $field['sender_name'] ) && $name === null )
			{
				$name = $value;
			}
		}

		$email ??= ( $values['email'] ?? null );
		$name  ??= ( $values['name'] ?? null );

		return [ 'email' => $email, 'name' => $name ];
	}

	/**
	 * Email the internal recipient that a donation completed.
	 *
	 * @param string $key Form key
	 * @param array $context Template context ( see Donations controller )
	 * @return bool
	 */
	public function sendNotification( string $key, array $context ): bool
	{
		$config = $this->getFormConfig( $key );

		if( $config === null )
		{
			Log::error( "DonationService: unknown form key '{$key}'" );
			return false;
		}

		$recipient = $config['to'] ?? null;

		if( empty( $recipient ) )
		{
			Log::warning( "DonationService: no notification recipient configured for form '{$key}'" );
			return false;
		}

		$subject = $config['notification_subject']
			?? ( 'New Donation: ' . ( $config['label'] ?? $key ) );

		return $this->dispatch(
			(string) $recipient,
			$subject,
			'emails/donation_notification',
			$context
		);
	}

	/**
	 * Email the donor a receipt for their completed donation.
	 *
	 * @param string $toEmail Donor email
	 * @param string $key Form key
	 * @param array $context Template context ( see Donations controller )
	 * @return bool
	 */
	public function sendReceipt( string $toEmail, string $key, array $context ): bool
	{
		if( $toEmail === '' )
		{
			return false;
		}

		$config  = $this->getFormConfig( $key ) ?? [];
		$subject = $config['receipt_subject'] ?? 'Thank you for your donation';

		return $this->dispatch( $toEmail, $subject, 'emails/donation_receipt', $context );
	}

	/**
	 * Render and send an email, falling back to a plain body on template error.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $template
	 * @param array $context
	 * @return bool
	 */
	private function dispatch( string $to, string $subject, string $template, array $context ): bool
	{
		$sender = $this->_sender ?? new Sender( $this->_settings, $this->_basePath );

		try
		{
			$sender->to( $to );
			$sender->subject( $subject );

			try
			{
				$sender->template( $template, $context );
			}
			catch( \Throwable $templateError )
			{
				Log::warning( 'DonationService: template render failed, using plain body: ' . $templateError->getMessage() );
				$sender->body( $this->buildPlainBody( $context ), false );
			}

			return $sender->send();
		}
		catch( \Throwable $e )
		{
			Log::error( 'DonationService: failed to send "' . $template . '": ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Simple plain-text fallback body from the template context.
	 *
	 * @param array $context
	 * @return string
	 */
	private function buildPlainBody( array $context ): string
	{
		$lines = [];

		$lines[] = ( $context['formLabel'] ?? 'Donation' );
		$lines[] = 'Amount: ' . ( $context['amountFormatted'] ?? '' )
			. ( !empty( $context['frequencyLabel'] ) ? ' (' . $context['frequencyLabel'] . ')' : '' );

		$fields = $context['fields'] ?? [];
		$values = $context['values'] ?? [];

		foreach( $fields as $field )
		{
			$name = $field['name'] ?? null;

			if( $name === null )
			{
				continue;
			}

			$label = $field['label'] ?? $name;
			$value = $values[ $name ] ?? '';

			if( !is_scalar( $value ) )
			{
				$value = json_encode( $value );
			}

			$lines[] = $label . ': ' . $value;
		}

		return implode( "\n", $lines );
	}
}
