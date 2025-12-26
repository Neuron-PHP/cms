<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Events;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Services\Event\Creator;
use Neuron\Cms\Services\Event\Updater;
use Neuron\Cms\Services\Event\Deleter;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;

/**
 * Unit tests for Events admin controller
 */
class EventsControllerTest extends TestCase
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

	public function testIndexReturnsAllEventsForAdmin(): void
	{
		// Set up admin user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		$user->method( 'isAdmin' )->willReturn( true );
		$user->method( 'isEditor' )->willReturn( false );
		Registry::getInstance()->set( 'Auth.User', $user );
		Registry::getInstance()->set( 'Auth.UserId', 1 );

		// Mock repository to return events
		$eventRepository = $this->createMock( DatabaseEventRepository::class );
		$events = [
			$this->createMock( Event::class ),
			$this->createMock( Event::class )
		];
		$eventRepository->expects( $this->once() )
			->method( 'all' )
			->willReturn( $events );

		$categoryRepository = $this->createMock( DatabaseEventCategoryRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = $this->getMockBuilder( Events::class )
			->setConstructorArgs([
				null,
				$eventRepository,
				$categoryRepository,
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Events Index</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Events::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionManager->method( 'getFlash' )->willReturn( null );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->index( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Events Index', $result );
	}

	public function testIndexFiltersEventsForNonAdmin(): void
	{
		// Set up regular user (not admin, not editor)
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 2 );
		$user->method( 'isAdmin' )->willReturn( false );
		$user->method( 'isEditor' )->willReturn( false );
		Registry::getInstance()->set( 'Auth.User', $user );
		Registry::getInstance()->set( 'Auth.UserId', 2 );

		// Mock repository to return creator's events
		$eventRepository = $this->createMock( DatabaseEventRepository::class );
		$events = [
			$this->createMock( Event::class )
		];
		$eventRepository->expects( $this->once() )
			->method( 'getByCreator' )
			->with( 2 )
			->willReturn( $events );

		$categoryRepository = $this->createMock( DatabaseEventCategoryRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = $this->getMockBuilder( Events::class )
			->setConstructorArgs([
				null,
				$eventRepository,
				$categoryRepository,
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Events Index</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Events::class ) );
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

		$eventRepository = $this->createMock( DatabaseEventRepository::class );
		$categoryRepository = $this->createMock( DatabaseEventCategoryRepository::class );
		$categoryRepository->expects( $this->once() )
			->method( 'all' )
			->willReturn( [] );

		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = $this->getMockBuilder( Events::class )
			->setConstructorArgs([
				null,
				$eventRepository,
				$categoryRepository,
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Create Event Form</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Events::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->create( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Create Event Form', $result );
	}
}
