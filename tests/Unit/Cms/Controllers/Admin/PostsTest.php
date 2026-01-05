<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Posts;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\Post\IPostCreator;
use Neuron\Cms\Services\Post\IPostUpdater;
use Neuron\Cms\Services\Post\IPostDeleter;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Routing\Router;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class PostsTest extends TestCase
{
	private Posts $controller;
	private IMvcApplication $mockApp;
	private IContainer $mockContainer;

	protected function setUp(): void
	{
		parent::setUp();

		$this->mockApp = $this->createMock( IMvcApplication::class );
		$router = $this->createMock( Router::class );
		$this->mockApp->method( 'getRouter' )->willReturn( $router );
		$this->mockContainer = $this->createMock( IContainer::class );

		// Setup mock settings for Registry
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturn( 'Test Site' );
		Registry::getInstance()->set( 'Settings', $mockSettings );

		// Setup container to return mocks
		$this->mockContainer
			->method( 'get' )
			->willReturnCallback( function( $class ) {
				if( $class === IPostRepository::class ) return $this->createMock( IPostRepository::class );
				if( $class === ICategoryRepository::class ) return $this->createMock( ICategoryRepository::class );
				if( $class === ITagRepository::class ) return $this->createMock( ITagRepository::class );
				if( $class === IPostCreator::class ) return $this->createMock( IPostCreator::class );
				if( $class === IPostUpdater::class ) return $this->createMock( IPostUpdater::class );
				if( $class === IPostDeleter::class ) return $this->createMock( IPostDeleter::class );
				if( $class === SessionManager::class ) return $this->createMock( SessionManager::class );
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );
	}

	public function testConstructorWithAllDependencies(): void
	{
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );

		$controller = new Posts(
			$this->mockApp,
			$mockSettingManager,
			$mockSessionManager,
			$this->createMock( IPostRepository::class ),
			$this->createMock( ICategoryRepository::class ),
			$this->createMock( ITagRepository::class ),
			$this->createMock( IPostCreator::class ),
			$this->createMock( IPostUpdater::class ),
			$this->createMock( IPostDeleter::class )
		);

		$this->assertInstanceOf( Posts::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutSettingManager(): void
	{
		$this->expectException( \TypeError::class );

		new Posts( null );
	}

	public function testConstructorThrowsExceptionWithoutPostRepository(): void
	{
		$this->expectException( \TypeError::class );

		$mockSettings = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );

		new Posts(
			$this->mockApp,
			$mockSettings,
			$mockSessionManager,
			null,
			null,
			null,
			null,
			null,
			null
		);
	}
}
