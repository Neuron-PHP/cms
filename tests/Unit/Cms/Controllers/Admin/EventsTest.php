<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Events;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\Event\IEventCreator;
use Neuron\Cms\Services\Event\IEventUpdater;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class EventsTest extends TestCase
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
				if( $class === IEventRepository::class ) return $this->createMock( IEventRepository::class );
				if( $class === IEventCategoryRepository::class ) return $this->createMock( IEventCategoryRepository::class );
				if( $class === IEventCreator::class ) return $this->createMock( IEventCreator::class );
				if( $class === IEventUpdater::class ) return $this->createMock( IEventUpdater::class );
				if( $class === SessionManager::class ) return $this->createMock( SessionManager::class );
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );
	}

	public function testConstructorWithAllDependencies(): void
	{
		$controller = new Events(
			$this->mockApp,
			$this->createMock( IEventRepository::class ),
			$this->createMock( IEventCategoryRepository::class ),
			$this->createMock( IEventCreator::class ),
			$this->createMock( IEventUpdater::class )
		);

		$this->assertInstanceOf( Events::class, $controller );
	}

	public function testConstructorResolvesFromContainer(): void
	{
		$controller = new Events( $this->mockApp );
		$this->assertInstanceOf( Events::class, $controller );
	}
}
