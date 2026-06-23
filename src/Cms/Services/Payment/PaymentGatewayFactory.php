<?php

namespace Neuron\Cms\Services\Payment;

use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Builds a payment gateway from the `payments` settings section.
 *
 * The optional neuron-php/payments package is referenced only by fully
 * qualified name behind a class_exists() guard, so this factory can be
 * autowired safely whether or not the package is installed. When payments are
 * not enabled (package missing or no secret key configured) it returns null
 * and the payment routes degrade gracefully.
 *
 * @package Neuron\Cms\Services\Payment
 */
class PaymentGatewayFactory
{
	private SettingManager $_settings;

	public function __construct( SettingManager $settings )
	{
		$this->_settings = $settings;
	}

	/**
	 * Whether payment processing is available and configured.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		if( !class_exists( '\\Neuron\\Payments\\GatewayFactory' ) )
		{
			return false;
		}

		return (string) ( $this->_settings->get( 'payments', 'secret_key' ) ?? '' ) !== '';
	}

	/**
	 * The configured payment section as a plain array.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array
	{
		$config = $this->_settings->getSection( 'payments' );

		return is_array( $config ) ? $config : [];
	}

	/**
	 * Build the configured gateway, or null when payments are not enabled.
	 *
	 * @return \Neuron\Payments\IPaymentGateway|null
	 */
	public function create(): ?\Neuron\Payments\IPaymentGateway
	{
		if( !$this->isEnabled() )
		{
			return null;
		}

		try
		{
			return \Neuron\Payments\GatewayFactory::create( $this->config() );
		}
		catch( \Throwable $e )
		{
			Log::error( 'PaymentGatewayFactory: unable to build gateway: ' . $e->getMessage() );
			return null;
		}
	}
}
