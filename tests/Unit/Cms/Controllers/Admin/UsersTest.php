<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Users;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\IUserCreator;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Services\User\IUserDeleter;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class UsersTest extends TestCase
{
	private Application $mockApp;
	private IContainer $mockContainer;

	protected function setUp(): void
	{
		parent::setUp();

		$this->mockApp = $this->createMock( Application::class );
		$this->mockContainer = $this->createMock( IContainer::class );

		// Setup mock settings for Registry
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturn( 'Test Site' );
		Registry::getInstance()->set( 'Settings', $mockSettings );

		// Setup container to return mocks
		$this->mockContainer
			->method( 'get' )
			->willReturnCallback( function( $class ) {
				if( $class === IUserRepository::class ) return $this->createMock( IUserRepository::class );
				if( $class === IUserCreator::class ) return $this->createMock( IUserCreator::class );
				if( $class === IUserUpdater::class ) return $this->createMock( IUserUpdater::class );
				if( $class === IUserDeleter::class ) return $this->createMock( IUserDeleter::class );
				if( $class === SessionManager::class ) return $this->createMock( SessionManager::class );
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );
	}

	public function testConstructorWithAllDependencies(): void
	{
		$controller = new Users(
			$this->mockApp,
			$this->createMock( IUserRepository::class ),
			$this->createMock( IUserCreator::class ),
			$this->createMock( IUserUpdater::class ),
			$this->createMock( IUserDeleter::class )
		);

		$this->assertInstanceOf( Users::class, $controller );
	}

	public function testConstructorResolvesFromContainer(): void
	{
		$controller = new Users( $this->mockApp );
		$this->assertInstanceOf( Users::class, $controller );
	}

	public function testConstructorWithPartialDependencies(): void
	{
		$controller = new Users(
			$this->mockApp,
			$this->createMock( IUserRepository::class )
		);

		$this->assertInstanceOf( Users::class, $controller );
	}
}
