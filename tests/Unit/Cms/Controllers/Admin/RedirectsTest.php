<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Admin\Redirects;
use Neuron\Cms\Repositories\IRedirectRepository;
use Neuron\Cms\Services\Redirect\RedirectService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class RedirectsTest extends TestCase
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

		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturn( 'Test Site' );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $mockSettings );

		$this->mockContainer
			->method( 'get' )
			->willReturnCallback( function( $class ) {
				if( $class === IRedirectRepository::class ) return $this->createMock( IRedirectRepository::class );
				if( $class === RedirectService::class ) return $this->createMock( RedirectService::class );
				if( $class === SessionManager::class ) return $this->createMock( SessionManager::class );
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );
	}

	public function testConstructorWithAllDependencies(): void
	{
		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$repository = $this->createMock( IRedirectRepository::class );

		$controller = new Redirects(
			$this->mockApp,
			$mockSettingManager,
			$mockSessionManager,
			$repository,
			new RedirectService( $repository )
		);

		$this->assertInstanceOf( Redirects::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutSettingManager(): void
	{
		$this->expectException( \TypeError::class );

		new Redirects( null );
	}
}
