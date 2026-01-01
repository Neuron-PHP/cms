<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\EventCategories;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\EventCategory\IEventCategoryCreator;
use Neuron\Cms\Services\EventCategory\IEventCategoryUpdater;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Routing\Router;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class EventCategoriesTest extends TestCase
{
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
				if( $class === IEventCategoryRepository::class ) return $this->createMock( IEventCategoryRepository::class );
				if( $class === IEventCategoryCreator::class ) return $this->createMock( IEventCategoryCreator::class );
				if( $class === IEventCategoryUpdater::class ) return $this->createMock( IEventCategoryUpdater::class );
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

		$controller = new EventCategories(
			$this->mockApp,
			$mockSettingManager,
			$mockSessionManager,
			$this->createMock( IEventCategoryRepository::class ),
			$this->createMock( IEventCategoryCreator::class ),
			$this->createMock( IEventCategoryUpdater::class )
		);

		$this->assertInstanceOf( EventCategories::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutSettingManager(): void
	{
		$this->expectException( \TypeError::class );

		new EventCategories( null );
	}
}
