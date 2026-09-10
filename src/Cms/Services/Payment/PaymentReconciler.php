<?php

namespace Neuron\Cms\Services\Payment;

use Neuron\Application\CrossCutting\Event;
use Neuron\Cms\Events\PaymentCompletedEvent;
use Neuron\Cms\Repositories\DatabaseSubscriptionRepository;
use Neuron\Cms\Repositories\IOrderItemRepository;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Repositories\ISubscriptionRepository;
use Neuron\Cms\Services\Store\StoreService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Completes a pending payment from a verified gateway result.
 *
 * Used by the signed webhook ( source of truth ) and by later reconciliation
 * when the webhook is delayed or missing: the thank-you page, admin "Sync
 * with Stripe", and `cms:payments:reconcile`. Completion is idempotent.
 *
 * Gateway types from the optional neuron-php/payments package are referenced
 * only inside method bodies so this class loads when payments are disabled.
 *
 * @package Neuron\Cms\Services\Payment
 */
class PaymentReconciler
{
	public const COMPLETED         = 'completed';
	public const ALREADY_COMPLETED = 'already_completed';
	public const UNPAID            = 'unpaid';
	public const CANCELED          = 'canceled';
	public const NO_SESSION        = 'no_session';
	public const UNAVAILABLE       = 'unavailable';
	public const ERROR             = 'error';

	private IPaymentRepository $_payments;
	private PaymentGatewayFactory $_gatewayFactory;
	private PaymentService $_paymentService;
	private SettingManager $_settings;
	private ?ISubscriptionRepository $_subscriptions;
	private ?IOrderItemRepository $_orderItems;
	private ?StoreService $_storeService;

	/**
	 * @param IPaymentRepository $payments
	 * @param PaymentGatewayFactory $gatewayFactory
	 * @param PaymentService $paymentService
	 * @param SettingManager $settings
	 * @param ISubscriptionRepository|null $subscriptions
	 * @param IOrderItemRepository|null $orderItems
	 * @param StoreService|null $storeService
	 */
	public function __construct(
		IPaymentRepository    $payments,
		PaymentGatewayFactory $gatewayFactory,
		PaymentService        $paymentService,
		SettingManager        $settings,
		?ISubscriptionRepository $subscriptions = null,
		?IOrderItemRepository    $orderItems = null,
		?StoreService            $storeService = null
	)
	{
		$this->_payments       = $payments;
		$this->_gatewayFactory = $gatewayFactory;
		$this->_paymentService = $paymentService;
		$this->_settings       = $settings;
		$this->_subscriptions  = $subscriptions;
		$this->_orderItems     = $orderItems;
		$this->_storeService   = $storeService;
	}

	/**
	 * Mark a pending payment completed from gateway identifiers, then notify.
	 *
	 * @param array<string, mixed> $payment
	 * @param string|null $paymentIntentId
	 * @param string|null $subscriptionId
	 * @param int|null $amountTotal
	 * @param object|null $gateway
	 * @return void
	 */
	public function finalize(
		array $payment,
		?string $paymentIntentId,
		?string $subscriptionId,
		?int $amountTotal,
		?object $gateway = null
	): void
	{
		if( ( $payment['status'] ?? '' ) === 'completed' )
		{
			return;
		}

		$paymentId = (int) ( $payment['id'] ?? 0 );
		$type      = $subscriptionId !== null && $subscriptionId !== '' ? 'recurring' : 'one_time';

		$this->_payments->markCompleted( $paymentId, [
			'payment_intent_id' => $paymentIntentId,
			'subscription_id'   => $subscriptionId,
			'amount_cents'      => $amountTotal,
			'type'              => $type
		] );

		if( $subscriptionId !== null && $subscriptionId !== '' && $gateway !== null )
		{
			$this->openSubscription( $payment, $subscriptionId, $gateway );
		}

		$completed = $this->_payments->findById( $paymentId ) ?? $payment;

		Event::emit( new PaymentCompletedEvent( $completed, false ) );

		$this->notify( $completed );
	}

	/**
	 * Ask the gateway whether a pending payment was paid ( or expired ).
	 *
	 * @param array<string, mixed> $payment
	 * @return string One of the result constants
	 */
	public function sync( array $payment ): string
	{
		if( ( $payment['status'] ?? '' ) === 'completed' )
		{
			return self::ALREADY_COMPLETED;
		}

		$sessionId = (string) ( $payment['session_id'] ?? '' );

		if( $sessionId === '' )
		{
			return self::NO_SESSION;
		}

		$gateway = $this->_gatewayFactory->create();

		if( $gateway === null )
		{
			return self::UNAVAILABLE;
		}

		try
		{
			$session = $this->retrieveSession( $gateway, $sessionId );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Payment reconcile: unable to retrieve checkout session: ' . $e->getMessage() );

			return self::ERROR;
		}

		if( $session === null )
		{
			return self::UNAVAILABLE;
		}

		if( $session->isPaid() )
		{
			$this->finalize(
				$payment,
				$session->paymentIntentId,
				$session->subscriptionId,
				$session->amountTotal,
				$gateway
			);

			return self::COMPLETED;
		}

		if( $session->isExpired() )
		{
			$this->_payments->updateStatus( (int) $payment['id'], 'canceled' );

			return self::CANCELED;
		}

		return self::UNPAID;
	}

	/**
	 * Load a checkout session from the gateway, or from the Stripe SDK when
	 * the installed payments package does not yet expose session lookup.
	 *
	 * @param object $gateway
	 * @param string $sessionId
	 * @return RetrievedCheckout|null
	 */
	private function retrieveSession( object $gateway, string $sessionId ): ?RetrievedCheckout
	{
		if( method_exists( $gateway, 'getCheckoutSession' ) )
		{
			return RetrievedCheckout::from( $gateway->getCheckoutSession( $sessionId ) );
		}

		$secret = (string) ( $this->_settings->get( 'payments', 'secret_key' ) ?? '' );

		if( $secret === '' || !class_exists( '\\Stripe\\StripeClient' ) )
		{
			return null;
		}

		$client  = new \Stripe\StripeClient( $secret );
		$session = $client->checkout->sessions->retrieve( $sessionId );

		return RetrievedCheckout::from( $session );
	}

	/**
	 * Send the payer receipt and internal notification for a completed payment.
	 *
	 * @param array<string, mixed> $payment
	 * @param bool $isRenewal
	 * @return void
	 */
	public function notify( array $payment, bool $isRenewal = false ): void
	{
		if( ( $payment['purpose'] ?? '' ) === 'order' )
		{
			$this->notifyOrder( $payment );

			return;
		}

		$key    = (string) ( $payment['form_key'] ?? '' );
		$fields = $this->_paymentService->getFields( $key );

		$values = json_decode( (string) ( $payment['payload'] ?? '{}' ), true );
		$values = is_array( $values ) ? $values : [];

		$context = [
			'formLabel'       => $this->_paymentService->getFormConfig( $key )['label'] ?? $key,
			'formKey'         => $key,
			'purpose'         => (string) ( $payment['purpose'] ?? 'donation' ),
			'isRenewal'       => $isRenewal,
			'fields'          => $fields,
			'values'          => $values,
			'amountFormatted' => $this->formatAmount( (int) ( $payment['amount_cents'] ?? 0 ), (string) ( $payment['currency'] ?? 'usd' ) ),
			'frequencyLabel'  => $this->frequencyLabel( (string) ( $payment['frequency'] ?? 'one_time' ) ),
			'payment'         => $payment
		];

		try
		{
			$this->_paymentService->sendNotification( $key, $context );

			$payerEmail = (string) ( $payment['payer_email'] ?? '' );

			if( $payerEmail !== '' )
			{
				$this->_paymentService->sendReceipt( $payerEmail, $key, $context );
			}
		}
		catch( \Throwable $e )
		{
			Log::error( 'Payment notifications failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Create the subscription record for a newly started recurring payment.
	 *
	 * @param array<string, mixed> $payment
	 * @param string $subscriptionId
	 * @param object $gateway
	 * @return void
	 */
	public function openSubscription( array $payment, string $subscriptionId, object $gateway ): void
	{
		$subscriptions = $this->subscriptions();

		if( $subscriptions->findByGatewayId( $subscriptionId ) !== null )
		{
			return;
		}

		$status    = 'active';
		$periodEnd = null;

		if( method_exists( $gateway, 'getSubscription' ) )
		{
			try
			{
				$subscription = $gateway->getSubscription( $subscriptionId );
				$status       = $subscription->status ?: 'active';
				$periodEnd    = $subscription->currentPeriodEnd !== null
					? date( 'Y-m-d H:i:s', $subscription->currentPeriodEnd )
					: null;
			}
			catch( \Throwable $e )
			{
				Log::warning( 'Payment webhook: unable to load subscription details: ' . $e->getMessage() );
			}
		}

		try
		{
			$subscriptions->create( [
				'purpose'            => $payment['purpose'] ?? 'donation',
				'form_key'           => $payment['form_key'] ?? '',
				'provider'           => $payment['provider'] ?? 'stripe',
				'subscription_id'    => $subscriptionId,
				'status'             => $status,
				'frequency'          => $payment['frequency'] ?? 'monthly',
				'amount_cents'       => (int) ( $payment['amount_cents'] ?? 0 ),
				'currency'           => $payment['currency'] ?? 'usd',
				'payer_name'         => $payment['payer_name'] ?? null,
				'payer_email'        => $payment['payer_email'] ?? null,
				'payload'            => (string) ( $payment['payload'] ?? '{}' ),
				'current_period_end' => $periodEnd
			] );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Payment webhook: failed to open subscription: ' . $e->getMessage() );
		}
	}

	/**
	 * Send store-order receipts when store services are available.
	 *
	 * @param array<string, mixed> $payment
	 * @return void
	 */
	private function notifyOrder( array $payment ): void
	{
		$store = $this->_storeService ?? new StoreService( $this->_settings );

		$items = [];

		if( $this->_orderItems !== null )
		{
			$items = $this->_orderItems->findByPaymentId( (int) ( $payment['id'] ?? 0 ) );
		}

		$currency = (string) ( $payment['currency'] ?? 'usd' );

		$lines = array_map( function( array $item ) use ( $currency ): array {
			$qty  = (int) ( $item['quantity'] ?? 1 );
			$unit = (int) ( $item['unit_amount_cents'] ?? 0 );

			return [
				'name'           => (string) ( $item['name'] ?? '' ),
				'sku'            => $item['sku'] ?? null,
				'quantity'       => $qty,
				'unitFormatted'  => $this->formatAmount( $unit, $currency ),
				'totalFormatted' => $this->formatAmount( $unit * $qty, $currency )
			];
		}, $items );

		$context = [
			'orderId'        => $payment['id'] ?? '',
			'payerName'      => (string) ( $payment['payer_name'] ?? '' ),
			'payerEmail'     => (string) ( $payment['payer_email'] ?? '' ),
			'items'          => $lines,
			'totalFormatted' => $this->formatAmount( (int) ( $payment['amount_cents'] ?? 0 ), $currency ),
			'order'          => $payment
		];

		try
		{
			$store->sendOrderNotification( $context );

			$buyerEmail = (string) ( $payment['payer_email'] ?? '' );

			if( $buyerEmail !== '' )
			{
				$store->sendOrderReceipt( $buyerEmail, $context );
			}
		}
		catch( \Throwable $e )
		{
			Log::error( 'Order notifications failed: ' . $e->getMessage() );
		}
	}

	/**
	 * @return ISubscriptionRepository
	 */
	private function subscriptions(): ISubscriptionRepository
	{
		return $this->_subscriptions ??= new DatabaseSubscriptionRepository( $this->_settings );
	}

	/**
	 * @param int $cents
	 * @param string $currency
	 * @return string
	 */
	private function formatAmount( int $cents, string $currency ): string
	{
		$symbols = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
		$symbol  = $symbols[ strtolower( $currency ) ] ?? '';

		return $symbol . number_format( $cents / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
	}

	/**
	 * @param string $frequency
	 * @return string
	 */
	private function frequencyLabel( string $frequency ): string
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
}
