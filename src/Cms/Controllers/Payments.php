<?php

namespace Neuron\Cms\Controllers;

use Neuron\Application\CrossCutting\Event;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Events\PaymentCompletedEvent;
use Neuron\Cms\Events\PaymentFailedEvent;
use Neuron\Cms\Repositories\IOrderItemRepository;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Repositories\ISubscriptionRepository;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Contact\ContactFormValidator;
use Neuron\Cms\Services\Payment\PaymentService;
use Neuron\Cms\Services\Payment\PaymentGatewayFactory;
use Neuron\Cms\Services\Payment\PaymentReconciler;
use Neuron\Cms\Services\Store\StoreService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Payments\Dto\CheckoutSessionRequest;
use Neuron\Payments\Dto\Frequency;
use Neuron\Payments\Dto\Money;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

/**
 * Public payment controller.
 *
 * Drives the [payment] / [donation] shortcode flow for any purpose ( donation,
 * membership, ... ): it persists a pending payment, opens a hosted Stripe
 * Checkout session ( one-time or subscription ), and redirects the payer. The
 * payment is confirmed asynchronously by the signed webhook, which is the
 * single source of truth for "paid" and also drives the recurring subscription
 * lifecycle ( renewals, cancellations, failed payments ).
 *
 * Routes are exposed under /payments/* with /donations/* kept as aliases for
 * backward compatibility.
 *
 * The optional neuron-php/payments package is referenced only inside method
 * bodies that run after the gateway is confirmed available, so the controller
 * loads and the CMS works even when payments are not enabled.
 *
 * @package Neuron\Cms\Controllers
 */
class Payments extends Content
{
	private const HONEYPOT_FIELD = 'form_extra_field';

	private IPaymentRepository $_repository;
	private ISubscriptionRepository $_subscriptions;
	private PaymentGatewayFactory $_gatewayFactory;
	private PaymentService $_paymentService;
	private PaymentReconciler $_reconciler;
	private ContactFormValidator $_validator;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPaymentRepository $repository
	 * @param ISubscriptionRepository $subscriptions
	 * @param PaymentGatewayFactory $gatewayFactory
	 * @param PaymentService|null $paymentService
	 * @param ContactFormValidator|null $validator
	 * @param IOrderItemRepository|null $orderItems Order line items ( for store-order receipts )
	 * @param StoreService|null $storeService Store order emails
	 * @param PaymentReconciler|null $reconciler Completes pending payments from webhook or session lookup
	 */
	public function __construct(
		IMvcApplication         $app,
		SettingManager          $settings,
		SessionManager          $sessionManager,
		IPaymentRepository      $repository,
		ISubscriptionRepository $subscriptions,
		PaymentGatewayFactory   $gatewayFactory,
		?PaymentService         $paymentService = null,
		?ContactFormValidator   $validator = null,
		?IOrderItemRepository   $orderItems = null,
		?StoreService           $storeService = null,
		?PaymentReconciler      $reconciler = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository     = $repository;
		$this->_subscriptions  = $subscriptions;
		$this->_gatewayFactory = $gatewayFactory;
		$this->_paymentService = $paymentService ?? new PaymentService( $settings );
		$this->_validator      = $validator ?? new ContactFormValidator();
		$this->_reconciler     = $reconciler ?? new PaymentReconciler(
			$repository,
			$gatewayFactory,
			$this->_paymentService,
			$settings,
			$subscriptions,
			$orderItems,
			$storeService
		);
	}

	/**
	 * Issue a fresh CSRF token for payment forms (used by the widget script).
	 */
	#[Get('/payments/token', name: 'payments_token')]
	#[Get('/donations/token', name: 'donations_token')]
	public function token( Request $request ): string
	{
		$csrf = new CsrfToken( $this->getSessionManager() );

		return $this->renderJson( HttpResponseStatus::OK, [ 'token' => $csrf->getToken() ] );
	}

	/**
	 * Handle a payment submission: validate, persist pending, open checkout,
	 * and redirect the payer to the hosted payment page.
	 */
	#[Post('/payments/checkout', name: 'payments_checkout', filters: ['csrf'])]
	#[Post('/donations/checkout', name: 'donations_checkout', filters: ['csrf'])]
	public function checkout( Request $request ): never
	{
		$key    = (string) ( $request->post( 'form', '' ) ?? '' );
		$config = $this->_paymentService->getFormConfig( $key );

		if( $config === null )
		{
			Log::warning( "Payment submission for unknown form key: '{$key}'" );
			$this->redirectBack( '/', [ 'error', 'Sorry, that payment form is not available.' ] );
		}

		// Honeypot: bots fill the hidden field. Redirect quietly without charging.
		if( !empty( $request->post( self::HONEYPOT_FIELD, '' ) ) )
		{
			Log::info( "Payment submission rejected by honeypot for form '{$key}'" );
			$this->redirectBack( '/' );
		}

		$gateway = $this->_gatewayFactory->create();

		if( $gateway === null )
		{
			Log::error( "Payment attempted but no payment gateway is configured (form '{$key}')" );
			$this->redirectBack( '/', [ 'payment.' . $key . '.error', 'Online payments are temporarily unavailable. Please try again later.' ] );
		}

		[ $amountMajor, $amountError ] = $this->resolveAmount( $request, $key );

		if( $amountError !== null )
		{
			$this->redirectBack( '/', [ 'payment.' . $key . '.error', $amountError ] );
		}

		$frequencyValue = (string) ( $request->post( 'frequency', 'one_time' ) ?? 'one_time' );

		if( !in_array( $frequencyValue, $this->_paymentService->allowedFrequencies( $key ), true ) )
		{
			$frequencyValue = 'one_time';
		}

		$fields = $config['fields'] ?? [];
		$values = $this->collectValues( $fields, $request );
		$errors = $this->_validator->validate( $fields, $values );

		if( !empty( $errors ) )
		{
			$message = 'Please correct the following: ' . implode( ' ', array_values( $errors ) );
			$this->redirectBack( '/', [ 'payment.' . $key . '.error', $message ] );
		}

		$payer       = $this->_paymentService->resolvePayer( $fields, $values );
		$currency    = $this->_paymentService->getCurrency();
		$amountCents = (int) round( $amountMajor * 100 );
		$type        = $frequencyValue === 'one_time' ? 'one_time' : 'recurring';

		$paymentId = $this->persistPending( $key, $request, [
			'purpose'      => $this->_paymentService->purpose( $key ),
			'type'         => $type,
			'amount_cents' => $amountCents,
			'currency'     => $currency,
			'frequency'    => $frequencyValue,
			'payer_name'   => $payer['name'] ?? null,
			'payer_email'  => $payer['email'] ?? null,
			'payload'      => json_encode( $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		] );

		if( $paymentId === null )
		{
			$this->redirectBack( '/', [ 'payment.' . $key . '.error', 'We could not start your payment. Please try again.' ] );
		}

		try
		{
			$sessionRequest = new CheckoutSessionRequest(
				amount:        new Money( $amountCents, $currency ),
				frequency:     Frequency::fromValue( $frequencyValue ),
				successUrl:    $this->successUrl(),
				cancelUrl:     $this->absoluteUrl( $this->_paymentService->getCancelUrl() ),
				productName:   (string) ( $config['product_name'] ?? ( $config['label'] ?? 'Payment' ) ),
				customerEmail: $payer['email'] ?? null,
				metadata:      [ 'payment_id' => $paymentId, 'form_key' => $key ]
			);

			$session = $gateway->createCheckoutSession( $sessionRequest );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Payment checkout session failed: ' . $e->getMessage() );
			$this->_repository->updateStatus( $paymentId, 'failed' );
			$this->redirectBack( '/', [ 'payment.' . $key . '.error', 'We could not reach the payment processor. Please try again.' ] );
		}

		try
		{
			$this->_repository->updateSession( $paymentId, $session->id );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Payment updateSession failed: ' . $e->getMessage() );
		}

		$this->redirectToUrl( $session->url );
	}

	/**
	 * Thank-you page shown after returning from the gateway.
	 *
	 * The signed webhook is the primary confirmation path. If it has not
	 * arrived yet, the hosted session is retrieved so a paid checkout is not
	 * left pending.
	 */
	#[Get('/payments/success', name: 'payments_success')]
	#[Get('/donations/success', name: 'donations_success')]
	public function success( Request $request ): string
	{
		$sessionId = (string) ( $request->get( 'session_id', '' ) ?? '' );
		$payment   = $sessionId !== '' ? $this->_repository->findBySessionId( $sessionId ) : null;

		if( $payment !== null && ( $payment['status'] ?? '' ) === 'pending' )
		{
			$this->_reconciler->sync( $payment );
			$payment = $this->_repository->findBySessionId( $sessionId ) ?? $payment;
		}

		$message = 'Thank you for your payment!';

		if( $payment !== null )
		{
			$config  = $this->_paymentService->getFormConfig( (string) ( $payment['form_key'] ?? '' ) );
			$message = $config['success_message'] ?? $message;
		}

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Thank You',
				'Description' => 'Payment received',
				'Message'     => $message,
				'Payment'     => $payment
			],
			'success',
			'default'
		);
	}

	/**
	 * Page shown when the payer cancels the hosted checkout.
	 */
	#[Get('/payments/cancel', name: 'payments_cancel')]
	#[Get('/donations/cancel', name: 'donations_cancel')]
	public function cancel( Request $request ): string
	{
		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Payment Canceled',
				'Description' => 'Payment canceled',
				'Message'     => 'Your payment was canceled and you have not been charged.'
			],
			'cancel',
			'default'
		);
	}

	/**
	 * Gateway webhook endpoint.
	 *
	 * Intentionally has NO csrf/auth filters: it is called server-to-server by
	 * the gateway and is authenticated by its signed payload. The raw request
	 * body is required for signature verification. Handles the full lifecycle:
	 * initial checkout, recurring renewals, subscription changes/cancellations,
	 * and failed payments.
	 */
	#[Post('/payments/webhook', name: 'payments_webhook')]
	#[Post('/donations/webhook', name: 'donations_webhook')]
	public function webhook( Request $request ): string
	{
		$gateway = $this->_gatewayFactory->create();

		if( $gateway === null )
		{
			return $this->plain( HttpResponseStatus::SERVICE_UNAVAILABLE, 'gateway unavailable' );
		}

		$payload   = (string) file_get_contents( 'php://input' );
		$signature = (string) ( $request->server( 'HTTP_STRIPE_SIGNATURE', '' ) ?? '' );

		try
		{
			$event = $gateway->verifyWebhook( $payload, $signature );
		}
		catch( \Throwable $e )
		{
			Log::warning( 'Payment webhook verification failed: ' . $e->getMessage() );

			return $this->plain( HttpResponseStatus::BAD_REQUEST, 'invalid signature' );
		}

		try
		{
			return match( true )
			{
				$event->isCheckoutCompleted()     => $this->handleCheckoutCompleted( $event, $gateway ),
				$event->isInvoicePaid()           => $this->handleInvoicePaid( $event, $gateway ),
				$event->isSubscriptionDeleted()   => $this->handleSubscriptionDeleted( $event ),
				$event->isSubscriptionUpdated()   => $this->handleSubscriptionUpdated( $event ),
				$event->isInvoicePaymentFailed()  => $this->handleInvoicePaymentFailed( $event ),
				default                           => $this->plain( HttpResponseStatus::OK, 'ignored' )
			};
		}
		catch( \Throwable $e )
		{
			Log::error( 'Payment webhook handling failed: ' . $e->getMessage() );

			return $this->plain( HttpResponseStatus::INTERNAL_SERVER_ERROR, 'handler failed' );
		}
	}

	/**
	 * Complete the initial checkout: mark the pending payment paid, open a
	 * subscription record for recurring payments, and send notifications.
	 *
	 * @param \Neuron\Payments\Dto\WebhookEvent $event
	 * @param \Neuron\Payments\IPaymentGateway $gateway
	 * @return string
	 */
	private function handleCheckoutCompleted( object $event, object $gateway ): string
	{
		$payment = $this->locatePayment( $event->metadata()['payment_id'] ?? null, $event->sessionId() );

		if( $payment === null )
		{
			Log::warning( 'Payment webhook: no matching payment for completed checkout.' );

			return $this->plain( HttpResponseStatus::OK, 'no match' );
		}

		// Idempotency: gateways may deliver the same event more than once.
		if( ( $payment['status'] ?? '' ) === 'completed' )
		{
			return $this->plain( HttpResponseStatus::OK, 'already processed' );
		}

		$this->_reconciler->finalize(
			$payment,
			$event->paymentIntentId(),
			$event->subscriptionId(),
			$event->amountTotal(),
			$gateway
		);

		return $this->plain( HttpResponseStatus::OK, 'ok' );
	}

	/**
	 * Record a recurring renewal as a new completed payment and refresh the
	 * subscription period. The initial subscription invoice is skipped because
	 * the checkout.session.completed handler already recorded it.
	 *
	 * @param \Neuron\Payments\Dto\WebhookEvent $event
	 * @param \Neuron\Payments\IPaymentGateway $gateway
	 * @return string
	 */
	private function handleInvoicePaid( object $event, object $gateway ): string
	{
		$subscriptionId = $event->subscriptionId();

		if( $subscriptionId === null )
		{
			return $this->plain( HttpResponseStatus::OK, 'ignored' );
		}

		// Only renewals are recorded here; the first charge comes via checkout.
		if( !$event->isRenewal() )
		{
			return $this->plain( HttpResponseStatus::OK, 'initial invoice' );
		}

		// Idempotency: skip if this invoice was already recorded.
		$invoiceId = $event->invoiceId();

		if( $invoiceId !== null && $this->invoiceAlreadyRecorded( $invoiceId ) )
		{
			return $this->plain( HttpResponseStatus::OK, 'already processed' );
		}

		$origin = $this->_repository->findBySubscriptionId( $subscriptionId );

		if( $origin === null )
		{
			Log::warning( "Payment webhook: renewal for unknown subscription '{$subscriptionId}'." );

			return $this->plain( HttpResponseStatus::OK, 'no match' );
		}

		$renewalId = $this->_repository->create( [
			'purpose'           => $origin['purpose'] ?? 'donation',
			'form_key'          => $origin['form_key'] ?? '',
			'provider'          => $origin['provider'] ?? 'stripe',
			'type'              => 'recurring',
			'payment_intent_id' => $event->paymentIntentId(),
			'invoice_id'        => $invoiceId,
			'subscription_id'   => $subscriptionId,
			'amount_cents'      => $event->amountPaid() ?? ( (int) ( $origin['amount_cents'] ?? 0 ) ),
			'currency'          => $origin['currency'] ?? 'usd',
			'frequency'         => $origin['frequency'] ?? 'monthly',
			'status'            => 'completed',
			'payer_name'        => $origin['payer_name'] ?? null,
			'payer_email'       => $origin['payer_email'] ?? null,
			'payload'           => (string) ( $origin['payload'] ?? '{}' ),
			'completed_at'      => date( 'Y-m-d H:i:s' )
		] );

		$this->refreshSubscription( $subscriptionId, $gateway );

		$renewal = $this->_repository->findById( $renewalId );

		if( $renewal !== null )
		{
			Event::emit( new PaymentCompletedEvent( $renewal, true ) );
			$this->_reconciler->notify( $renewal, true );
		}

		return $this->plain( HttpResponseStatus::OK, 'ok' );
	}

	/**
	 * Mark a subscription canceled when the gateway reports it ended.
	 *
	 * @param \Neuron\Payments\Dto\WebhookEvent $event
	 * @return string
	 */
	private function handleSubscriptionDeleted( object $event ): string
	{
		$subscriptionId = $event->subscriptionId();

		if( $subscriptionId !== null )
		{
			$this->_subscriptions->updateState( $subscriptionId, [
				'status'      => 'canceled',
				'canceled_at' => date( 'Y-m-d H:i:s' )
			] );
		}

		return $this->plain( HttpResponseStatus::OK, 'ok' );
	}

	/**
	 * Sync a subscription's status / period when the gateway reports a change.
	 *
	 * @param \Neuron\Payments\Dto\WebhookEvent $event
	 * @return string
	 */
	private function handleSubscriptionUpdated( object $event ): string
	{
		$subscriptionId = $event->subscriptionId();

		if( $subscriptionId === null )
		{
			return $this->plain( HttpResponseStatus::OK, 'ignored' );
		}

		$state = [];

		if( $event->subscriptionStatus() !== null )
		{
			$state['status'] = $event->subscriptionStatus();
		}

		if( $event->currentPeriodEnd() !== null )
		{
			$state['current_period_end'] = date( 'Y-m-d H:i:s', $event->currentPeriodEnd() );
		}

		if( $state !== [] )
		{
			$this->_subscriptions->updateState( $subscriptionId, $state );
		}

		return $this->plain( HttpResponseStatus::OK, 'ok' );
	}

	/**
	 * Flag a subscription past_due when a renewal payment fails.
	 *
	 * @param \Neuron\Payments\Dto\WebhookEvent $event
	 * @return string
	 */
	private function handleInvoicePaymentFailed( object $event ): string
	{
		$subscriptionId = $event->subscriptionId();
		$payment        = $subscriptionId !== null
			? $this->_repository->findBySubscriptionId( $subscriptionId )
			: null;

		if( $subscriptionId !== null )
		{
			$this->_subscriptions->updateState( $subscriptionId, [ 'status' => 'past_due' ] );
		}

		Event::emit( new PaymentFailedEvent( $payment, $subscriptionId ) );

		return $this->plain( HttpResponseStatus::OK, 'ok' );
	}

	/**
	 * Create the subscription record for a newly started recurring payment,
	 * enriching status / period end from the gateway when possible.
	 *
	 * @param array<string, mixed> $payment
	 * @param string $subscriptionId
	 * @param \Neuron\Payments\IPaymentGateway $gateway
	 * @return void
	 */
	private function openSubscription( array $payment, string $subscriptionId, object $gateway ): void
	{
		$this->_reconciler->openSubscription( $payment, $subscriptionId, $gateway );
	}

	/**
	 * Refresh a subscription's status and period end from the gateway.
	 *
	 * @param string $subscriptionId
	 * @param \Neuron\Payments\IPaymentGateway $gateway
	 * @return void
	 */
	private function refreshSubscription( string $subscriptionId, object $gateway ): void
	{
		try
		{
			$subscription = $gateway->getSubscription( $subscriptionId );

			$this->_subscriptions->updateState( $subscriptionId, [
				'status'             => $subscription->status ?: 'active',
				'current_period_end' => $subscription->currentPeriodEnd !== null
					? date( 'Y-m-d H:i:s', $subscription->currentPeriodEnd )
					: null
			] );
		}
		catch( \Throwable $e )
		{
			Log::warning( 'Payment webhook: unable to refresh subscription: ' . $e->getMessage() );
		}
	}

	/**
	 * Whether a renewal invoice has already been recorded ( idempotency ).
	 *
	 * @param string $invoiceId
	 * @return bool
	 */
	private function invoiceAlreadyRecorded( string $invoiceId ): bool
	{
		return $this->_repository->findByInvoiceId( $invoiceId ) !== null;
	}

	/**
	 * Resolve the payment amount in major units, validating against the form's
	 * custom-amount policy and minimum.
	 *
	 * @param Request $request
	 * @param string $key
	 * @return array{0: float, 1: ?string} [amount, error]
	 */
	private function resolveAmount( Request $request, string $key ): array
	{
		$selected = (string) ( $request->post( 'amount', '' ) ?? '' );
		$min      = $this->_paymentService->minimumAmount( $key );

		if( $selected === 'custom' || $selected === '' )
		{
			if( !$this->_paymentService->allowsCustomAmount( $key ) && $selected === 'custom' )
			{
				return [ 0.0, 'Please choose an amount.' ];
			}

			$custom = $request->post( 'custom_amount', '' );

			if( !is_numeric( $custom ) )
			{
				return [ 0.0, 'Please enter an amount.' ];
			}

			$value = (float) $custom;
		}
		else
		{
			if( !is_numeric( $selected ) )
			{
				return [ 0.0, 'Please choose an amount.' ];
			}

			$value = (float) $selected;
		}

		if( $value < $min )
		{
			return [ 0.0, 'The minimum amount is ' . rtrim( rtrim( number_format( $min, 2 ), '0' ), '.' ) . '.' ];
		}

		return [ $value, null ];
	}

	/**
	 * Collect only the configured payer field values from the request.
	 *
	 * @param array $fields
	 * @param Request $request
	 * @return array<string, mixed>
	 */
	private function collectValues( array $fields, Request $request ): array
	{
		$values = [];

		foreach( $fields as $field )
		{
			$name = $field['name'] ?? null;

			if( $name === null )
			{
				continue;
			}

			$type = $field['type'] ?? 'text';

			if( $type === 'checkbox' )
			{
				$raw = $request->post( $name, '' );
				$values[ $name ] = ( $raw === 'on' || $raw === '1' || $raw === 1 || $raw === true ) ? '1' : '';
				continue;
			}

			$raw = $request->post( $name, '' );
			$values[ $name ] = is_string( $raw ) ? trim( $raw ) : $raw;
		}

		return $values;
	}

	/**
	 * Persist a pending payment, returning its id (or null on failure).
	 *
	 * @param string $key
	 * @param Request $request
	 * @param array<string, mixed> $data
	 * @return int|null
	 */
	private function persistPending( string $key, Request $request, array $data ): ?int
	{
		try
		{
			return $this->_repository->create( array_merge( [
				'form_key'   => $key,
				'provider'   => (string) ( $this->_settings->get( 'payments', 'provider' ) ?? 'stripe' ),
				'status'     => 'pending',
				'ip_address' => $request->getClientIp(),
				'user_agent' => substr( (string) $request->server( 'HTTP_USER_AGENT', '' ), 0, 500 )
			], $data ) );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Payment persistence failed: ' . $e->getMessage() );

			return null;
		}
	}

	/**
	 * Find a payment by metadata id first, then by gateway session id.
	 *
	 * @param mixed $paymentId
	 * @param string|null $sessionId
	 * @return array<string, mixed>|null
	 */
	private function locatePayment( mixed $paymentId, ?string $sessionId ): ?array
	{
		$id = (int) $paymentId;

		if( $id > 0 )
		{
			$payment = $this->_repository->findById( $id );

			if( $payment !== null )
			{
				return $payment;
			}
		}

		if( $sessionId !== null && $sessionId !== '' )
		{
			return $this->_repository->findBySessionId( $sessionId );
		}

		return null;
	}

	/**
	 * Send the payer receipt and internal notification for a completed payment.
	 *
	 * @param array<string, mixed> $payment
	 * @param bool $isRenewal Whether this is a recurring renewal charge
	 * @return void
	 */
	private function sendNotifications( array $payment, bool $isRenewal = false ): void
	{
		$this->_reconciler->notify( $payment, $isRenewal );
	}

	/**
	 * Build the absolute success URL with the gateway session placeholder.
	 *
	 * @return string
	 */
	private function successUrl(): string
	{
		$base = $this->absoluteUrl( $this->_paymentService->getSuccessUrl() );
		$glue = str_contains( $base, '?' ) ? '&' : '?';

		return $base . $glue . 'session_id={CHECKOUT_SESSION_ID}';
	}

	/**
	 * Turn a path into an absolute URL using the current request host.
	 *
	 * @param string $path
	 * @return string
	 */
	private function absoluteUrl( string $path ): string
	{
		if( str_starts_with( $path, 'http://' ) || str_starts_with( $path, 'https://' ) )
		{
			return $path;
		}

		$https  = ( $_SERVER['HTTPS'] ?? '' ) === 'on' || ( $_SERVER['SERVER_PORT'] ?? '' ) === '443';
		$scheme = $https ? 'https' : 'http';
		$host   = (string) ( $_SERVER['HTTP_HOST'] ?? ( $this->_settings->get( 'site', 'url' ) ?? 'localhost' ) );

		return $scheme . '://' . $host . '/' . ltrim( $path, '/' );
	}

	/**
	 * Format an amount (minor units) as a display string.
	 *
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
	 * Human label for a recurrence value.
	 *
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

	/**
	 * Emit a short plain-text response with a status code (for webhooks).
	 *
	 * @param HttpResponseStatus $status
	 * @param string $body
	 * @return string
	 */
	private function plain( HttpResponseStatus $status, string $body ): string
	{
		@http_response_code( $status->value );
		@header( 'Content-Type: text/plain; charset=UTF-8' );

		return $body;
	}
}
