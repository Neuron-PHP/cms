<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Tags;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;

/**
 * Unit tests for Tags admin controller
 */
class TagsControllerTest extends TestCase
{
	private array $_originalRegistry = [];

	protected function setUp(): void
	{
		parent::setUp();

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

	public function testIndexThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$controller = new Tags(
			null,
			$repository
		);

		$request = $this->createMock( Request::class );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->index( $request );
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

		$controller = $this->getMockBuilder( Tags::class )
			->setConstructorArgs([
				null,
				$repository
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

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Tags::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->index( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Tags Index', $result );
	}

	public function testCreateThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$controller = new Tags(
			null,
			$repository
		);

		$request = $this->createMock( Request::class );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->create( $request );
	}

	public function testCreateReturnsForm(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$controller = $this->getMockBuilder( Tags::class )
			->setConstructorArgs([
				null,
				$repository
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

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Tags::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->create( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Create Tag Form', $result );
	}

	public function testStoreThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$controller = new Tags(
			null,
			$repository
		);

		$request = $this->createMock( Request::class );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->store( $request );
	}

	public function testEditThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$controller = new Tags(
			null,
			$repository
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '1' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->edit( $request );
	}

	public function testEditThrowsExceptionWhenTagNotFound(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		$repository = $this->createMock( DatabaseTagRepository::class );
		$repository->method( 'findById' )->with( 999 )->willReturn( null );

		$controller = new Tags(
			null,
			$repository
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '999' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Tag not found' );

		$controller->edit( $request );
	}

	public function testUpdateThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$controller = new Tags(
			null,
			$repository
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '1' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->update( $request );
	}

	public function testDestroyThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$repository = $this->createMock( DatabaseTagRepository::class );

		$controller = new Tags(
			null,
			$repository
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '1' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->destroy( $request );
	}
}
