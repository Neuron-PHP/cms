<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Users;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Services\User\Creator;
use Neuron\Cms\Services\User\Updater;
use Neuron\Cms\Services\User\Deleter;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;

/**
 * Unit tests for Users admin controller
 */
class UsersControllerTest extends TestCase
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

	public function testIndexReturnsAllUsers(): void
	{
		// Set up admin user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Mock repository to return users
		$repository = $this->createMock( DatabaseUserRepository::class );
		$users = [
			$this->createMock( User::class ),
			$this->createMock( User::class )
		];
		$repository->expects( $this->once() )
			->method( 'all' )
			->willReturn( $users );

		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		// Create controller with mocked dependencies
		$controller = $this->getMockBuilder( Users::class )
			->setConstructorArgs([
				null,
				$repository,
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Users Index</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Users::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionManager->method( 'getFlash' )->willReturn( null );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->index( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Users Index', $result );
	}

	public function testCreateReturnsForm(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		$repository = $this->createMock( DatabaseUserRepository::class );
		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = $this->getMockBuilder( Users::class )
			->setConstructorArgs([
				null,
				$repository,
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Create User Form</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Users::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );

		$result = $controller->create( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Create User Form', $result );
	}

	public function testEditReturnsFormForValidUser(): void
	{
		// Set up authenticated user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Mock user to edit
		$userToEdit = $this->createMock( User::class );
		$userToEdit->method( 'getId' )->willReturn( 2 );

		$repository = $this->createMock( DatabaseUserRepository::class );
		$repository->method( 'findById' )->with( 2 )->willReturn( $userToEdit );

		$creator = $this->createMock( Creator::class );
		$updater = $this->createMock( Updater::class );
		$deleter = $this->createMock( Deleter::class );

		$controller = $this->getMockBuilder( Users::class )
			->setConstructorArgs([
				null,
				$repository,
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
		$viewBuilder->method( 'render' )->willReturn( '<html>Edit User Form</html>' );

		$controller->method( 'view' )->willReturn( $viewBuilder );

		// Mock session manager
		$reflection = new \ReflectionClass( get_parent_class( Users::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );

		$sessionManager = $this->createMock( SessionManager::class );
		$sessionProperty->setValue( $controller, $sessionManager );

		$request = $this->createMock( Request::class );
		$request->method( 'getRouteParameter' )->with( 'id' )->willReturn( '2' );

		$result = $controller->edit( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Edit User Form', $result );
	}

}
