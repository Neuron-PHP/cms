<?php

namespace Tests\Cms\Container;

use Neuron\Cms\Container\Container;
use Neuron\Cms\Services\Security\ResendVerificationThrottle;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Routing\IIpResolver;
use Neuron\Routing\DefaultIpResolver;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Container
 *
 * Ensures that Container::build() successfully creates a container
 * and can resolve all defined services without import or autowiring errors.
 *
 * @package Tests\Cms\Container
 */
class ContainerTest extends TestCase
{
	private SettingManager $_settings;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock settings with required configuration
		$memory = new Memory();
		$memory->set( 'database', 'adapter', 'sqlite' );
		$memory->set( 'database', 'name', ':memory:' );
		$memory->set( 'cloudinary', 'cloud_name', 'test' );
		$memory->set( 'cloudinary', 'api_key', 'test-key' );
		$memory->set( 'cloudinary', 'api_secret', 'test-secret' );
		$memory->set( 'email', 'verification_url', 'http://test.com/verify' );
		$memory->set( 'member', 'registration_enabled', true );

		$this->_settings = new SettingManager( $memory );

		// Reset container singleton for test isolation
		Container::reset();
		Registry::getInstance()->set( 'Container', null );
	}

	protected function tearDown(): void
	{
		Container::reset();
		Registry::getInstance()->set( 'Container', null );
		parent::tearDown();
	}

	/**
	 * Test that Container::build() successfully creates a container
	 */
	public function testBuildCreatesContainer(): void
	{
		$container = Container::build( $this->_settings );

		$this->assertInstanceOf( \Neuron\Patterns\Container\IContainer::class, $container );
	}

	/**
	 * Test that Container::build() returns singleton instance
	 */
	public function testBuildReturnsSingletonInstance(): void
	{
		$container1 = Container::build( $this->_settings );
		$container2 = Container::build( $this->_settings );

		$this->assertSame( $container1, $container2, 'Container should return same instance' );
	}

	/**
	 * Test that security services can be resolved
	 *
	 * This test would have caught the incorrect imports:
	 * - use Neuron\Cms\Services\Security\ResendVerificationThrottle; (wrong namespace before refactor)
	 * - use Neuron\Cms\Services\Security\IIpResolver; (non-existent class)
	 * - use Neuron\Cms\Services\Security\IpResolver; (non-existent class)
	 */
	public function testSecurityServicesCanBeResolved(): void
	{
		$container = Container::build( $this->_settings );

		// Test ResendVerificationThrottle (now in Services/Security)
		$throttle = $container->get( ResendVerificationThrottle::class );
		$this->assertInstanceOf( ResendVerificationThrottle::class, $throttle );

		// Test IIpResolver resolves to DefaultIpResolver (from Neuron\Routing)
		$ipResolver = $container->get( IIpResolver::class );
		$this->assertInstanceOf( DefaultIpResolver::class, $ipResolver );
	}

	/**
	 * Test that media services can be resolved
	 */
	public function testMediaServicesCanBeResolved(): void
	{
		$container = Container::build( $this->_settings );

		$uploader = $container->get( CloudinaryUploader::class );
		$this->assertInstanceOf( CloudinaryUploader::class, $uploader );

		$validator = $container->get( MediaValidator::class );
		$this->assertInstanceOf( MediaValidator::class, $validator );
	}

	/**
	 * Test that content services can be resolved
	 */
	public function testContentServicesCanBeResolved(): void
	{
		$container = Container::build( $this->_settings );

		$renderer = $container->get( EditorJsRenderer::class );
		$this->assertInstanceOf( EditorJsRenderer::class, $renderer );
	}

	/**
	 * Test that auth services can be resolved
	 */
	public function testAuthServicesCanBeResolved(): void
	{
		$container = Container::build( $this->_settings );

		$sessionManager = $container->get( SessionManager::class );
		$this->assertInstanceOf( SessionManager::class, $sessionManager );

		$passwordHasher = $container->get( PasswordHasher::class );
		$this->assertInstanceOf( PasswordHasher::class, $passwordHasher );

		$csrfToken = $container->get( CsrfToken::class );
		$this->assertInstanceOf( CsrfToken::class, $csrfToken );
	}

	/**
	 * Test that member services can be resolved
	 *
	 * KNOWN BUG: This test currently fails due to bug in Container.php:153
	 * EmailVerifier constructor expects IEmailVerificationTokenRepository but
	 * Container.php passes DatabaseUserRepository instead.
	 *
	 * Fix needed in Container.php around line 151-154:
	 * Replace: $userRepository = $c->get( DatabaseUserRepository::class );
	 * With:    $tokenRepository = $c->get( DatabaseEmailVerificationTokenRepository::class );
	 */
	public function testMemberServicesCanBeResolved(): void
	{
		$container = Container::build( $this->_settings );

		$registrationService = $container->get( IRegistrationService::class );
		$this->assertInstanceOf( IRegistrationService::class, $registrationService );
	}

	/**
	 * Test that repository services can be resolved
	 */
	public function testRepositoryServicesCanBeResolved(): void
	{
		$container = Container::build( $this->_settings );

		$userRepository = $container->get( IUserRepository::class );
		$this->assertInstanceOf( IUserRepository::class, $userRepository );
	}

	/**
	 * Test that SettingManager is available in container
	 *
	 * KNOWN BUG: Container.php creates a new SettingManager instance instead of using
	 * the injected one. The container should return the same instance that was passed
	 * to Container::build().
	 */
	public function testSettingManagerIsAvailable(): void
	{
		$container = Container::build( $this->_settings );

		$settings = $container->get( SettingManager::class );
		$this->assertSame( $this->_settings, $settings );
	}

	/**
	 * Test that container is stored in Registry
	 */
	public function testContainerIsStoredInRegistry(): void
	{
		$container = Container::build( $this->_settings );

		$registryContainer = Registry::getInstance()->get( 'Container' );
		$this->assertSame( $container, $registryContainer );
	}
}
