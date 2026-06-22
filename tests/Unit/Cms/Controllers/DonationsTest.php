<?php

namespace Tests\Unit\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Controllers\Donations;
use Neuron\Cms\Repositories\IDonationRepository;
use Neuron\Cms\Services\Donation\DonationService;
use Neuron\Cms\Services\Donation\PaymentGatewayFactory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use PHPUnit\Framework\TestCase;

class DonationsTest extends TestCase
{
	private function settings(): SettingManager
	{
		return new SettingManager( new Memory( [
			'site'     => [ 'name' => 'Test Site' ],
			'payments' => [ 'provider' => 'stripe', 'currency' => 'usd' ],
			'donations' => [
				'default_form' => 'general',
				'forms'        => [
					'general' => [
						'label'               => 'Donate',
						'to'                  => 'info@example.com',
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

	private function makeController( ?IDonationRepository $repository = null ): Donations
	{
		$settings = $this->settings();

		return new Donations(
			$this->createMock( IMvcApplication::class ),
			$settings,
			$this->createMock( SessionManager::class ),
			$repository ?? $this->createMock( IDonationRepository::class ),
			new PaymentGatewayFactory( $settings ),
			new DonationService( $settings )
		);
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

	public function testCheckoutRouteHasCsrfFilter(): void
	{
		$attributes = ( new \ReflectionMethod( Donations::class, 'checkout' ) )
			->getAttributes( \Neuron\Routing\Attributes\Post::class );

		$this->assertNotEmpty( $attributes );
		$this->assertContains( 'csrf', $attributes[0]->getArguments()['filters'] ?? [] );
	}

	public function testWebhookRouteHasNoCsrfFilter(): void
	{
		$attributes = ( new \ReflectionMethod( Donations::class, 'webhook' ) )
			->getAttributes( \Neuron\Routing\Attributes\Post::class );

		$this->assertNotEmpty( $attributes );

		$filters = $attributes[0]->getArguments()['filters'] ?? [];

		$this->assertNotContains( 'csrf', $filters, 'webhook must not require a CSRF token' );
		$this->assertNotContains( 'auth', $filters, 'webhook must not require authentication' );
	}

	/**
	 * @dataProvider amountProvider
	 */
	public function testResolveAmount( array $post, ?float $expected, bool $expectError ): void
	{
		$_POST = $post;

		$method = new \ReflectionMethod( Donations::class, 'resolveAmount' );
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

		$method = new \ReflectionMethod( Donations::class, 'collectValues' );
		$values = $method->invoke( $this->makeController(), $fields, new Request() );

		$this->assertSame( 'Bob', $values['name'] );
		$this->assertSame( 'bob@example.com', $values['email'] );
		$this->assertArrayNotHasKey( 'evil', $values );

		$_POST = [];
	}

	public function testFormatAmountAndFrequencyLabel(): void
	{
		$controller = $this->makeController();

		$format = new \ReflectionMethod( Donations::class, 'formatAmount' );
		$this->assertSame( '$50.00', $format->invoke( $controller, 5000, 'usd' ) );

		$freq = new \ReflectionMethod( Donations::class, 'frequencyLabel' );
		$this->assertSame( 'Monthly', $freq->invoke( $controller, 'monthly' ) );
		$this->assertSame( 'Semi-annually', $freq->invoke( $controller, 'semiannual' ) );
	}

	public function testLocateDonationPrefersMetadataId(): void
	{
		$repository = $this->createMock( IDonationRepository::class );
		$repository->method( 'findById' )->with( 5 )->willReturn( [ 'id' => 5, 'form_key' => 'general' ] );
		$repository->expects( $this->never() )->method( 'findBySessionId' );

		$method = new \ReflectionMethod( Donations::class, 'locateDonation' );
		$row = $method->invoke( $this->makeController( $repository ), 5, 'cs_x' );

		$this->assertSame( 5, $row['id'] );
	}

	public function testLocateDonationFallsBackToSession(): void
	{
		$repository = $this->createMock( IDonationRepository::class );
		$repository->method( 'findById' )->willReturn( null );
		$repository->method( 'findBySessionId' )->with( 'cs_x' )->willReturn( [ 'id' => 9 ] );

		$method = new \ReflectionMethod( Donations::class, 'locateDonation' );
		$row = $method->invoke( $this->makeController( $repository ), 0, 'cs_x' );

		$this->assertSame( 9, $row['id'] );
	}
}
