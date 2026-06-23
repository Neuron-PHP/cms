<?php

namespace Neuron\Cms\Services\Store;

use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Storefront configuration and order notification emails.
 *
 * The catalog itself lives in the database ( admin-managed ); this service only
 * reads the small `store` settings section ( currency, return URLs, notification
 * recipient ) and sends the order confirmation / internal notification emails.
 * Orders are recorded in the shared `payments` table with purpose = "order", so
 * the store reuses the entire payment + webhook lifecycle.
 *
 * Free of any payment-gateway types so the CMS works whether or not the
 * optional neuron-php/payments package is installed.
 *
 * @package Neuron\Cms\Services\Store
 */
class StoreService
{
	private SettingManager $_settings;
	private ?Sender $_sender;
	private string $_basePath;

	/**
	 * @param SettingManager $settings
	 * @param Sender|null $sender Injectable for testing
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
	 * Currency code ( lowercase ISO 4217 ), shared with the payments section.
	 *
	 * @return string
	 */
	public function getCurrency(): string
	{
		$store = $this->_settings->get( 'store', 'currency' );

		if( $store !== null && $store !== '' )
		{
			return strtolower( (string) $store );
		}

		return strtolower( (string) ( $this->_settings->get( 'payments', 'currency' ) ?? 'usd' ) );
	}

	/**
	 * Heading shown by a bare [products] grid.
	 *
	 * @return string
	 */
	public function getStoreTitle(): string
	{
		return (string) ( $this->_settings->get( 'store', 'title' ) ?? 'Shop' );
	}

	/**
	 * Success URL the gateway returns to after a completed order.
	 *
	 * @return string
	 */
	public function getSuccessUrl(): string
	{
		return (string) ( $this->_settings->get( 'store', 'success_url' ) ?? '/store/success' );
	}

	/**
	 * Cancel URL the gateway returns to when the buyer cancels.
	 *
	 * @return string
	 */
	public function getCancelUrl(): string
	{
		return (string) ( $this->_settings->get( 'store', 'cancel_url' ) ?? '/store/cancel' );
	}

	/**
	 * Internal recipient for new-order notifications.
	 *
	 * @return string|null
	 */
	public function getNotificationEmail(): ?string
	{
		$to = $this->_settings->get( 'store', 'notification_email' );

		return ( is_string( $to ) && $to !== '' ) ? $to : null;
	}

	/**
	 * Email the internal recipient that an order was placed.
	 *
	 * @param array<string, mixed> $context
	 * @return bool
	 */
	public function sendOrderNotification( array $context ): bool
	{
		$recipient = $this->getNotificationEmail();

		if( $recipient === null )
		{
			Log::warning( 'StoreService: no store.notification_email configured; skipping order notification' );

			return false;
		}

		$subject = (string) ( $this->_settings->get( 'store', 'notification_subject' ) ?? 'New order received' );

		return $this->dispatch( $recipient, $subject, 'emails/order_notification', $context );
	}

	/**
	 * Email the buyer their order receipt.
	 *
	 * @param string $toEmail
	 * @param array<string, mixed> $context
	 * @return bool
	 */
	public function sendOrderReceipt( string $toEmail, array $context ): bool
	{
		if( $toEmail === '' )
		{
			return false;
		}

		$subject = (string) ( $this->_settings->get( 'store', 'receipt_subject' ) ?? 'Your order confirmation' );

		return $this->dispatch( $toEmail, $subject, 'emails/order_receipt', $context );
	}

	/**
	 * Render and send an email, falling back to a plain body on template error.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $template
	 * @param array<string, mixed> $context
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
				Log::warning( 'StoreService: template render failed, using plain body: ' . $templateError->getMessage() );
				$sender->body( $this->buildPlainBody( $context ), false );
			}

			return $sender->send();
		}
		catch( \Throwable $e )
		{
			Log::error( 'StoreService: failed to send "' . $template . '": ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Simple plain-text fallback body from the order context.
	 *
	 * @param array<string, mixed> $context
	 * @return string
	 */
	private function buildPlainBody( array $context ): string
	{
		$lines   = [ 'Order #' . ( $context['orderId'] ?? '' ) ];
		$items   = $context['items'] ?? [];

		foreach( $items as $item )
		{
			$lines[] = ( $item['quantity'] ?? 1 ) . ' x ' . ( $item['name'] ?? '' );
		}

		$lines[] = 'Total: ' . ( $context['totalFormatted'] ?? '' );

		return implode( "\n", $lines );
	}
}
