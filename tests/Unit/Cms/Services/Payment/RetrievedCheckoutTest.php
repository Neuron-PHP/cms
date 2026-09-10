<?php

namespace Tests\Unit\Cms\Services\Payment;

use Neuron\Cms\Services\Payment\RetrievedCheckout;
use PHPUnit\Framework\TestCase;

class RetrievedCheckoutTest extends TestCase
{
	public function testFromArrayMapsSnakeAndCamelKeys(): void
	{
		$session = RetrievedCheckout::from( [
			'id'             => 'cs_1',
			'status'         => 'complete',
			'payment_status' => 'paid',
			'payment_intent' => 'pi_1',
			'amount_total'   => 2500
		] );

		$this->assertTrue( $session->isPaid() );
		$this->assertFalse( $session->isExpired() );
		$this->assertSame( 'pi_1', $session->paymentIntentId );
		$this->assertSame( 2500, $session->amountTotal );
	}

	public function testFromObjectReadsPublicProperties(): void
	{
		$raw = (object) [
			'id'             => 'cs_2',
			'status'         => 'expired',
			'payment_status' => 'unpaid'
		];

		$session = RetrievedCheckout::from( $raw );

		$this->assertTrue( $session->isExpired() );
		$this->assertFalse( $session->isPaid() );
	}

	public function testFromIgnoresMissingProperties(): void
	{
		$session = RetrievedCheckout::from( (object) [ 'id' => 'cs_4', 'url' => 'https://example.test' ] );

		$this->assertSame( 'cs_4', $session->id );
		$this->assertSame( '', $session->status );
		$this->assertFalse( $session->isPaid() );
	}

	public function testNoPaymentRequiredCountsAsPaid(): void
	{
		$session = new RetrievedCheckout( 'cs_3', 'complete', 'no_payment_required' );

		$this->assertTrue( $session->isPaid() );
	}
}
