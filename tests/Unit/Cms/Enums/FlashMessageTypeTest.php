<?php

namespace Tests\Unit\Cms\Enums;

use Neuron\Cms\Enums\FlashMessageType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FlashMessageType enum
 *
 * @package Tests\Unit\Cms\Enums
 */
class FlashMessageTypeTest extends TestCase
{
	/**
	 * Test that viewKey() returns capitalized version of the enum value
	 *
	 * The viewKey() method provides consistent view variable names
	 * (e.g., $Success, $Error) for use in templates.
	 */
	public function testViewKeyReturnsCapitalizedValue(): void
	{
		$this->assertEquals( 'Success', FlashMessageType::SUCCESS->viewKey() );
		$this->assertEquals( 'Error', FlashMessageType::ERROR->viewKey() );
		$this->assertEquals( 'Warning', FlashMessageType::WARNING->viewKey() );
		$this->assertEquals( 'Info', FlashMessageType::INFO->viewKey() );
	}

	/**
	 * Test that enum values remain lowercase for session storage
	 *
	 * The value property should remain lowercase for consistency
	 * with session flash key storage.
	 */
	public function testEnumValuesAreLowercase(): void
	{
		$this->assertEquals( 'success', FlashMessageType::SUCCESS->value );
		$this->assertEquals( 'error', FlashMessageType::ERROR->value );
		$this->assertEquals( 'warning', FlashMessageType::WARNING->value );
		$this->assertEquals( 'info', FlashMessageType::INFO->value );
	}

	/**
	 * Test that all enum cases exist
	 *
	 * Ensures all expected flash message types are defined.
	 */
	public function testAllCasesExist(): void
	{
		$cases = FlashMessageType::cases();
		$this->assertCount( 4, $cases );

		$values = array_map( fn( $case ) => $case->value, $cases );
		$this->assertContains( 'success', $values );
		$this->assertContains( 'error', $values );
		$this->assertContains( 'warning', $values );
		$this->assertContains( 'info', $values );
	}
}
