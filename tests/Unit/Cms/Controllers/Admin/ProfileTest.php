<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Admin\Profile;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
	private SettingManager $_settingManager;
	private string $_versionFilePath;
	private IMvcApplication $_mockApp;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock application
		$router = $this->createMock( Router::class );
		$this->_mockApp = $this->createMock( IMvcApplication::class );
		$this->_mockApp->method( 'getRouter' )->willReturn( $router );

		// Create version file in temp directory
		$this->_versionFilePath = sys_get_temp_dir() . '/neuron-test-version-' . uniqid() . '.json';
		$versionContent = json_encode([ 'major' => 1, 'minor' => 0, 'patch' => 0 ]);
		file_put_contents( $this->_versionFilePath, $versionContent );

		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );
		$settings->set( 'paths', 'version_file', $this->_versionFilePath );

		$this->_settingManager = new SettingManager( $settings );
		Registry::getInstance()->set( 'Settings', $this->_settingManager );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( 'Settings', null );
		Registry::getInstance()->set( 'version', null );
		Registry::getInstance()->set( 'name', null );
		Registry::getInstance()->set( 'rss_url', null );
		Registry::getInstance()->set( 'DtoFactoryService', null );
		Registry::getInstance()->set( 'CsrfToken', null );
		Registry::getInstance()->set( 'User', null );

		// Clean up temp version file
		if( isset( $this->_versionFilePath ) && file_exists( $this->_versionFilePath ) )
		{
			unlink( $this->_versionFilePath );
		}

		parent::tearDown();
	}

	/**
	 * Note: Admin\Profile controller requires integration testing due to:
	 * - Global auth() function dependency
	 * - Global group_timezones_for_select() function dependency
	 * - Complex DTO handling with YAML configuration
	 * - Redirect mechanisms that terminate execution
	 *
	 * Unit testing these methods would require extensive mocking infrastructure.
	 * Integration tests are more appropriate for this controller.
	 */
	public function testConstructorWithDependencies(): void
	{
		$mockRepository = $this->createMock( IUserRepository::class );
		$mockHasher = $this->createMock( PasswordHasher::class );
		$mockUpdater = $this->createMock( IUserUpdater::class );
		$mockSessionManager = $this->createMock( SessionManager::class );

		$controller = new Profile(
			$this->_mockApp,
			$this->_settingManager,
			$mockSessionManager,
			$mockRepository,
			$mockHasher,
			$mockUpdater
		);

		$this->assertInstanceOf( Profile::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutSettingManager(): void
	{
		$this->expectException( \TypeError::class );

		new Profile( null );
	}
}
