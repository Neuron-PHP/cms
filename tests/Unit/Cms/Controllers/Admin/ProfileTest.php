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
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
	private SettingManager $_settingManager;

	protected function setUp(): void
	{
		parent::setUp();

		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );

		$this->_settingManager = new SettingManager( $settings );
		Registry::getInstance()->set( 'Settings', $this->_settingManager );

		$versionContent = json_encode([ 'major' => 1, 'minor' => 0, 'patch' => 0 ]);
		$parentDir = dirname( getcwd() );
		if( !file_exists( $parentDir . '/.version.json' ) )
		{
			file_put_contents( $parentDir . '/.version.json', $versionContent );
		}
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

		$parentDir = dirname( getcwd() );
		@unlink( $parentDir . '/.version.json' );

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
			null,
			$mockRepository,
			$mockHasher,
			$mockUpdater,
			$this->_settingManager,
			$mockSessionManager
		);

		$this->assertInstanceOf( Profile::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutSettingManager(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'SettingManager must be injected' );

		new Profile( null );
	}
}
