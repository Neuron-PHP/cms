<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cms\Controllers\Admin\Categories;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Services\Category\Creator;
use Neuron\Cms\Services\Category\Updater;
use Neuron\Cms\Services\Category\Deleter;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;

/**
 * Unit tests for Categories admin controller
 */
class CategoriesControllerTest extends TestCase
{
	private array $_originalRegistry = [];
	private IMvcApplication $_mockApp;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock application
		$router = $this->createMock( Router::class );
		$this->_mockApp = $this->createMock( IMvcApplication::class );
		$this->_mockApp->method( 'getRouter' )->willReturn( $router );

		// Store original registry values
		$this->_originalRegistry = [
			'Auth.User' => Registry::getInstance()->get( RegistryKeys::AUTH_USER ),
			'Auth.UserId' => Registry::getInstance()->get( RegistryKeys::AUTH_USER_ID ),
			'Auth.CsrfToken' => Registry::getInstance()->get( RegistryKeys::AUTH_CSRF_TOKEN ),
			'Settings' => Registry::getInstance()->get( RegistryKeys::SETTINGS )
		];

		// Mock Settings for Content controller parent class
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			$defaults = [
				'site' => [
					'name' => 'Test Site',
					'title' => 'Test Site Title',
					'description' => 'Test Description',
					'url' => 'https://test.example.com'
				]
			];
			return $defaults[$section][$key] ?? null;
		});
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );
	}

	protected function tearDown(): void
	{
		// Restore original registry values
		foreach( $this->_originalRegistry as $key => $value )
		{
			Registry::getInstance()->set( $key, $value );
		}

		parent::tearDown();
	}

	public function testIndexReturnsAllCategories(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );

		// Mock repository
		$repository = $this->createMock( DatabaseCategoryRepository::class );
		$categories = [
			['id' => 1, 'name' => 'Technology', 'post_count' => 5],
			['id' => 2, 'name' => 'Business', 'post_count' => 3]
		];
		$repository->expects( $this->once() )
			->method( 'allWithPostCount' )
			->willReturn( $categories );

		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Categories::class )
			->setConstructorArgs([
				$this->_mockApp,
				$mockSettingManager,
				$mockSessionManager,
				$repository,
				$creator,
				$updater
			])
			->onlyMethods( ['view'] )
			->getMock();

		// Mock the view builder chain
		$viewBuilder = $this->createMock( ViewContext::class );
		$viewBuilder->method( 'title' )->willReturnSelf();
		$viewBuilder->method( 'description' )->willReturnSelf();
		$viewBuilder->method( 'withCurrentUser' )->willReturnSelf();
		$viewBuilder->method( 'withCsrfToken' )->willReturnSelf();
		$viewBuilder->method( 'with' )->willReturnSelf();
		$viewBuilder->method( 'render' )->willReturn( '<html>Categories Index</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Categories::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->index( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Categories Index', $result );
	}

	public function testCreateReturnsForm(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );

		$repository = $this->createMock( DatabaseCategoryRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Categories::class )
			->setConstructorArgs([
				$this->_mockApp,
				$mockSettingManager,
				$mockSessionManager,
				$repository,
				$creator,
				$updater
			])
			->onlyMethods( ['view'] )
			->getMock();

		// Mock the view builder chain
		$viewBuilder = $this->createMock( ViewContext::class );
		$viewBuilder->method( 'title' )->willReturnSelf();
		$viewBuilder->method( 'description' )->willReturnSelf();
		$viewBuilder->method( 'withCurrentUser' )->willReturnSelf();
		$viewBuilder->method( 'withCsrfToken' )->willReturnSelf();
		$viewBuilder->method( 'render' )->willReturn( '<html>Create Category Form</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Categories::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->create( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Create Category Form', $result );
	}

	public function testEditThrowsExceptionWhenCategoryNotFound(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );

		$repository = $this->createMock( DatabaseCategoryRepository::class );
		$repository->method( 'findById' )->with( 999 )->willReturn( null );

		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Categories(
			$this->_mockApp,
			$mockSettingManager,
			$mockSessionManager,
			$repository,
			$creator,
			$updater
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '999' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Category not found' );

		$controller->edit( $request );
	}

}
