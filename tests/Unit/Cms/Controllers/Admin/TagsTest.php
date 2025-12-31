<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Tags;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class TagsTest extends TestCase
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
				if( $class === ITagRepository::class ) return $this->createMock( ITagRepository::class );
				if( $class === SlugGenerator::class ) return $this->createMock( SlugGenerator::class );
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

		$controller = new Tags(
			$this->mockApp,
			$this->createMock( ITagRepository::class ),
			$this->createMock( SlugGenerator::class ),
			$mockSettingManager,
			$mockSessionManager
		);

		$this->assertInstanceOf( Tags::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutSettingManager(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'SettingManager must be injected' );

		new Tags( null );
	}
}
