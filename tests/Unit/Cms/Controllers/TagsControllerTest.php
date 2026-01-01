<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Tags;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;

/**
 * Unit tests for Tags admin controller
 */
class TagsControllerTest extends TestCase
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
			'Auth.User' => Registry::getInstance()->get( 'Auth.User' ),
			'Auth.UserId' => Registry::getInstance()->get( 'Auth.UserId' ),
			'Auth.CsrfToken' => Registry::getInstance()->get( 'Auth.CsrfToken' ),
			'Settings' => Registry::getInstance()->get( 'Settings' )
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
		Registry::getInstance()->set( 'Settings', $settings );
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

	public function testIndexReturnsAllTags(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Mock repository
		$repository = $this->createMock( DatabaseTagRepository::class );
		$tags = [
			['id' => 1, 'name' => 'PHP', 'post_count' => 10],
			['id' => 2, 'name' => 'JavaScript', 'post_count' => 5]
		];
		$repository->expects( $this->once() )
			->method( 'allWithPostCount' )
			->willReturn( $tags );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$mockSlugGenerator = $this->createMock( \Neuron\Cms\Services\SlugGenerator::class );

		$controller = $this->getMockBuilder( Tags::class )
			->setConstructorArgs([
				$this->_mockApp,
				$mockSettingManager,
				$mockSessionManager,
				$repository,
				$mockSlugGenerator
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Tags Index</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		$request = $this->createMock( Request::class );

		$result = $controller->index( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Tags Index', $result );
	}

	public function testCreateReturnsForm(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$mockSlugGenerator = $this->createMock( \Neuron\Cms\Services\SlugGenerator::class );

		$controller = $this->getMockBuilder( Tags::class )
			->setConstructorArgs([
				$this->_mockApp,
				$mockSettingManager,
				$mockSessionManager,
				$repository,
				$mockSlugGenerator
			])
			->onlyMethods( ['view'] )
			->getMock();

		// Mock the view builder chain
		$viewBuilder = $this->createMock( ViewContext::class );
		$viewBuilder->method( 'title' )->willReturnSelf();
		$viewBuilder->method( 'description' )->willReturnSelf();
		$viewBuilder->method( 'withCurrentUser' )->willReturnSelf();
		$viewBuilder->method( 'withCsrfToken' )->willReturnSelf();
		$viewBuilder->method( 'render' )->willReturn( '<html>Create Tag Form</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		$request = $this->createMock( Request::class );

		$result = $controller->create( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Create Tag Form', $result );
	}

	public function testEditThrowsExceptionWhenTagNotFound(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		$repository = $this->createMock( DatabaseTagRepository::class );
		$repository->method( 'findById' )->with( 999 )->willReturn( null );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$mockSlugGenerator = $this->createMock( \Neuron\Cms\Services\SlugGenerator::class );

		$controller = new Tags(
			$this->_mockApp,
			$mockSettingManager,
			$mockSessionManager,
			$repository,
			$mockSlugGenerator
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '999' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Tag not found' );

		$controller->edit( $request );
	}

}
