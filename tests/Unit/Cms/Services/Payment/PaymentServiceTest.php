<?php

namespace Tests\Unit\Cms\Services\Payment;

use Neuron\Cms\Services\Payment\PaymentService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use PHPUnit\Framework\TestCase;

class PaymentServiceTest extends TestCase
{
	private function service( array $settings ): PaymentService
	{
		return new PaymentService( new SettingManager( new Memory( $settings ) ) );
	}

	private function paymentsConfig(): array
	{
		return [
			'system'   => [ 'base_path' => sys_get_temp_dir() ],
			'payments' => [
				'currency'     => 'usd',
				'default_form' => 'general',
				'success_url'  => '/payments/success',
				'cancel_url'   => '/payments/cancel',
				'forms'        => [
					'general' => [
						'purpose'             => 'donation',
						'label'               => 'Donate',
						'to'                  => 'info@example.com',
						'amounts'             => [ 25, 50, 100 ],
						'allow_custom_amount' => true,
						'min_amount'          => 5,
						'frequencies'         => [ 'one_time', 'monthly' ],
						'fields'              => [
							[ 'name' => 'name', 'type' => 'text', 'sender_name' => true ],
							[ 'name' => 'email', 'type' => 'email', 'reply_to' => true ]
						]
					],
					'membership' => [
						'purpose' => 'membership',
						'label'   => 'Join',
						'to'      => 'members@example.com'
					]
				]
			]
		];
	}

	public function testDefaultFormAndConfig(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$this->assertSame( 'general', $service->getDefaultFormKey() );
		$this->assertNotNull( $service->getFormConfig( 'general' ) );
		$this->assertNull( $service->getFormConfig( 'missing' ) );
	}

	public function testPurposeDefaultsToDonation(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$this->assertSame( 'donation', $service->purpose( 'general' ) );
		$this->assertSame( 'membership', $service->purpose( 'membership' ) );
		$this->assertSame( 'donation', $service->purpose( 'missing' ) );
	}

	public function testUrlsAndCurrency(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$this->assertSame( '/payments/success', $service->getSuccessUrl() );
		$this->assertSame( '/payments/cancel', $service->getCancelUrl() );
		$this->assertSame( 'usd', $service->getCurrency() );
	}

	public function testAmountConfig(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$this->assertSame( [ 25.0, 50.0, 100.0 ], $service->presetAmounts( 'general' ) );
		$this->assertSame( 5.0, $service->minimumAmount( 'general' ) );
		$this->assertTrue( $service->allowsCustomAmount( 'general' ) );
	}

	public function testAllowedFrequenciesDefaultsToOneTime(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$this->assertSame( [ 'one_time', 'monthly' ], $service->allowedFrequencies( 'general' ) );
		$this->assertSame( [ 'one_time' ], $service->allowedFrequencies( 'missing' ) );
	}

	public function testResolvePayerUsesRoleFlags(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$fields = $service->getFields( 'general' );
		$values = [ 'name' => 'Alice', 'email' => 'alice@example.com' ];

		$payer = $service->resolvePayer( $fields, $values );

		$this->assertSame( 'alice@example.com', $payer['email'] );
		$this->assertSame( 'Alice', $payer['name'] );
	}

	public function testResolvePayerJoinsSplitNameFields(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$fields = [
			[ 'name' => 'first_name', 'type' => 'text', 'sender_name' => true ],
			[ 'name' => 'last_name', 'type' => 'text', 'sender_name' => true ],
			[ 'name' => 'email', 'type' => 'email', 'reply_to' => true ]
		];

		$values = [ 'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com' ];

		$payer = $service->resolvePayer( $fields, $values );

		$this->assertSame( 'jane@example.com', $payer['email'] );
		$this->assertSame( 'Jane Doe', $payer['name'] );
	}

	public function testResolvePayerSkipsEmptySplitNameParts(): void
	{
		$service = $this->service( $this->paymentsConfig() );

		$fields = [
			[ 'name' => 'first_name', 'type' => 'text', 'sender_name' => true ],
			[ 'name' => 'last_name', 'type' => 'text', 'sender_name' => true ]
		];

		$payer = $service->resolvePayer( $fields, [ 'first_name' => 'Jane', 'last_name' => '' ] );

		$this->assertSame( 'Jane', $payer['name'] );
	}

	public function testLegacyDonationsSectionIsStillRead(): void
	{
		$service = $this->service( [
			'system'   => [ 'base_path' => sys_get_temp_dir() ],
			'payments' => [ 'currency' => 'usd' ],
			'donations' => [
				'default_form' => 'general',
				'success_url'  => '/donations/success',
				'forms'        => [
					'general' => [ 'label' => 'Donate', 'to' => 'info@example.com' ]
				]
			]
		] );

		$this->assertSame( 'general', $service->getDefaultFormKey() );
		$this->assertNotNull( $service->getFormConfig( 'general' ) );
		$this->assertSame( '/donations/success', $service->getSuccessUrl() );
		$this->assertSame( 'donation', $service->purpose( 'general' ) );
	}
}
