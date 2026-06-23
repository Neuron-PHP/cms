<?php

namespace Tests\Unit\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Controllers\Payments;
use Neuron\Cms\Repositories\DatabasePaymentRepository;
use Neuron\Cms\Repositories\DatabaseSubscriptionRepository;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Repositories\ISubscriptionRepository;
use Neuron\Cms\Services\Payment\PaymentService;
use Neuron\Cms\Services\Payment\PaymentGatewayFactory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Payments\Dto\CheckoutSession;
use Neuron\Payments\Dto\CheckoutSessionRequest;
use Neuron\Payments\Dto\Refund;
use Neuron\Payments\Dto\Subscription;
use Neuron\Payments\Dto\WebhookEvent;
use Neuron\Payments\IPaymentGateway;
use PHPUnit\Framework\TestCase;
use PDO;

class PaymentsTest extends TestCase
{
	private function settings(): SettingManager
	{
		return new SettingManager( new Memory( [
			'site'     => [ 'name' => 'Test Site' ],
			'payments' => [
				'provider'     => 'stripe',
				'currency'     => 'usd',
				'default_form' => 'general',
				'forms'        => [
					'general' => [
						'purpose'             => 'donation',
						'label'               => 'Donate',
						'amounts'             => [ 25, 50, 100 ],
						'allow_custom_amount' => true,
						'min_amount'          => 5,
						'frequencies'         => [ 'one_time', 'monthly' ],
						'fields'              => [
							[ 'name' => 'name', 'type' => 'text', 'required' => true, 'sender_name' => true ],
							[ 'name' => 'email', 'type' => 'email', 'required' => true, 'reply_to' => true ]
						]
					]
				]
			]
		] ) );
	}

	private function pdo(): PDO
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

		return $pdo;
	}

	private function repoFrom( string $class, PDO $pdo ): object
	{
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )->willReturn( [ 'adapter' => 'sqlite', 'name' => ':memory:' ] );

		$repo = new $class( $settings );
		$prop = new \ReflectionProperty( $repo, '_pdo' );
		$prop->setValue( $repo, $pdo );

		return $repo;
	}

	private function makeController(
		?IPaymentRepository $payments = null,
		?ISubscriptionRepository $subs = null
	): Payments
	{
		$settings = $this->settings();

		return new Payments(
			$this->createMock( IMvcApplication::class ),
			$settings,
			$this->createMock( SessionManager::class ),
			$payments ?? $this->createMock( IPaymentRepository::class ),
			$subs ?? $this->createMock( ISubscriptionRepository::class ),
			new PaymentGatewayFactory( $settings ),
			new PaymentService( $settings )
		);
	}

	private function stubGateway(): IPaymentGateway
	{
		return new class implements IPaymentGateway {
			public function createCheckoutSession( CheckoutSessionRequest $request ): CheckoutSession
			{
				return new CheckoutSession( 'cs_x', 'https://example.test/cs_x' );
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

	public function testExtendsContentController(): void
	{
		$this->assertInstanceOf( Content::class, $this->makeController() );
	}

	public function testExpectedActionsExist(): void
	{
		$controller = $this->makeController();

		foreach( [ 'checkout', 'success', 'cancel', 'webhook', 'token' ] as $action )
		{
			$this->assertTrue( method_exists( $controller, $action ), "missing action: {$action}" );
		}
	}

	public function testCheckoutRoutesAllRequireCsrf(): void
	{
		$attributes = ( new \ReflectionMethod( Payments::class, 'checkout' ) )
			->getAttributes( \Neuron\Routing\Attributes\Post::class );

		$this->assertNotEmpty( $attributes );

		foreach( $attributes as $attribute )
		{
			$this->assertContains( 'csrf', $attribute->getArguments()['filters'] ?? [] );
		}
	}

	public function testWebhookRoutesHaveNoCsrfOrAuthFilter(): void
	{
		$attributes = ( new \ReflectionMethod( Payments::class, 'webhook' ) )
			->getAttributes( \Neuron\Routing\Attributes\Post::class );

		$this->assertNotEmpty( $attributes );

		foreach( $attributes as $attribute )
		{
			$filters = $attribute->getArguments()['filters'] ?? [];
			$this->assertNotContains( 'csrf', $filters, 'webhook must not require a CSRF token' );
			$this->assertNotContains( 'auth', $filters, 'webhook must not require authentication' );
		}
	}

	/**
	 * @dataProvider amountProvider
	 */
	public function testResolveAmount( array $post, ?float $expected, bool $expectError ): void
	{
		$_POST = $post;

		$method = new \ReflectionMethod( Payments::class, 'resolveAmount' );
		[ $amount, $error ] = $method->invoke( $this->makeController(), new Request(), 'general' );

		if( $expectError )
		{
			$this->assertNotNull( $error );
		}
		else
		{
			$this->assertNull( $error );
			$this->assertSame( $expected, $amount );
		}

		$_POST = [];
	}

	public static function amountProvider(): array
	{
		return [
			'preset'         => [ [ 'amount' => '50' ], 50.0, false ],
			'custom'         => [ [ 'amount' => 'custom', 'custom_amount' => '12.50' ], 12.5, false ],
			'below minimum'  => [ [ 'amount' => '2' ], null, true ],
			'missing custom' => [ [ 'amount' => 'custom', 'custom_amount' => '' ], null, true ]
		];
	}

	public function testCollectValuesOnlyConfiguredFields(): void
	{
		$_POST = [ 'name' => '  Bob  ', 'email' => 'bob@example.com', 'evil' => 'x' ];

		$fields = [ [ 'name' => 'name', 'type' => 'text' ], [ 'name' => 'email', 'type' => 'email' ] ];

		$method = new \ReflectionMethod( Payments::class, 'collectValues' );
		$values = $method->invoke( $this->makeController(), $fields, new Request() );

		$this->assertSame( 'Bob', $values['name'] );
		$this->assertSame( 'bob@example.com', $values['email'] );
		$this->assertArrayNotHasKey( 'evil', $values );

		$_POST = [];
	}

	public function testFormatAmountAndFrequencyLabel(): void
	{
		$controller = $this->makeController();

		$format = new \ReflectionMethod( Payments::class, 'formatAmount' );
		$this->assertSame( '$50.00', $format->invoke( $controller, 5000, 'usd' ) );

		$freq = new \ReflectionMethod( Payments::class, 'frequencyLabel' );
		$this->assertSame( 'Monthly', $freq->invoke( $controller, 'monthly' ) );
		$this->assertSame( 'Semi-annually', $freq->invoke( $controller, 'semiannual' ) );
	}

	public function testLocatePaymentPrefersMetadataId(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$repository->method( 'findById' )->with( 5 )->willReturn( [ 'id' => 5, 'form_key' => 'general' ] );
		$repository->expects( $this->never() )->method( 'findBySessionId' );

		$method = new \ReflectionMethod( Payments::class, 'locatePayment' );
		$row = $method->invoke( $this->makeController( $repository ), 5, 'cs_x' );

		$this->assertSame( 5, $row['id'] );
	}

	public function testLocatePaymentFallsBackToSession(): void
	{
		$repository = $this->createMock( IPaymentRepository::class );
		$repository->method( 'findById' )->willReturn( null );
		$repository->method( 'findBySessionId' )->with( 'cs_x' )->willReturn( [ 'id' => 9 ] );

		$method = new \ReflectionMethod( Payments::class, 'locatePayment' );
		$row = $method->invoke( $this->makeController( $repository ), 0, 'cs_x' );

		$this->assertSame( 9, $row['id'] );
	}

	public function testCheckoutCompletedRecurringOpensSubscription(): void
	{
		$pdo      = $this->pdo();
		$payments = $this->repoFrom( DatabasePaymentRepository::class, $pdo );
		$subs     = $this->repoFrom( DatabaseSubscriptionRepository::class, $pdo );

		$paymentId = $payments->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe', 'type' => 'recurring',
			'amount_cents' => 2500, 'currency' => 'usd', 'frequency' => 'monthly', 'status' => 'pending',
			'payer_email' => '', 'payload' => '{}'
		] );

		$controller = $this->makeController( $payments, $subs );

		$event = new WebhookEvent( WebhookEvent::CHECKOUT_COMPLETED, [
			'id'             => 'cs_1',
			'payment_intent' => 'pi_1',
			'subscription'   => 'sub_1',
			'amount_total'   => 2500,
			'metadata'       => [ 'payment_id' => $paymentId, 'form_key' => 'general' ]
		] );

		$method = new \ReflectionMethod( Payments::class, 'handleCheckoutCompleted' );
		$method->invoke( $controller, $event, $this->stubGateway() );

		$payment = $payments->findById( $paymentId );
		$this->assertSame( 'completed', $payment['status'] );
		$this->assertSame( 'sub_1', $payment['subscription_id'] );
		$this->assertSame( 'recurring', $payment['type'] );

		$subscription = $subs->findByGatewayId( 'sub_1' );
		$this->assertNotNull( $subscription );
		$this->assertSame( 'active', $subscription['status'] );
		$this->assertSame( 2500, (int) $subscription['amount_cents'] );
	}

	public function testInvoicePaidRenewalRecordsNewCharge(): void
	{
		$pdo      = $this->pdo();
		$payments = $this->repoFrom( DatabasePaymentRepository::class, $pdo );
		$subs     = $this->repoFrom( DatabaseSubscriptionRepository::class, $pdo );

		// Seed the original (initial) recurring charge + subscription.
		$payments->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe', 'type' => 'recurring',
			'subscription_id' => 'sub_1', 'amount_cents' => 2500, 'currency' => 'usd', 'frequency' => 'monthly',
			'status' => 'completed', 'payer_email' => '', 'payload' => '{}'
		] );
		$subs->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe', 'subscription_id' => 'sub_1',
			'status' => 'active', 'frequency' => 'monthly', 'amount_cents' => 2500, 'currency' => 'usd', 'payload' => '{}'
		] );

		$controller = $this->makeController( $payments, $subs );

		$event = new WebhookEvent( WebhookEvent::INVOICE_PAID, [
			'id'             => 'in_2',
			'subscription'   => 'sub_1',
			'payment_intent' => 'pi_2',
			'amount_paid'    => 2500,
			'billing_reason' => 'subscription_cycle'
		] );

		$method = new \ReflectionMethod( Payments::class, 'handleInvoicePaid' );
		$method->invoke( $controller, $event, $this->stubGateway() );

		$this->assertSame( 2, $payments->paginate( 1, 25, null, null, null )['total'] );
		$renewal = $payments->findByInvoiceId( 'in_2' );
		$this->assertNotNull( $renewal );
		$this->assertSame( 'completed', $renewal['status'] );
		$this->assertSame( 'sub_1', $renewal['subscription_id'] );

		// Idempotency: replaying the same invoice does not create a duplicate.
		$method->invoke( $controller, $event, $this->stubGateway() );
		$this->assertSame( 2, $payments->paginate( 1, 25, null, null, null )['total'] );
	}

	public function testInvoicePaidInitialChargeIsIgnored(): void
	{
		$pdo      = $this->pdo();
		$payments = $this->repoFrom( DatabasePaymentRepository::class, $pdo );
		$subs     = $this->repoFrom( DatabaseSubscriptionRepository::class, $pdo );

		$controller = $this->makeController( $payments, $subs );

		$event = new WebhookEvent( WebhookEvent::INVOICE_PAID, [
			'id'             => 'in_1',
			'subscription'   => 'sub_1',
			'billing_reason' => 'subscription_create'
		] );

		$method = new \ReflectionMethod( Payments::class, 'handleInvoicePaid' );
		$method->invoke( $controller, $event, $this->stubGateway() );

		$this->assertSame( 0, $payments->paginate( 1, 25, null, null, null )['total'] );
	}

	public function testSubscriptionDeletedMarksCanceled(): void
	{
		$pdo  = $this->pdo();
		$subs = $this->repoFrom( DatabaseSubscriptionRepository::class, $pdo );
		$subs->create( [
			'purpose' => 'donation', 'form_key' => 'general', 'provider' => 'stripe', 'subscription_id' => 'sub_1',
			'status' => 'active', 'frequency' => 'monthly', 'amount_cents' => 2500, 'currency' => 'usd', 'payload' => '{}'
		] );

		$controller = $this->makeController( $this->createMock( IPaymentRepository::class ), $subs );

		$event = new WebhookEvent( WebhookEvent::SUBSCRIPTION_DELETED, [ 'id' => 'sub_1', 'status' => 'canceled' ] );

		$method = new \ReflectionMethod( Payments::class, 'handleSubscriptionDeleted' );
		$method->invoke( $controller, $event );

		$subscription = $subs->findByGatewayId( 'sub_1' );
		$this->assertSame( 'canceled', $subscription['status'] );
		$this->assertNotEmpty( $subscription['canceled_at'] );
	}
}
