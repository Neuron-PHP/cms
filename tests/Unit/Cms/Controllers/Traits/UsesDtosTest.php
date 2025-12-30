<?php

namespace Tests\Cms\Controllers\Traits;

use Neuron\Cms\Controllers\Traits\UsesDtos;
use Neuron\Cms\Services\Dto\DtoFactoryService;
use Neuron\Dto\Dto;
use Neuron\Dto\Mapper\Request as RequestMapper;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Container\IContainer;
use PHPUnit\Framework\TestCase;

class UsesDtosTest extends TestCase
{
	private $controller;
	private $mockApp;
	private $mockContainer;
	private $mockDtoFactory;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mocks
		$this->mockContainer = $this->createMock( IContainer::class );
		$this->mockApp = $this->createMock( Application::class );
		$this->mockDtoFactory = $this->createMock( DtoFactoryService::class );

		// Setup container to return DtoFactory
		$this->mockContainer
			->method( 'get' )
			->with( DtoFactoryService::class )
			->willReturn( $this->mockDtoFactory );

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );

		// Create concrete class using trait
		$this->controller = new class( $this->mockApp ) {
			use UsesDtos;

			private Application $app;

			public function __construct( Application $app )
			{
				$this->app = $app;
			}

			public function getApplication(): Application
			{
				return $this->app;
			}

			// Expose protected methods for testing
			public function testGetDtoFactory(): DtoFactoryService
			{
				return $this->getDtoFactory();
			}

			public function testPopulateDtoFromRequest( Dto $dto, Request $request, array $fields = [] ): Dto
			{
				return $this->populateDtoFromRequest( $dto, $request, $fields );
			}

			public function testValidateDto( Dto $dto ): array
			{
				return $this->validateDto( $dto );
			}

			public function testValidateDtoOrFail( Dto $dto ): void
			{
				$this->validateDtoOrFail( $dto );
			}

			public function testCreateDtoFromRequest( string $name, Request $request, array $fields = [] ): Dto
			{
				return $this->createDtoFromRequest( $name, $request, $fields );
			}
		};
	}

	public function testGetDtoFactory(): void
	{
		$factory = $this->controller->testGetDtoFactory();

		$this->assertInstanceOf( DtoFactoryService::class, $factory );
		$this->assertSame( $this->mockDtoFactory, $factory );
	}

	public function testPopulateDtoFromRequestWithoutFields(): void
	{
		// Create a mock DTO
		$mockDto = $this->createMock( Dto::class );
		$mockRequest = $this->createMock( Request::class );

		// The actual population happens in RequestMapper, which we can't fully test here
		// But we can verify it returns a DTO
		$result = $this->controller->testPopulateDtoFromRequest( $mockDto, $mockRequest );

		$this->assertInstanceOf( Dto::class, $result );
	}

	public function testPopulateDtoFromRequestWithFields(): void
	{
		$mockDto = $this->createMock( Dto::class );
		$mockRequest = $this->createMock( Request::class );
		$fields = ['name', 'email'];

		$result = $this->controller->testPopulateDtoFromRequest( $mockDto, $mockRequest, $fields );

		$this->assertInstanceOf( Dto::class, $result );
	}

	public function testValidateDtoWithNoErrors(): void
	{
		$mockDto = $this->createMock( Dto::class );
		$mockDto->method( 'getErrors' )->willReturn( [] );

		$errors = $this->controller->testValidateDto( $mockDto );

		$this->assertIsArray( $errors );
		$this->assertEmpty( $errors );
	}

	public function testValidateDtoWithErrors(): void
	{
		$mockDto = $this->createMock( Dto::class );
		$mockDto->method( 'validate' )->willThrowException(
			new \Neuron\Core\Exceptions\Validation( 'dto', [
				'name' => ['Name is required'],
				'email' => ['Email is invalid']
			])
		);
		$mockDto->method( 'getErrors' )->willReturn([
			'name' => ['Name is required'],
			'email' => ['Email is invalid']
		]);

		$errors = $this->controller->testValidateDto( $mockDto );

		$this->assertIsArray( $errors );
		$this->assertArrayHasKey( 'name', $errors );
		$this->assertArrayHasKey( 'email', $errors );
	}

	public function testValidateDtoOrFailWithNoErrors(): void
	{
		$mockDto = $this->createMock( Dto::class );
		$mockDto->method( 'getErrors' )->willReturn( [] );

		// Should not throw exception
		$this->controller->testValidateDtoOrFail( $mockDto );

		$this->assertTrue( true ); // If we get here, test passed
	}

	public function testValidateDtoOrFailWithErrors(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'name: Name is required, email: Email is invalid' );

		$mockDto = $this->createMock( Dto::class );
		$mockDto->method( 'validate' )->willThrowException(
			new \Neuron\Core\Exceptions\Validation( 'dto', [
				'name' => ['Name is required'],
				'email' => ['Email is invalid']
			])
		);
		$mockDto->method( 'getErrors' )->willReturn([
			'name' => ['Name is required'],
			'email' => ['Email is invalid']
		]);

		$this->controller->testValidateDtoOrFail( $mockDto );
	}

	public function testCreateDtoFromRequest(): void
	{
		$mockDto = $this->createMock( Dto::class );
		$mockRequest = $this->createMock( Request::class );

		$this->mockDtoFactory
			->expects( $this->once() )
			->method( 'create' )
			->with( 'TestDto' )
			->willReturn( $mockDto );

		$result = $this->controller->testCreateDtoFromRequest( 'TestDto', $mockRequest );

		$this->assertInstanceOf( Dto::class, $result );
	}

	public function testCreateDtoFromRequestWithFields(): void
	{
		$mockDto = $this->createMock( Dto::class );
		$mockRequest = $this->createMock( Request::class );
		$fields = ['name', 'email'];

		$this->mockDtoFactory
			->expects( $this->once() )
			->method( 'create' )
			->with( 'TestDto' )
			->willReturn( $mockDto );

		$result = $this->controller->testCreateDtoFromRequest( 'TestDto', $mockRequest, $fields );

		$this->assertInstanceOf( Dto::class, $result );
	}
}
