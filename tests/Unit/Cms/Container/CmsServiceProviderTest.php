<?php

namespace Tests\Cms\Container;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cms\Services\Security\ResendVerificationThrottle;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Container\CmsServiceProvider;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabasePageRepository;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Services\Content\ShortcodeParser;
use Neuron\Cms\Services\User\Creator;
use Neuron\Cms\Services\User\Deleter;
use Neuron\Cms\Services\User\IUserCreator;
use Neuron\Cms\Services\User\IUserDeleter;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Services\User\Updater;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use Neuron\Data\Settings\SettingManager;
use Neuron\Events\Emitter;
use Neuron\Cms\Container\ContainerAdapter;
use DI\Container as DIContainer;
use Neuron\Patterns\Registry;
use Neuron\Patterns\Container\IContainer;
use Neuron\Routing\DefaultIpResolver;
use Neuron\Routing\IIpResolver;
use PHPUnit\Framework\TestCase;

class CmsServiceProviderTest extends TestCase
{
	private CmsServiceProvider $provider;
	private IContainer $container;

	protected function setUp(): void
	{
		parent::setUp();

		// Setup Registry with mock settings and emitter for shared services
		$mockSettings = $this->createMock( SettingManager::class );
		// Configure mock settings to return database config
		$mockSettings->method( 'getSection' )
			->willReturnCallback( function( $section ) {
				if( $section === 'database' )
				{
					return [
						'adapter' => 'sqlite',
						'name' => ':memory:',
					];
				}
				return null;
			});

		$mockEmitter = $this->createMock( Emitter::class );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $mockSettings );
		Registry::getInstance()->set( 'EventEmitter', $mockEmitter );

		// Create real container for testing using ContainerAdapter
		$diContainer = new DIContainer();
		// Pre-configure SettingManager in the DI container
		$diContainer->set( SettingManager::class, $mockSettings );

		$this->container = new ContainerAdapter( $diContainer );
		$this->provider = new CmsServiceProvider();
	}

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testRegisterBindsAllRepositories(): void
	{
		$this->provider->register( $this->container );

		// Verify repository bindings can be resolved
		$this->assertInstanceOf( DatabaseUserRepository::class, $this->container->get( IUserRepository::class ) );
		$this->assertInstanceOf( DatabasePostRepository::class, $this->container->get( IPostRepository::class ) );
		$this->assertInstanceOf( DatabasePageRepository::class, $this->container->get( IPageRepository::class ) );
		$this->assertInstanceOf( DatabaseCategoryRepository::class, $this->container->get( ICategoryRepository::class ) );
		$this->assertInstanceOf( DatabaseTagRepository::class, $this->container->get( ITagRepository::class ) );
		$this->assertInstanceOf( DatabaseEventRepository::class, $this->container->get( IEventRepository::class ) );
		$this->assertInstanceOf( DatabaseEventCategoryRepository::class, $this->container->get( IEventCategoryRepository::class ) );
	}

	public function testRegisterBindsUserServices(): void
	{
		$this->provider->register( $this->container );

		// Verify user service bindings can be resolved
		$this->assertInstanceOf( Creator::class, $this->container->get( IUserCreator::class ) );
		$this->assertInstanceOf( Updater::class, $this->container->get( IUserUpdater::class ) );
		$this->assertInstanceOf( Deleter::class, $this->container->get( IUserDeleter::class ) );
	}

	public function testRegisterCreatesAuthServicesSingletons(): void
	{
		$this->provider->register( $this->container );

		// Verify auth singletons - get same instance twice
		$hasher1 = $this->container->get( PasswordHasher::class );
		$hasher2 = $this->container->get( PasswordHasher::class );
		$this->assertSame( $hasher1, $hasher2, 'PasswordHasher should be singleton' );

		$session1 = $this->container->get( SessionManager::class );
		$session2 = $this->container->get( SessionManager::class );
		$this->assertSame( $session1, $session2, 'SessionManager should be singleton' );
	}

	public function testRegisterBindsAuthServices(): void
	{
		$this->provider->register( $this->container );

		// Verify auth bindings can be resolved
		$this->assertInstanceOf( ResendVerificationThrottle::class, $this->container->get( ResendVerificationThrottle::class ) );
		$this->assertInstanceOf( DefaultIpResolver::class, $this->container->get( IIpResolver::class ) );
	}

	public function testRegisterBindsContentServices(): void
	{
		$this->provider->register( $this->container );

		// Verify content service bindings can be resolved
		$this->assertInstanceOf( WidgetRenderer::class, $this->container->get( WidgetRenderer::class ) );
		$this->assertInstanceOf( ShortcodeParser::class, $this->container->get( ShortcodeParser::class ) );
		$this->assertInstanceOf( EditorJsRenderer::class, $this->container->get( EditorJsRenderer::class ) );
	}

	public function testRegisterCreatesSharedServiceSingletons(): void
	{
		$this->provider->register( $this->container );

		// Verify shared services are singletons - get same instance twice
		$settings1 = $this->container->get( SettingManager::class );
		$settings2 = $this->container->get( SettingManager::class );
		$this->assertSame( $settings1, $settings2, 'SettingManager should be singleton' );

		$emitter1 = $this->container->get( Emitter::class );
		$emitter2 = $this->container->get( Emitter::class );
		$this->assertSame( $emitter1, $emitter2, 'Emitter should be singleton' );
	}

	public function testAllServicesAreRegistered(): void
	{
		$this->provider->register( $this->container );

		// Verify all key service categories are registered and can be resolved
		$services = [
			// Repositories (7)
			IUserRepository::class,
			IPostRepository::class,
			IPageRepository::class,
			ICategoryRepository::class,
			ITagRepository::class,
			IEventRepository::class,
			IEventCategoryRepository::class,
			// User services (3)
			IUserCreator::class,
			IUserUpdater::class,
			IUserDeleter::class,
			// Auth services (4)
			PasswordHasher::class,
			SessionManager::class,
			ResendVerificationThrottle::class,
			IIpResolver::class,
			// Content services (3)
			WidgetRenderer::class,
			ShortcodeParser::class,
			EditorJsRenderer::class,
			// Shared services (2)
			SettingManager::class,
			Emitter::class,
		];

		foreach( $services as $service )
		{
			$this->assertNotNull(
				$this->container->get( $service ),
				"Service {$service} should be registered and resolvable"
			);
		}
	}
}
