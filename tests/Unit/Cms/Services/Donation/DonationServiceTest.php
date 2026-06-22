<?php

namespace Tests\Unit\Cms\Services\Donation;

use Neuron\Cms\Services\Donation\DonationService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use PHPUnit\Framework\TestCase;

class DonationServiceTest extends TestCase
{
	private DonationService $service;

	protected function setUp(): void
	{
		parent::setUp();

		$settings = new SettingManager( new Memory( [
			'system'   => [ 'base_path' => sys_get_temp_dir() ],
			'payments' => [ 'currency' => 'usd' ],
			'donations' => [
				'default_form' => 'general',
				'success_url'  => '/donations/success',
				'cancel_url'   => '/donations/cancel',
				'forms'        => [
					'general' => [
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
					]
				]
			]
		] ) );

		$this->service = new DonationService( $settings );
	}

	public function testDefaultFormAndConfig(): void
	{
		$this->assertSame( 'general', $this->service->getDefaultFormKey() );
		$this->assertNotNull( $this->service->getFormConfig( 'general' ) );
		$this->assertNull( $this->service->getFormConfig( 'missing' ) );
	}

	public function testUrlsAndCurrency(): void
	{
		$this->assertSame( '/donations/success', $this->service->getSuccessUrl() );
		$this->assertSame( '/donations/cancel', $this->service->getCancelUrl() );
		$this->assertSame( 'usd', $this->service->getCurrency() );
	}

	public function testAmountConfig(): void
	{
		$this->assertSame( [ 25.0, 50.0, 100.0 ], $this->service->presetAmounts( 'general' ) );
		$this->assertSame( 5.0, $this->service->minimumAmount( 'general' ) );
		$this->assertTrue( $this->service->allowsCustomAmount( 'general' ) );
	}

	public function testAllowedFrequenciesDefaultsToOneTime(): void
	{
		$this->assertSame( [ 'one_time', 'monthly' ], $this->service->allowedFrequencies( 'general' ) );
		$this->assertSame( [ 'one_time' ], $this->service->allowedFrequencies( 'missing' ) );
	}

	public function testResolveDonorUsesRoleFlags(): void
	{
		$fields = $this->service->getFields( 'general' );
		$values = [ 'name' => 'Alice', 'email' => 'alice@example.com' ];

		$donor = $this->service->resolveDonor( $fields, $values );

		$this->assertSame( 'alice@example.com', $donor['email'] );
		$this->assertSame( 'Alice', $donor['name'] );
	}
}
