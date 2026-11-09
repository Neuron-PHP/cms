<?php

namespace Tests\Cms\Config;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Config\AuthConfig;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class AuthConfigTest extends TestCase
{
	private string $_dir;
	private mixed $_previousSettings;

	protected function setUp(): void
	{
		parent::setUp();
		$this->_dir = sys_get_temp_dir() . '/cms_auth_config_' . uniqid();
		mkdir( $this->_dir );
		$this->_previousSettings = Registry::getInstance()->get( RegistryKeys::SETTINGS );
	}

	protected function tearDown(): void
	{
		@unlink( $this->_dir . '/auth.yaml' );
		@rmdir( $this->_dir );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $this->_previousSettings );
		parent::tearDown();
	}

	private function writeAuthYaml( string $yaml ): void
	{
		file_put_contents( $this->_dir . '/auth.yaml', $yaml );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function settings( array $data ): SettingManager
	{
		return new SettingManager( new Memory( $data ) );
	}

	public function testLoadWithoutAFileChangesNothing(): void
	{
		$settings = $this->settings( [ 'site' => [ 'name' => 'Test' ] ] );

		$this->assertFalse( AuthConfig::load( $settings, $this->_dir ) );
		$this->assertNull( $settings->get( 'auth', 'throttle' ) );
		$this->assertSame( 'Test', $settings->get( 'site', 'name' ) );
	}

	public function testLoadAddsAuthAndMemberSections(): void
	{
		$this->writeAuthYaml( <<<YAML
auth:
  throttle:
    max_attempts: 3
member:
  registration_enabled: false
YAML );

		$settings = $this->settings( [ 'site' => [ 'name' => 'Test' ] ] );

		$this->assertTrue( AuthConfig::load( $settings, $this->_dir ) );
		$this->assertSame( [ 'max_attempts' => 3 ], $settings->get( 'auth', 'throttle' ) );
		$this->assertFalse( $settings->get( 'member', 'registration_enabled' ) );
		$this->assertSame( 'Test', $settings->get( 'site', 'name' ) );
	}

	public function testAlreadyLoadedSettingsWin(): void
	{
		$this->writeAuthYaml( <<<YAML
auth:
  throttle:
    enabled: true
    max_attempts: 3
member:
  registration_enabled: true
  default_role: subscriber
YAML );

		$settings = $this->settings( [
			'auth'   => [ 'throttle' => [ 'max_attempts' => 10 ] ],
			'member' => [ 'registration_enabled' => false ],
		] );

		AuthConfig::load( $settings, $this->_dir );

		$this->assertSame( [ 'enabled' => true, 'max_attempts' => 10 ], $settings->get( 'auth', 'throttle' ) );
		$this->assertFalse( $settings->get( 'member', 'registration_enabled' ) );
		$this->assertSame( 'subscriber', $settings->get( 'member', 'default_role' ) );
	}

	public function testLoadingTwiceGivesTheSameResult(): void
	{
		$this->writeAuthYaml( "auth:\n  throttle:\n    max_attempts: 3\n" );
		$settings = $this->settings( [ 'auth' => [ 'throttle' => [ 'lockout_duration' => 30 ] ] ] );

		AuthConfig::load( $settings, $this->_dir );
		AuthConfig::load( $settings, $this->_dir );

		$this->assertSame( [ 'max_attempts' => 3, 'lockout_duration' => 30 ], $settings->get( 'auth', 'throttle' ) );
	}

	public function testAnUnparsableFileIsIgnored(): void
	{
		$this->writeAuthYaml( "auth:\n  throttle: [unclosed\n" );
		$settings = $this->settings( [ 'site' => [ 'name' => 'Test' ] ] );

		$this->assertFalse( AuthConfig::load( $settings, $this->_dir ) );
		$this->assertSame( 'Test', $settings->get( 'site', 'name' ) );
	}

	public function testConfigureThrottleAppliesEverySetting(): void
	{
		$authentication = $this->createMock( Authentication::class );
		$authentication->expects( $this->once() )->method( 'setThrottleEnabled' )->with( false );
		$authentication->expects( $this->once() )->method( 'setMaxLoginAttempts' )->with( 7 );
		$authentication->expects( $this->once() )->method( 'setLockoutDuration' )->with( 45 );

		AuthConfig::configureThrottle( $authentication, $this->settings( [
			'auth' => [ 'throttle' => [ 'enabled' => 'false', 'max_attempts' => '7', 'lockout_duration' => 45 ] ],
		] ) );
	}

	public function testConfigureThrottleIgnoresMissingOrInvalidValues(): void
	{
		$authentication = $this->createMock( Authentication::class );
		$authentication->expects( $this->never() )->method( 'setThrottleEnabled' );
		$authentication->expects( $this->never() )->method( 'setMaxLoginAttempts' );
		$authentication->expects( $this->never() )->method( 'setLockoutDuration' );

		AuthConfig::configureThrottle( $authentication, null );
		AuthConfig::configureThrottle( $authentication, $this->settings( [] ) );
		AuthConfig::configureThrottle( $authentication, $this->settings( [
			'auth' => [ 'throttle' => [ 'max_attempts' => 0, 'lockout_duration' => -5 ] ],
		] ) );
	}

	public function testConfigureRememberAppliesBothSettings(): void
	{
		$authentication = $this->createMock( Authentication::class );
		$authentication->expects( $this->once() )->method( 'setRememberLifetime' )->with( 600 );
		$authentication->expects( $this->once() )->method( 'setRememberEnabled' )->with( false );

		AuthConfig::configureRemember( $authentication, $this->settings( [
			'auth' => [ 'remember' => [ 'enabled' => false, 'lifetime' => 600 ] ],
		] ) );
	}

	public function testConfigurePasswordsAppliesThePolicy(): void
	{
		$hasher = new PasswordHasher();

		AuthConfig::configurePasswords( $hasher, $this->settings( [
			'auth' => [ 'passwords' => [
				'min_length'            => 12,
				'require_uppercase'     => false,
				'require_lowercase'     => true,
				'require_numbers'       => false,
				'require_special_chars' => true,
			] ],
		] ) );

		$this->assertTrue( $hasher->meetsRequirements( 'lowercase-only!' ) );
		$this->assertFalse( $hasher->meetsRequirements( 'short-one!' ), 'min_length 12' );
		$this->assertFalse( $hasher->meetsRequirements( 'lowercaseonlyx' ), 'special character required' );
	}

	public function testConfigurePasswordsWithoutSettingsKeepsDefaults(): void
	{
		$hasher = new PasswordHasher();

		AuthConfig::configurePasswords( $hasher, null );

		$this->assertTrue( $hasher->meetsRequirements( 'Password123' ) );
		$this->assertFalse( $hasher->meetsRequirements( 'password123' ), 'uppercase required by default' );
	}

	public function testCliSettingsLoadsAuthYamlIntoTheRegistrySettings(): void
	{
		$this->writeAuthYaml( "auth:\n  passwords:\n    min_length: 20\n" );
		$settings = $this->settings( [ 'site' => [ 'name' => 'Test' ] ] );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$this->assertSame( $settings, AuthConfig::cliSettings( $this->_dir ) );
		$this->assertSame( [ 'min_length' => 20 ], $settings->get( 'auth', 'passwords' ) );
	}

	public function testCliSettingsWithoutRegistrySettings(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		$this->assertNull( AuthConfig::cliSettings( $this->_dir ) );
	}

	public function testSessionConfigIsEmptyWithoutSettings(): void
	{
		$this->assertSame( [], AuthConfig::sessionConfig( null ) );
		$this->assertSame( [], AuthConfig::sessionConfig( $this->settings( [] ) ) );
	}

	public function testSessionConfigFallsBackToTopLevelLifetimeInSeconds(): void
	{
		$config = AuthConfig::sessionConfig( $this->settings( [ 'session' => [ 'lifetime' => 1800 ] ] ) );

		$this->assertSame( [ 'lifetime' => 1800 ], $config );
	}

	public function testSessionConfigFromAuthSession(): void
	{
		$config = AuthConfig::sessionConfig( $this->settings( [
			'session' => [ 'lifetime' => 1800 ],
			'auth'    => [ 'session' => [
				'lifetime'        => 120,
				'expire_on_close' => 'true',
				'cookie_name'     => 'neuron_session',
				'cookie_httponly' => true,
				'cookie_secure'   => 'auto',
				'cookie_samesite' => 'strict',
			] ],
		] ) );

		$this->assertSame( [
			'lifetime'        => 7200,
			'expire_on_close' => true,
			'cookie_httponly' => true,
			'cookie_secure'   => 'auto',
			'cookie_name'     => 'neuron_session',
			'cookie_samesite' => 'Strict',
		], $config );
	}

	public function testSessionConfigCookieSecureBooleans(): void
	{
		$on = AuthConfig::sessionConfig( $this->settings( [ 'auth' => [ 'session' => [ 'cookie_secure' => true ] ] ] ) );
		$off = AuthConfig::sessionConfig( $this->settings( [ 'auth' => [ 'session' => [ 'cookie_secure' => 'false' ] ] ] ) );

		$this->assertTrue( $on['cookie_secure'] );
		$this->assertFalse( $off['cookie_secure'] );
	}

	public function testSessionConfigDropsInvalidNameAndSameSite(): void
	{
		$config = AuthConfig::sessionConfig( $this->settings( [ 'auth' => [ 'session' => [
			'cookie_name'     => 'bad name;',
			'cookie_samesite' => 'sometimes',
		] ] ] ) );

		$this->assertSame( [], $config );
	}

	public function testConfigurePasswordsSetsTheHashAlgorithm(): void
	{
		$hasher = new PasswordHasher();

		AuthConfig::configurePasswords( $hasher, $this->settings( [ 'auth' => [ 'passwords' => [ 'hash_algorithm' => 'bcrypt' ] ] ] ) );

		$this->assertSame( 'bcrypt', $hasher->getAlgorithm() );
	}

	public function testConfigurePasswordsIgnoresAnUnknownHashAlgorithm(): void
	{
		$hasher = new PasswordHasher();
		$default = $hasher->getAlgorithm();

		AuthConfig::configurePasswords( $hasher, $this->settings( [ 'auth' => [ 'passwords' => [ 'hash_algorithm' => 'md5' ] ] ] ) );

		$this->assertSame( $default, $hasher->getAlgorithm() );
	}
}
