<?php

namespace Tests\Unit\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Ini;
use Neuron\Mvc\Application;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test that all DTOs can be loaded successfully by controllers.
 *
 * This test ensures that:
 * - All DTO YAML files are in the correct location (src/Cms/Dtos/)
 * - All DTOs have valid YAML syntax
 * - The createDto() method can successfully load each DTO
 * - No broken references exist in controller code
 */
class DtoLoadingTest extends TestCase
{
	private Content $controller;
	private ReflectionMethod $createDtoMethod;

	protected function setUp(): void
	{
		parent::setUp();

		// Create minimal application for testing
		$settings = $this->createMock( SettingManager::class );
		$app = $this->createMock( Application::class );
		$app->method( 'getSettingManager' )->willReturn( $settings );
		$app->method( 'getBasePath' )->willReturn( '/var/www' );

		$sessionManager = $this->createMock( SessionManager::class );

		// Create anonymous controller instance to test createDto
		$this->controller = new class($app, $settings, $sessionManager) extends Content {
			public function index(): string
			{
				return '';
			}
		};

		// Make createDto accessible via reflection
		$reflection = new ReflectionClass( $this->controller );
		$this->createDtoMethod = $reflection->getMethod( 'createDto' );
		$this->createDtoMethod->setAccessible( true );
	}

	/**
	 * Test all auth DTOs can be loaded
	 */
	public function testAuthDtosCanBeLoaded(): void
	{
		$dtos = [
			'auth/forgot-password-request.yaml',
			'auth/login-request.yaml',
			'auth/reset-password-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test all category DTOs can be loaded
	 */
	public function testCategoryDtosCanBeLoaded(): void
	{
		$dtos = [
			'categories/create-category-request.yaml',
			'categories/update-category-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test all event DTOs can be loaded
	 */
	public function testEventDtosCanBeLoaded(): void
	{
		$dtos = [
			'events/create-event-request.yaml',
			'events/update-event-request.yaml',
			'event-categories/create-event-category-request.yaml',
			'event-categories/update-event-category-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test all page DTOs can be loaded
	 */
	public function testPageDtosCanBeLoaded(): void
	{
		$dtos = [
			'pages/create-page-request.yaml',
			'pages/update-page-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test all post DTOs can be loaded
	 */
	public function testPostDtosCanBeLoaded(): void
	{
		$dtos = [
			'posts/create-post-request.yaml',
			'posts/update-post-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test all tag DTOs can be loaded
	 */
	public function testTagDtosCanBeLoaded(): void
	{
		$dtos = [
			'tags/create-tag-request.yaml',
			'tags/update-tag-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test all user DTOs can be loaded
	 */
	public function testUserDtosCanBeLoaded(): void
	{
		$dtos = [
			'users/create-user-request.yaml',
			'users/update-user-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test all member DTOs can be loaded
	 */
	public function testMemberDtosCanBeLoaded(): void
	{
		$dtos = [
			'members/update-profile-request.yaml',
		];

		foreach( $dtos as $dtoPath )
		{
			$dto = $this->createDtoMethod->invoke( $this->controller, $dtoPath );
			$this->assertInstanceOf( \Neuron\Dto\Dto::class, $dto, "Failed to load DTO: {$dtoPath}" );
		}
	}

	/**
	 * Test that attempting to load a non-existent DTO throws an exception
	 */
	public function testNonExistentDtoThrowsException(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'DTO configuration file not found' );

		$this->createDtoMethod->invoke( $this->controller, 'nonexistent/dto.yaml' );
	}

	/**
	 * Verify all DTO files physically exist in src/Cms/Dtos/
	 */
	public function testAllDtoFilesExist(): void
	{
		$basePath = __DIR__ . '/../../../../src/Cms/Dtos';

		$expectedDtos = [
			'auth/forgot-password-request.yaml',
			'auth/login-request.yaml',
			'auth/reset-password-request.yaml',
			'categories/create-category-request.yaml',
			'categories/update-category-request.yaml',
			'events/create-event-request.yaml',
			'events/update-event-request.yaml',
			'event-categories/create-event-category-request.yaml',
			'event-categories/update-event-category-request.yaml',
			'pages/create-page-request.yaml',
			'pages/update-page-request.yaml',
			'posts/create-post-request.yaml',
			'posts/update-post-request.yaml',
			'tags/create-tag-request.yaml',
			'tags/update-tag-request.yaml',
			'users/create-user-request.yaml',
			'users/update-user-request.yaml',
			'members/update-profile-request.yaml',
		];

		foreach( $expectedDtos as $dtoPath )
		{
			$fullPath = $basePath . '/' . $dtoPath;
			$this->assertFileExists( $fullPath, "DTO file does not exist: {$dtoPath}" );
		}
	}
}
