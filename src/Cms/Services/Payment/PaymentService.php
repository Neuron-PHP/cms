<?php

namespace Neuron\Cms\Services\Payment;

use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Resolves payment form definitions from configuration and sends the payer
 * receipt and internal notification emails.
 *
 * A single engine drives every payment type: each form declares a `purpose`
 * ( donation, membership, ... ), defaulting to donation. Form definitions are
 * read from the `payments` settings section, falling back to the legacy
 * `donations` section so existing configuration keeps working.
 *
 * This service is deliberately free of any payment-gateway types: the optional
 * neuron-php/payments package is only touched by {@see PaymentGatewayFactory}
 * and the Payments controller, so the CMS works unchanged when payments are
 * not enabled.
 *
 * @package Neuron\Cms\Services\Payment
 */
class PaymentService
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
	 * Read a top-level payments setting, falling back to the legacy donations
	 * section for backward compatibility.
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	private function setting( string $name, mixed $default = null ): mixed
	{
		$value = $this->_settings->get( 'payments', $name );

		if( $value === null )
		{
			$value = $this->_settings->get( 'donations', $name );
		}

		return $value ?? $default;
	}

	/**
	 * The form key rendered by a bare [payment] / [donation] shortcode.
	 *
	 * @return string
	 */
	public function getDefaultFormKey(): string
	{
		return (string) $this->setting( 'default_form', 'general' );
	}

	/**
	 * All configured payment forms keyed by form key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function forms(): array
	{
		$forms = $this->setting( 'forms', [] );

		return is_array( $forms ) ? $forms : [];
	}

	/**
	 * Configuration for a single payment form.
	 *
	 * @param string $key
	 * @return array|null Null when the form key is unknown
	 */
	public function getFormConfig( string $key ): ?array
	{
		$forms = $this->forms();

		if( !isset( $forms[ $key ] ) || !is_array( $forms[ $key ] ) )
		{
			return null;
		}

		return $forms[ $key ];
	}

	/**
	 * The purpose tag for a form ( donation, membership, ... ).
	 *
	 * @param string $key
	 * @return string
	 */
	public function purpose( string $key ): string
	{
		$config = $this->getFormConfig( $key );

		return (string) ( $config['purpose'] ?? 'donation' );
	}

	/**
	 * Payer field definitions for a form.
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
	 * Absolute or path success URL the gateway returns to after payment.
	 *
	 * @return string
	 */
	public function getSuccessUrl(): string
	{
		return (string) $this->setting( 'success_url', '/payments/success' );
	}

	/**
	 * Absolute or path cancel URL the gateway returns to when the payer cancels.
	 *
	 * @return string
	 */
	public function getCancelUrl(): string
	{
		return (string) $this->setting( 'cancel_url', '/payments/cancel' );
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
		$config  = $this->getFormConfig( $key );
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
	 * Extract the payer's email and name from submitted values using the same
	 * reply_to / sender_name conventions as the contact form.
	 *
	 * @param array $fieldDefs
	 * @param array $values
	 * @return array{email: ?string, name: ?string}
	 */
	public function resolvePayer( array $fieldDefs, array $values ): array
	{
		$email      = null;
		$nameParts  = [];

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

			// Collect every field flagged as part of the payer's name ( e.g. a
			// split first / last name ) in declaration order so the full name is
			// captured, not just the first field.
			if( !empty( $field['sender_name'] ) && is_scalar( $value ) && trim( (string) $value ) !== '' )
			{
				$nameParts[] = trim( (string) $value );
			}
		}

		$name = $nameParts !== [] ? implode( ' ', $nameParts ) : null;

		$email ??= ( $values['email'] ?? null );
		$name  ??= ( $values['name'] ?? null );

		return [ 'email' => $email, 'name' => $name ];
	}

	/**
	 * Email the internal recipient that a payment completed.
	 *
	 * @param string $key Form key
	 * @param array $context Template context ( see Payments controller )
	 * @return bool
	 */
	public function sendNotification( string $key, array $context ): bool
	{
		$config = $this->getFormConfig( $key );

		if( $config === null )
		{
			Log::error( "PaymentService: unknown form key '{$key}'" );
			return false;
		}

		$recipient = $config['to'] ?? null;

		if( empty( $recipient ) )
		{
			Log::warning( "PaymentService: no notification recipient configured for form '{$key}'" );
			return false;
		}

		$noun    = $this->purpose( $key ) === 'donation' ? 'Donation' : 'Payment';
		$subject = $config['notification_subject']
			?? ( 'New ' . $noun . ': ' . ( $config['label'] ?? $key ) );

		return $this->dispatch(
			(string) $recipient,
			$subject,
			'emails/payment_notification',
			$context
		);
	}

	/**
	 * Email the payer a receipt for their completed payment.
	 *
	 * @param string $toEmail Payer email
	 * @param string $key Form key
	 * @param array $context Template context ( see Payments controller )
	 * @return bool
	 */
	public function sendReceipt( string $toEmail, string $key, array $context ): bool
	{
		if( $toEmail === '' )
		{
			return false;
		}

		$config  = $this->getFormConfig( $key ) ?? [];
		$noun    = $this->purpose( $key ) === 'donation' ? 'donation' : 'payment';
		$subject = $config['receipt_subject'] ?? ( 'Thank you for your ' . $noun );

		return $this->dispatch( $toEmail, $subject, 'emails/payment_receipt', $context );
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
				Log::warning( 'PaymentService: template render failed, using plain body: ' . $templateError->getMessage() );
				$sender->body( $this->buildPlainBody( $context ), false );
			}

			return $sender->send();
		}
		catch( \Throwable $e )
		{
			Log::error( 'PaymentService: failed to send "' . $template . '": ' . $e->getMessage() );
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

		$lines[] = ( $context['formLabel'] ?? 'Payment' );
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
