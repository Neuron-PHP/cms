<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Categories;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Category\ICategoryCreator;
use Neuron\Cms\Services\Category\ICategoryUpdater;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class CategoriesTest extends TestCase
{
	private Categories $controller;
	private ICategoryRepository $mockCategoryRepo;
	private ICategoryCreator $mockCategoryCreator;
	private ICategoryUpdater $mockCategoryUpdater;
	private Application $mockApp;
	private IContainer $mockContainer;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mocks
		$this->mockCategoryRepo = $this->createMock( ICategoryRepository::class );
		$this->mockCategoryCreator = $this->createMock( ICategoryCreator::class );
		$this->mockCategoryUpdater = $this->createMock( ICategoryUpdater::class );
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
				if( $class === ICategoryRepository::class ) return $this->mockCategoryRepo;
				if( $class === ICategoryCreator::class ) return $this->mockCategoryCreator;
				if( $class === ICategoryUpdater::class ) return $this->mockCategoryUpdater;
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

		$controller = new Categories(
			$this->mockApp,
			$this->mockCategoryRepo,
			$this->mockCategoryCreator,
			$this->mockCategoryUpdater,
			$mockSettingManager,
			$mockSessionManager
		);

		$this->assertInstanceOf( Categories::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutSettingManager(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'SettingManager must be injected' );

		new Categories( null );
	}

	public function testConstructorThrowsExceptionWithoutCategoryRepository(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'ICategoryRepository must be injected' );

		$mockSettings = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );

		new Categories(
			$this->mockApp,
			null,
			null,
			null,
			$mockSettings,
			$mockSessionManager
		);
	}
}
