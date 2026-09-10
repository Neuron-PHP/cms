<?php

namespace Tests\Unit\Cms\Services\View;

use Neuron\Cms\Services\View\PayloadFormatter;
use PHPUnit\Framework\TestCase;

class PayloadFormatterTest extends TestCase
{
	public function testFormatsScalar(): void
	{
		$this->assertSame( 'Alice', PayloadFormatter::format( 'Alice' ) );
		$this->assertSame( 'Yes', PayloadFormatter::format( true ) );
		$this->assertSame( 'No', PayloadFormatter::format( false ) );
		$this->assertSame( '', PayloadFormatter::format( null ) );
	}

	public function testFormatsListOfScalars(): void
	{
		$this->assertSame( "red\nblue", PayloadFormatter::format( [ 'red', 'blue' ] ) );
	}

	public function testFormatsNestedLineItemsWithoutWarning(): void
	{
		$value = [
			[
				'name'              => 'T-Shirt',
				'sku'               => 'TEE-1',
				'quantity'          => 2,
				'unit_amount_cents' => 1500
			],
			[
				'name'              => 'Sticker',
				'sku'               => '',
				'quantity'          => 1,
				'unit_amount_cents' => 500
			]
		];

		$this->assertSame(
			"T-Shirt (TEE-1) × 2 — $30.00\nSticker — $5.00",
			PayloadFormatter::format( $value )
		);
	}

	public function testFormatsAssociativeScalars(): void
	{
		$this->assertSame(
			'name: Alice, email: a@example.com',
			PayloadFormatter::format( [ 'name' => 'Alice', 'email' => 'a@example.com' ] )
		);
	}
}
