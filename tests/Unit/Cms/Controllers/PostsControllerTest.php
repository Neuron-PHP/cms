<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Posts;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Services\Post\Creator;
use Neuron\Cms\Services\Post\Updater;
use Neuron\Cms\Services\Post\Deleter;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;

/**
 * Unit tests for Posts admin controller
 */
class PostsControllerTest extends TestCase
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

		$postRepository = $this->createMock( DatabasePostRepository::class );
		$categoryRepository = $this->createMock( DatabaseCategoryRepository::class );
		$tagRepository = $this->createMock( DatabaseTagRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = new Posts(
			null,
			$postRepository,
			$categoryRepository,
			$tagRepository,
			$creator,
			$updater,
			$deleter
		);

		$request = $this->createMock( Request::class );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->index( $request );
	}

	public function testIndexReturnsAllPostsForAdmin(): void
	{
		// Set up admin user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		$user->method( 'getUsername' )->willReturn( 'admin' );
		$user->method( 'isAdmin' )->willReturn( true );
		$user->method( 'isEditor' )->willReturn( false );
		Registry::getInstance()->set( 'Auth.User', $user );
		Registry::getInstance()->set( 'Auth.UserId', 1 );

		// Mock repository to return posts
		$postRepository = $this->createMock( DatabasePostRepository::class );
		$posts = [
			$this->createMock( Post::class ),
			$this->createMock( Post::class )
		];
		$postRepository->expects( $this->once() )
			->method( 'all' )
			->willReturn( $posts );

		$categoryRepository = $this->createMock( DatabaseCategoryRepository::class );
		$tagRepository = $this->createMock( DatabaseTagRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		// Create controller with mocked dependencies and mocked view method
		$controller = $this->getMockBuilder( Posts::class )
			->setConstructorArgs([
				null,
				$postRepository,
				$categoryRepository,
				$tagRepository,
				$creator,
				$updater,
				$deleter
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Posts Index</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager via reflection
		$reflection = new \ReflectionClass( get_parent_class( Posts::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionManager->method( 'getFlash' )->willReturn( null );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->index( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Posts Index', $result );
	}

	public function testIndexFiltersPostsByAuthorForNonAdmin(): void
	{
		// Set up regular user (not admin, not editor)
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 2 );
		$user->method( 'isAdmin' )->willReturn( false );
		$user->method( 'isEditor' )->willReturn( false );
		Registry::getInstance()->set( 'Auth.User', $user );
		Registry::getInstance()->set( 'Auth.UserId', 2 );

		// Mock repository to return author's posts
		$postRepository = $this->createMock( DatabasePostRepository::class );
		$posts = [
			$this->createMock( Post::class )
		];
		$postRepository->expects( $this->once() )
			->method( 'getByAuthor' )
			->with( 2 )
			->willReturn( $posts );

		$categoryRepository = $this->createMock( DatabaseCategoryRepository::class );
		$tagRepository = $this->createMock( DatabaseTagRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		// Create controller with mocked dependencies
		$controller = $this->getMockBuilder( Posts::class )
			->setConstructorArgs([
				null,
				$postRepository,
				$categoryRepository,
				$tagRepository,
				$creator,
				$updater,
				$deleter
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Posts Index</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Posts::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionManager->method( 'getFlash' )->willReturn( null );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->index( $request );

		$this->assertIsString( $result );
	}

	public function testCreateReturnsFormForAuthenticatedUser(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		$postRepository = $this->createMock( DatabasePostRepository::class );
		$categoryRepository = $this->createMock( DatabaseCategoryRepository::class );
		$categoryRepository->expects( $this->once() )
			->method( 'all' )
			->willReturn( [] );

		$tagRepository = $this->createMock( DatabaseTagRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = $this->getMockBuilder( Posts::class )
			->setConstructorArgs([
				null,
				$postRepository,
				$categoryRepository,
				$tagRepository,
				$creator,
				$updater,
				$deleter
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Create Post Form</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Posts::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->create( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Create Post Form', $result );
	}

	public function testCreateThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$postRepository = $this->createMock( DatabasePostRepository::class );
		$categoryRepository = $this->createMock( DatabaseCategoryRepository::class );
		$tagRepository = $this->createMock( DatabaseTagRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = new Posts(
			null,
			$postRepository,
			$categoryRepository,
			$tagRepository,
			$creator,
			$updater,
			$deleter
		);

		$request = $this->createMock( Request::class );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->create( $request );
	}

	public function testEditThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// No user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$postRepository = $this->createMock( DatabasePostRepository::class );
		$categoryRepository = $this->createMock( DatabaseCategoryRepository::class );
		$tagRepository = $this->createMock( DatabaseTagRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = new Posts(
			null,
			$postRepository,
			$categoryRepository,
			$tagRepository,
			$creator,
			$updater,
			$deleter
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '1' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$controller->edit( $request );
	}

	public function testEditThrowsExceptionWhenUserUnauthorized(): void
	{
		// Set up regular user (not admin, not editor)
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 2 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Mock post owned by different user
		$post = $this->createMock( Post::class );
		$post->method( 'getAuthorId' )->willReturn( 999 ); // Different author

		$postRepository = $this->createMock( DatabasePostRepository::class );
		$postRepository->method( 'findById' )->with( 1 )->willReturn( $post );

		$categoryRepository = $this->createMock( DatabaseCategoryRepository::class );
		$tagRepository = $this->createMock( DatabaseTagRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = new Posts(
			null,
			$postRepository,
			$categoryRepository,
			$tagRepository,
			$creator,
			$updater,
			$deleter
		);

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '1' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Unauthorized to edit this post' );

		$controller->edit( $request );
	}
}
