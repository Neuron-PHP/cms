<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Pages;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Services\Page\IPageCreator;
use Neuron\Cms\Services\Page\IPageUpdater;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class PagesTest extends TestCase
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
				if( $class === IPageRepository::class ) return $this->createMock( IPageRepository::class );
				if( $class === IPageCreator::class ) return $this->createMock( IPageCreator::class );
				if( $class === IPageUpdater::class ) return $this->createMock( IPageUpdater::class );
				if( $class === SessionManager::class ) return $this->createMock( SessionManager::class );
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );
	}

	public function testConstructorWithAllDependencies(): void
	{
		$controller = new Pages(
			$this->mockApp,
			$this->createMock( IPageRepository::class ),
			$this->createMock( IPageCreator::class ),
			$this->createMock( IPageUpdater::class )
		);

		$this->assertInstanceOf( Pages::class, $controller );
	}

	public function testConstructorResolvesFromContainer(): void
	{
		$controller = new Pages( $this->mockApp );
		$this->assertInstanceOf( Pages::class, $controller );
	}
}
