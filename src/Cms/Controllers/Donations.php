<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IDonationRepository;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Contact\ContactFormValidator;
use Neuron\Cms\Services\Donation\DonationService;
use Neuron\Cms\Services\Donation\PaymentGatewayFactory;
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
 * Public donation controller.
 *
 * Handles the [donation] shortcode flow: it persists a pending donation,
 * opens a hosted Stripe Checkout session, and redirects the donor. The
 * payment is confirmed asynchronously by the signed webhook, which is the
 * single source of truth for "paid" (donors can close the success tab).
 *
 * The optional neuron-php/payments package is referenced only inside method
 * bodies that run after the gateway is confirmed available, so the controller
 * loads and the CMS works even when donations are not enabled.
 *
 * @package Neuron\Cms\Controllers
 */
class Donations extends Content
{
	private const HONEYPOT_FIELD = 'company_website';

	private IDonationRepository $_repository;
	private PaymentGatewayFactory $_gatewayFactory;
	private DonationService $_donationService;
	private ContactFormValidator $_validator;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IDonationRepository $repository
	 * @param PaymentGatewayFactory $gatewayFactory
	 * @param DonationService|null $donationService
	 * @param ContactFormValidator|null $validator
	 */
	public function __construct(
		IMvcApplication       $app,
		SettingManager        $settings,
		SessionManager        $sessionManager,
		IDonationRepository   $repository,
		PaymentGatewayFactory $gatewayFactory,
		?DonationService      $donationService = null,
		?ContactFormValidator $validator = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository      = $repository;
		$this->_gatewayFactory  = $gatewayFactory;
		$this->_donationService = $donationService ?? new DonationService( $settings );
		$this->_validator       = $validator ?? new ContactFormValidator();
	}

	/**
	 * Issue a fresh CSRF token for donation forms (used by the widget script).
	 */
	#[Get('/donations/token', name: 'donations_token')]
	public function token( Request $request ): string
	{
		$csrf = new CsrfToken( $this->getSessionManager() );

		return $this->renderJson( HttpResponseStatus::OK, [ 'token' => $csrf->getToken() ] );
	}

	/**
	 * Handle a donation submission: validate, persist pending, open checkout,
	 * and redirect the donor to the hosted payment page.
	 */
	#[Post('/donations/checkout', name: 'donations_checkout', filters: ['csrf'])]
	public function checkout( Request $request ): never
	{
		$key    = (string) ( $request->post( 'form', '' ) ?? '' );
		$config = $this->_donationService->getFormConfig( $key );

		if( $config === null )
		{
			Log::warning( "Donation submission for unknown form key: '{$key}'" );
			$this->redirectBack( '/', [ 'error', 'Sorry, that donation form is not available.' ] );
		}

		// Honeypot: bots fill the hidden field. Redirect quietly without charging.
		if( !empty( $request->post( self::HONEYPOT_FIELD, '' ) ) )
		{
			Log::info( "Donation submission rejected by honeypot for form '{$key}'" );
			$this->redirectBack( '/' );
		}

		$gateway = $this->_gatewayFactory->create();

		if( $gateway === null )
		{
			Log::error( "Donation attempted but no payment gateway is configured (form '{$key}')" );
			$this->redirectBack( '/', [ 'donation.' . $key . '.error', 'Online donations are temporarily unavailable. Please try again later.' ] );
		}

		[ $amountMajor, $amountError ] = $this->resolveAmount( $request, $key );

		if( $amountError !== null )
		{
			$this->redirectBack( '/', [ 'donation.' . $key . '.error', $amountError ] );
		}

		$frequencyValue = (string) ( $request->post( 'frequency', 'one_time' ) ?? 'one_time' );

		if( !in_array( $frequencyValue, $this->_donationService->allowedFrequencies( $key ), true ) )
		{
			$frequencyValue = 'one_time';
		}

		$fields = $config['fields'] ?? [];
		$values = $this->collectValues( $fields, $request );
		$errors = $this->_validator->validate( $fields, $values );

		if( !empty( $errors ) )
		{
			$message = 'Please correct the following: ' . implode( ' ', array_values( $errors ) );
			$this->redirectBack( '/', [ 'donation.' . $key . '.error', $message ] );
		}

		$donor       = $this->_donationService->resolveDonor( $fields, $values );
		$currency    = $this->_donationService->getCurrency();
		$amountCents = (int) round( $amountMajor * 100 );

		$donationId = $this->persistPending( $key, $request, [
			'amount_cents' => $amountCents,
			'currency'     => $currency,
			'frequency'    => $frequencyValue,
			'donor_name'   => $donor['name'] ?? null,
			'donor_email'  => $donor['email'] ?? null,
			'payload'      => json_encode( $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		] );

		if( $donationId === null )
		{
			$this->redirectBack( '/', [ 'donation.' . $key . '.error', 'We could not start your donation. Please try again.' ] );
		}

		try
		{
			$sessionRequest = new CheckoutSessionRequest(
				amount:        new Money( $amountCents, $currency ),
				frequency:     Frequency::fromValue( $frequencyValue ),
				successUrl:    $this->successUrl(),
				cancelUrl:     $this->absoluteUrl( $this->_donationService->getCancelUrl() ),
				productName:   (string) ( $config['product_name'] ?? ( $config['label'] ?? 'Donation' ) ),
				customerEmail: $donor['email'] ?? null,
				metadata:      [ 'donation_id' => $donationId, 'form_key' => $key ]
			);

			$session = $gateway->createCheckoutSession( $sessionRequest );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Donation checkout session failed: ' . $e->getMessage() );
			$this->_repository->updateStatus( $donationId, 'failed' );
			$this->redirectBack( '/', [ 'donation.' . $key . '.error', 'We could not reach the payment processor. Please try again.' ] );
		}

		try
		{
			$this->_repository->updateSession( $donationId, $session->id );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Donation updateSession failed: ' . $e->getMessage() );
		}

		$this->redirectToUrl( $session->url );
	}

	/**
	 * Thank-you page shown after returning from the gateway.
	 *
	 * This is presentational only; the donation is confirmed by the webhook.
	 */
	#[Get('/donations/success', name: 'donations_success')]
	public function success( Request $request ): string
	{
		$sessionId = (string) ( $request->get( 'session_id', '' ) ?? '' );
		$donation  = $sessionId !== '' ? $this->_repository->findBySessionId( $sessionId ) : null;

		$message = 'Thank you for your generous donation!';

		if( $donation !== null )
		{
			$config = $this->_donationService->getFormConfig( (string) ( $donation['form_key'] ?? '' ) );
			$message = $config['success_message'] ?? $message;
		}

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Thank You',
				'Description' => 'Donation received',
				'Message'     => $message,
				'Donation'    => $donation
			],
			'success',
			'default'
		);
	}

	/**
	 * Page shown when the donor cancels the hosted checkout.
	 */
	#[Get('/donations/cancel', name: 'donations_cancel')]
	public function cancel( Request $request ): string
	{
		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Donation Canceled',
				'Description' => 'Donation canceled',
				'Message'     => 'Your donation was canceled and you have not been charged.'
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
	 * body is required for signature verification.
	 */
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
			Log::warning( 'Donation webhook verification failed: ' . $e->getMessage() );

			return $this->plain( HttpResponseStatus::BAD_REQUEST, 'invalid signature' );
		}

		if( !$event->isCheckoutCompleted() )
		{
			return $this->plain( HttpResponseStatus::OK, 'ignored' );
		}

		$donation = $this->locateDonation( $event->metadata()['donation_id'] ?? null, $event->sessionId() );

		if( $donation === null )
		{
			Log::warning( 'Donation webhook: no matching donation for completed checkout.' );

			return $this->plain( HttpResponseStatus::OK, 'no match' );
		}

		// Idempotency: gateways may deliver the same event more than once.
		if( ( $donation['status'] ?? '' ) === 'completed' )
		{
			return $this->plain( HttpResponseStatus::OK, 'already processed' );
		}

		$donationId = (int) $donation['id'];

		try
		{
			$this->_repository->markCompleted( $donationId, [
				'payment_intent_id' => $event->paymentIntentId(),
				'subscription_id'   => $event->subscriptionId(),
				'amount_cents'      => $event->amountTotal()
			] );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Donation webhook markCompleted failed: ' . $e->getMessage() );

			return $this->plain( HttpResponseStatus::INTERNAL_SERVER_ERROR, 'persist failed' );
		}

		$this->sendNotifications( $this->_repository->findById( $donationId ) ?? $donation );

		return $this->plain( HttpResponseStatus::OK, 'ok' );
	}

	/**
	 * Resolve the donation amount in major units, validating against the form's
	 * custom-amount policy and minimum.
	 *
	 * @param Request $request
	 * @param string $key
	 * @return array{0: float, 1: ?string} [amount, error]
	 */
	private function resolveAmount( Request $request, string $key ): array
	{
		$selected = (string) ( $request->post( 'amount', '' ) ?? '' );
		$min      = $this->_donationService->minimumAmount( $key );

		if( $selected === 'custom' || $selected === '' )
		{
			if( !$this->_donationService->allowsCustomAmount( $key ) && $selected === 'custom' )
			{
				return [ 0.0, 'Please choose a donation amount.' ];
			}

			$custom = $request->post( 'custom_amount', '' );

			if( !is_numeric( $custom ) )
			{
				return [ 0.0, 'Please enter a donation amount.' ];
			}

			$value = (float) $custom;
		}
		else
		{
			if( !is_numeric( $selected ) )
			{
				return [ 0.0, 'Please choose a donation amount.' ];
			}

			$value = (float) $selected;
		}

		if( $value < $min )
		{
			return [ 0.0, 'The minimum donation is ' . rtrim( rtrim( number_format( $min, 2 ), '0' ), '.' ) . '.' ];
		}

		return [ $value, null ];
	}

	/**
	 * Collect only the configured donor field values from the request.
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
	 * Persist a pending donation, returning its id (or null on failure).
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
			Log::error( 'Donation persistence failed: ' . $e->getMessage() );

			return null;
		}
	}

	/**
	 * Find a donation by metadata id first, then by gateway session id.
	 *
	 * @param mixed $donationId
	 * @param string|null $sessionId
	 * @return array<string, mixed>|null
	 */
	private function locateDonation( mixed $donationId, ?string $sessionId ): ?array
	{
		$id = (int) $donationId;

		if( $id > 0 )
		{
			$donation = $this->_repository->findById( $id );

			if( $donation !== null )
			{
				return $donation;
			}
		}

		if( $sessionId !== null && $sessionId !== '' )
		{
			return $this->_repository->findBySessionId( $sessionId );
		}

		return null;
	}

	/**
	 * Send the donor receipt and internal notification for a completed donation.
	 *
	 * @param array<string, mixed> $donation
	 * @return void
	 */
	private function sendNotifications( array $donation ): void
	{
		$key    = (string) ( $donation['form_key'] ?? '' );
		$fields = $this->_donationService->getFields( $key );

		$values = json_decode( (string) ( $donation['payload'] ?? '{}' ), true );
		$values = is_array( $values ) ? $values : [];

		$context = [
			'formLabel'       => $this->_donationService->getFormConfig( $key )['label'] ?? $key,
			'formKey'         => $key,
			'fields'          => $fields,
			'values'          => $values,
			'amountFormatted' => $this->formatAmount( (int) ( $donation['amount_cents'] ?? 0 ), (string) ( $donation['currency'] ?? 'usd' ) ),
			'frequencyLabel'  => $this->frequencyLabel( (string) ( $donation['frequency'] ?? 'one_time' ) ),
			'donation'        => $donation
		];

		try
		{
			$this->_donationService->sendNotification( $key, $context );

			$donorEmail = (string) ( $donation['donor_email'] ?? '' );

			if( $donorEmail !== '' )
			{
				$this->_donationService->sendReceipt( $donorEmail, $key, $context );
			}
		}
		catch( \Throwable $e )
		{
			Log::error( 'Donation notifications failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Build the absolute success URL with the gateway session placeholder.
	 *
	 * @return string
	 */
	private function successUrl(): string
	{
		$base = $this->absoluteUrl( $this->_donationService->getSuccessUrl() );
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
