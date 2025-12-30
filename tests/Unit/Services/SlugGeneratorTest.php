<?php

namespace Tests\Unit\Services;

use Neuron\Cms\Services\SlugGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Test SlugGenerator Service
 *
 * @package Tests\Unit\Services
 */
class SlugGeneratorTest extends TestCase
{
	private SlugGenerator $_generator;

	protected function setUp(): void
	{
		parent::setUp();
		$this->_generator = new SlugGenerator();
	}

	public function testGenerateBasicSlug()
	{
		$slug = $this->_generator->generate( 'Hello World' );
		$this->assertEquals( 'hello-world', $slug );
	}

	public function testGenerateWithSpecialCharacters()
	{
		$slug = $this->_generator->generate( 'Hello, World! How are you?' );
		$this->assertEquals( 'hello-world-how-are-you', $slug );
	}

	public function testGenerateWithMultipleSpaces()
	{
		$slug = $this->_generator->generate( 'Hello    World' );
		$this->assertEquals( 'hello-world', $slug );
	}

	public function testGenerateWithLeadingTrailingSpaces()
	{
		$slug = $this->_generator->generate( '  Hello World  ' );
		$this->assertEquals( 'hello-world', $slug );
	}

	public function testGenerateWithNumbers()
	{
		$slug = $this->_generator->generate( 'PHP 8.4 Features' );
		$this->assertEquals( 'php-8-4-features', $slug );
	}

	public function testGenerateWithHyphens()
	{
		$slug = $this->_generator->generate( 'Hello-World' );
		$this->assertEquals( 'hello-world', $slug );
	}

	public function testGenerateWithConsecutiveHyphens()
	{
		$slug = $this->_generator->generate( 'Hello---World' );
		$this->assertEquals( 'hello-world', $slug );
	}

	public function testGenerateWithUnderscores()
	{
		$slug = $this->_generator->generate( 'hello_world_test' );
		$this->assertEquals( 'hello-world-test', $slug );
	}

	public function testGenerateWithMixedCase()
	{
		$slug = $this->_generator->generate( 'HeLLo WoRLd' );
		$this->assertEquals( 'hello-world', $slug );
	}

	public function testGenerateWithUnicode()
	{
		// Non-ASCII characters should be replaced with hyphens
		$slug = $this->_generator->generate( 'Café Restaurant' );
		$this->assertEquals( 'caf-restaurant', $slug );
	}

	public function testGenerateWithChineseCharacters()
	{
		// Should fallback to unique ID since no ASCII characters
		$slug = $this->_generator->generate( '你好世界' );

		// Should match pattern: item-{uniqid}
		$this->assertMatchesRegularExpression( '/^item-[a-z0-9]+$/', $slug );
	}

	public function testGenerateWithArabicCharacters()
	{
		// Should fallback to unique ID
		$slug = $this->_generator->generate( 'مرحبا بالعالم' );
		$this->assertMatchesRegularExpression( '/^item-[a-z0-9]+$/', $slug );
	}

	public function testGenerateWithCustomFallbackPrefix()
	{
		$slug = $this->_generator->generate( '你好', 'post' );
		$this->assertMatchesRegularExpression( '/^post-[a-z0-9]+$/', $slug );
	}

	public function testGenerateWithEmptyString()
	{
		$slug = $this->_generator->generate( '' );
		$this->assertMatchesRegularExpression( '/^item-[a-z0-9]+$/', $slug );
	}

	public function testGenerateWithOnlySpecialCharacters()
	{
		$slug = $this->_generator->generate( '!@#$%^&*()' );
		$this->assertMatchesRegularExpression( '/^item-[a-z0-9]+$/', $slug );
	}

	public function testGenerateUnique()
	{
		$existingSlugs = ['hello-world', 'hello-world-2'];

		$callback = function( $slug ) use ( $existingSlugs ) {
			return in_array( $slug, $existingSlugs );
		};

		$slug = $this->_generator->generateUnique( 'Hello World', $callback );
		$this->assertEquals( 'hello-world-3', $slug );
	}

	public function testGenerateUniqueWithNoConflict()
	{
		$callback = function( $slug ) {
			return false; // No conflicts
		};

		$slug = $this->_generator->generateUnique( 'Hello World', $callback );
		$this->assertEquals( 'hello-world', $slug );
	}

	public function testGenerateUniqueWithFirstConflict()
	{
		$callback = function( $slug ) {
			return $slug === 'hello-world'; // Only first one exists
		};

		$slug = $this->_generator->generateUnique( 'Hello World', $callback );
		$this->assertEquals( 'hello-world-2', $slug );
	}

	public function testIsValidWithValidSlug()
	{
		$this->assertTrue( $this->_generator->isValid( 'hello-world' ) );
		$this->assertTrue( $this->_generator->isValid( 'hello-world-123' ) );
		$this->assertTrue( $this->_generator->isValid( 'php-8-features' ) );
		$this->assertTrue( $this->_generator->isValid( 'a' ) );
		$this->assertTrue( $this->_generator->isValid( 'a-b-c-d-e' ) );
	}

	public function testIsValidWithInvalidSlug()
	{
		$this->assertFalse( $this->_generator->isValid( '' ) );
		$this->assertFalse( $this->_generator->isValid( '-hello-world' ) ); // Starts with hyphen
		$this->assertFalse( $this->_generator->isValid( 'hello-world-' ) ); // Ends with hyphen
		$this->assertFalse( $this->_generator->isValid( 'hello--world' ) ); // Consecutive hyphens
		$this->assertFalse( $this->_generator->isValid( 'Hello-World' ) ); // Uppercase
		$this->assertFalse( $this->_generator->isValid( 'hello world' ) ); // Spaces
		$this->assertFalse( $this->_generator->isValid( 'hello_world' ) ); // Underscore
		$this->assertFalse( $this->_generator->isValid( 'hello.world' ) ); // Period
	}

	public function testCleanSlug()
	{
		$cleaned = $this->_generator->clean( 'Hello World!' );
		$this->assertEquals( 'hello-world', $cleaned );
	}

	public function testCleanInvalidSlug()
	{
		$cleaned = $this->_generator->clean( '-hello--world-' );
		$this->assertEquals( 'hello-world', $cleaned );
	}

	public function testCleanEmptySlug()
	{
		$cleaned = $this->_generator->clean( '!@#$' );
		$this->assertMatchesRegularExpression( '/^item-[a-z0-9]+$/', $cleaned );
	}

	public function testCleanWithCustomPrefix()
	{
		$cleaned = $this->_generator->clean( '!@#$', 'page' );
		$this->assertMatchesRegularExpression( '/^page-[a-z0-9]+$/', $cleaned );
	}

	public function testGenerateWithVeryLongText()
	{
		$longText = str_repeat( 'hello world ', 50 );
		$slug = $this->_generator->generate( $longText );

		// Should contain 'hello-world' repeated
		$this->assertStringContainsString( 'hello-world', $slug );

		// Should not contain spaces
		$this->assertStringNotContainsString( ' ', $slug );
	}

	public function testGeneratePreservesExistingHyphens()
	{
		$slug = $this->_generator->generate( 'pre-existing-hyphens' );
		$this->assertEquals( 'pre-existing-hyphens', $slug );
	}

	public function testGenerateWithMixedAlphanumeric()
	{
		$slug = $this->_generator->generate( 'ABC123xyz456' );
		$this->assertEquals( 'abc123xyz456', $slug );
	}
}
