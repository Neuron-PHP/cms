<?php

namespace Tests\Unit\Cms\Services\Payment;

use Neuron\Application\CrossCutting\Event;
use Neuron\Cms\Events\PaymentCompletedEvent;
use Neuron\Cms\Repositories\DatabasePaymentRepository;
use Neuron\Cms\Repositories\DatabaseSubscriptionRepository;
use Neuron\Cms\Services\Payment\PaymentGatewayFactory;
use Neuron\Cms\Services\Payment\PaymentReconciler;
use Neuron\Cms\Services\Payment\PaymentService;
use Neuron\Cms\Services\Payment\RetrievedCheckout;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Events\Broadcasters\Generic;
use Neuron\Events\IEvent;
use Neuron\Events\IListener;
use Neuron\Payments\Dto\CheckoutSession;
use Neuron\Payments\Dto\CheckoutSessionRequest;
use Neuron\Payments\Dto\Refund;
use Neuron\Payments\Dto\Subscription;
use Neuron\Payments\Dto\WebhookEvent;
use Neuron\Payments\IPaymentGateway;
use PHPUnit\Framework\TestCase;
use PDO;

class PaymentReconcilerTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		Event::invalidate();
		ReconcileEventCapture::$events = [];
		Event::registerBroadcaster( new Generic() );
		Event::registerListeners( [
			PaymentCompletedEvent::class => [ ReconcileEventCapture::class ]
		] );
	}

	protected function tearDown(): void
	{
		Event::invalidate();
		ReconcileEventCapture::$events = [];

		parent::tearDown();
	}

	public function testSyncCompletesPaidPendingPayment(): void
	{
		[ $payments, $subs ] = $this->repos();

		$id = $payments->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe',
			'type' => 'one_time', 'session_id' => 'cs_paid', 'amount_cents' => 2500,
			'currency' => 'usd', 'frequency' => 'one_time', 'status' => 'pending',
			'payer_email' => '', 'payload' => '{}'
		] );

		$reconciler = $this->reconciler( $payments, $subs, $this->gateway(
			new RetrievedCheckout( 'cs_paid', 'complete', 'paid', 'pi_1', null, 2500 )
		) );

		$this->assertSame( PaymentReconciler::COMPLETED, $reconciler->sync( $payments->findById( $id ) ) );

		$row = $payments->findById( $id );
		$this->assertSame( 'completed', $row['status'] );
		$this->assertSame( 'pi_1', $row['payment_intent_id'] );
		$this->assertCount( 1, ReconcileEventCapture::$events );
	}

	public function testSyncMarksExpiredCheckoutCanceled(): void
	{
		[ $payments, $subs ] = $this->repos();

		$id = $payments->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe',
			'type' => 'one_time', 'session_id' => 'cs_exp', 'amount_cents' => 1000,
			'currency' => 'usd', 'frequency' => 'one_time', 'status' => 'pending', 'payload' => '{}'
		] );

		$reconciler = $this->reconciler( $payments, $subs, $this->gateway(
			new RetrievedCheckout( 'cs_exp', 'expired', 'unpaid' )
		) );

		$this->assertSame( PaymentReconciler::CANCELED, $reconciler->sync( $payments->findById( $id ) ) );
		$this->assertSame( 'canceled', $payments->findById( $id )['status'] );
		$this->assertCount( 0, ReconcileEventCapture::$events );
	}

	public function testSyncLeavesOpenCheckoutPending(): void
	{
		[ $payments, $subs ] = $this->repos();

		$id = $payments->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe',
			'type' => 'one_time', 'session_id' => 'cs_open', 'amount_cents' => 1000,
			'currency' => 'usd', 'frequency' => 'one_time', 'status' => 'pending',
			'payload' => '{}'
		] );

		$reconciler = $this->reconciler( $payments, $subs, $this->gateway(
			new RetrievedCheckout( 'cs_open', 'open', 'unpaid' )
		) );

		$this->assertSame( PaymentReconciler::UNPAID, $reconciler->sync( $payments->findById( $id ) ) );
		$this->assertSame( 'pending', $payments->findById( $id )['status'] );
	}

	public function testSyncWithoutSessionId(): void
	{
		[ $payments, $subs ] = $this->repos();

		$id = $payments->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe',
			'type' => 'one_time', 'amount_cents' => 500, 'currency' => 'usd',
			'frequency' => 'one_time', 'status' => 'pending', 'payload' => '{}'
		] );

		$reconciler = $this->reconciler( $payments, $subs, $this->gateway(
			new RetrievedCheckout( 'cs_x', 'complete', 'paid' )
		) );

		$this->assertSame( PaymentReconciler::NO_SESSION, $reconciler->sync( $payments->findById( $id ) ) );
	}

	public function testFinalizeIsIdempotent(): void
	{
		[ $payments, $subs ] = $this->repos();

		$id = $payments->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe',
			'type' => 'one_time', 'amount_cents' => 500, 'currency' => 'usd',
			'frequency' => 'one_time', 'status' => 'completed', 'payload' => '{}'
		] );

		$reconciler = $this->reconciler( $payments, $subs, $this->gateway(
			new RetrievedCheckout( 'cs_x', 'complete', 'paid' )
		) );

		$reconciler->finalize( $payments->findById( $id ), 'pi_x', null, 500 );

		$this->assertCount( 0, ReconcileEventCapture::$events );
	}

	/**
	 * @return array{0: DatabasePaymentRepository, 1: DatabaseSubscriptionRepository, 2: PDO}
	 */
	private function repos(): array
	{
		$pdo = new PDO( 'sqlite::memory:' );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$pdo->exec( "
			CREATE TABLE payments (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				purpose VARCHAR(32) NOT NULL DEFAULT 'donation',
				form_key VARCHAR(64) NOT NULL,
				provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
				type VARCHAR(16) NOT NULL DEFAULT 'one_time',
				session_id VARCHAR(255), payment_intent_id VARCHAR(255), invoice_id VARCHAR(255),
				subscription_id VARCHAR(255), amount_cents INTEGER NOT NULL DEFAULT 0,
				currency VARCHAR(8) NOT NULL DEFAULT 'usd', frequency VARCHAR(32) NOT NULL DEFAULT 'one_time',
				status VARCHAR(32) NOT NULL DEFAULT 'pending', payer_name VARCHAR(255), payer_email VARCHAR(255),
				payload TEXT NOT NULL DEFAULT '{}', ip_address VARCHAR(45), user_agent VARCHAR(500),
				completed_at TIMESTAMP, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );
		$pdo->exec( "
			CREATE TABLE subscriptions (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				purpose VARCHAR(32) NOT NULL DEFAULT 'donation', form_key VARCHAR(64) NOT NULL,
				provider VARCHAR(32) NOT NULL DEFAULT 'stripe', subscription_id VARCHAR(255) NOT NULL,
				status VARCHAR(32) NOT NULL DEFAULT 'active', frequency VARCHAR(32) NOT NULL DEFAULT 'monthly',
				amount_cents INTEGER NOT NULL DEFAULT 0, currency VARCHAR(8) NOT NULL DEFAULT 'usd',
				payer_name VARCHAR(255), payer_email VARCHAR(255), payload TEXT NOT NULL DEFAULT '{}',
				current_period_end TIMESTAMP, canceled_at TIMESTAMP,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )->willReturn( [ 'adapter' => 'sqlite', 'name' => ':memory:' ] );

		$payments = new DatabasePaymentRepository( $settings );
		$subs     = new DatabaseSubscriptionRepository( $settings );

		( new \ReflectionProperty( $payments, '_pdo' ) )->setValue( $payments, $pdo );
		( new \ReflectionProperty( $subs, '_pdo' ) )->setValue( $subs, $pdo );

		return [ $payments, $subs, $pdo ];
	}

	private function reconciler(
		DatabasePaymentRepository $payments,
		DatabaseSubscriptionRepository $subs,
		IPaymentGateway $gateway
	): PaymentReconciler
	{
		$settings = new SettingManager( new Memory( [
			'system'   => [ 'base_path' => sys_get_temp_dir() ],
			'payments' => [
				'secret_key' => 'sk_test_x',
				'forms'      => [
					'general' => [
						'label'  => 'Donate',
						'to'     => 'info@example.com',
						'fields' => []
					]
				]
			]
		] ) );

		$factory = $this->createMock( PaymentGatewayFactory::class );
		$factory->method( 'create' )->willReturn( $gateway );

		return new PaymentReconciler(
			$payments,
			$factory,
			new PaymentService( $settings ),
			$settings,
			$subs
		);
	}

	private function gateway( RetrievedCheckout $session ): IPaymentGateway
	{
		return new class( $session ) implements IPaymentGateway {
			public function __construct( private RetrievedCheckout $session )
			{
			}

			public function createCheckoutSession( CheckoutSessionRequest $request ): CheckoutSession
			{
				return new CheckoutSession( $this->session->id, 'https://example.test/cs' );
			}

			public function getCheckoutSession( string $sessionId ): RetrievedCheckout
			{
				return $this->session;
			}

			public function verifyWebhook( string $payload, string $signature ): WebhookEvent
			{
				return new WebhookEvent( 'noop', [] );
			}

			public function refund( string $paymentIntentId ): Refund
			{
				return new Refund( 're_x', 'succeeded' );
			}

			public function getSubscription( string $subscriptionId ): Subscription
			{
				return new Subscription( $subscriptionId, 'active', 1750000000 );
			}

			public function cancelSubscription( string $subscriptionId, bool $atPeriodEnd = false ): Subscription
			{
				return new Subscription( $subscriptionId, 'canceled', null, 1750000000 );
			}
		};
	}
}

class ReconcileEventCapture implements IListener
{
	/** @var IEvent[] */
	public static array $events = [];

	public function event( $event ): void
	{
		self::$events[] = $event;
	}
}
