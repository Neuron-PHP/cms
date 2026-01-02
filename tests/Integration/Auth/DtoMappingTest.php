<?php

namespace Tests\Integration\Auth;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for DTO mapping from HTTP requests
 *
 * Tests the actual request -> DTO mapping that happens in controllers
 * This would have caught the "Cannot use object of type Property as array" bug
 */
class DtoMappingTest extends TestCase
{
	private Content $controller;

	protected function setUp(): void
	{
		parent::setUp();

		// Setup mock dependencies
		$mockApp = $this->createMock( IMvcApplication::class );
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturn( 'Test Site' );
		$mockSession = $this->createMock( SessionManager::class );

		Registry::getInstance()->set( 'Settings', $mockSettings );

		// Create anonymous class extending Content to access protected methods
		$this->controller = new class( $mockApp, $mockSettings, $mockSession ) extends Content {
			public function testMapRequestToDto( \Neuron\Dto\Dto $dto, Request $request ): void
			{
				$this->mapRequestToDto( $dto, $request );
			}

			public function testCreateDto( string $config ): \Neuron\Dto\Dto
			{
				return $this->createDto( $config );
			}
		};
	}

	/**
	 * Test that boolean checkbox values are correctly converted
	 *
	 * This tests the bug where checkbox "on" value wasn't converted to boolean
	 * WITHOUT this fix, this test would fail with: "Cannot use object of type Property as array"
	 */
	public function testMapRequestToDtoConvertsBooleanCheckboxValues(): void
	{
		// Create a DTO from the actual login YAML config
		$dto = $this->controller->testCreateDto( 'auth/login-request.yaml' );

		// Mock request with checkbox value "on" (actual HTML checkbox behavior)
		$mockRequest = $this->createMock( Request::class );
		$mockRequest
			->method( 'post' )
			->willReturnCallback( function( $name, $default = null ) {
				$formData = [
					'username' => 'testuser',
					'password' => 'password123',
					'remember' => 'on'  // Actual checkbox value
				];
				return $formData[$name] ?? $default;
			});

		// Map request to DTO - this should NOT throw "Cannot use object of type Property as array"
		// The bug was accessing $property['type'] instead of $property->getType()
		$this->controller->testMapRequestToDto( $dto, $mockRequest );

		// Verify the values were mapped correctly
		$this->assertEquals( 'testuser', $dto->username );
		$this->assertEquals( 'password123', $dto->password );
		$this->assertTrue( $dto->remember, 'Checkbox value "on" should be converted to boolean true' );
	}

	/**
	 * Test mapping with various checkbox values (1, "1", true, "on")
	 */
	public function testMapRequestToDtoHandlesVariousCheckboxValues(): void
	{
		$testCases = [
			'on' => true,
			'1' => true,
			1 => true,
			true => true,
			'off' => false,
			'0' => false,
			0 => false,
			false => false,
		];

		foreach( $testCases as $inputValue => $expectedBoolean )
		{
			$dto = $this->controller->testCreateDto( 'auth/login-request.yaml' );

			$mockRequest = $this->createMock( Request::class );
			$mockRequest
				->method( 'post' )
				->willReturnCallback( function( $name, $default = null ) use ( $inputValue ) {
					$formData = [
						'username' => 'testuser',
						'password' => 'password123',
						'remember' => $inputValue
					];
					return $formData[$name] ?? $default;
				});

			$this->controller->testMapRequestToDto( $dto, $mockRequest );

			$this->assertSame(
				$expectedBoolean,
				$dto->remember,
				"Checkbox value " . var_export( $inputValue, true ) . " should convert to " . var_export( $expectedBoolean, true )
			);
		}
	}

	/**
	 * Test that Property objects are correctly accessed (not as arrays)
	 *
	 * This directly tests the bug fix
	 */
	public function testDtoPropertiesAreObjectsNotArrays(): void
	{
		$dto = $this->controller->testCreateDto( 'auth/login-request.yaml' );

		// Verify getProperties() returns Property objects
		$properties = $dto->getProperties();
		$this->assertIsArray( $properties );

		foreach( $properties as $name => $property )
		{
			$this->assertInstanceOf(
				\Neuron\Dto\Property::class,
				$property,
				"Property '$name' should be a Property object, not an array"
			);

			// Verify we can call getType() method (not access as array)
			$this->assertIsString(
				$property->getType(),
				"Property '$name' should have getType() method"
			);
		}
	}

	protected function tearDown(): void
	{
		parent::tearDown();
	}
}
