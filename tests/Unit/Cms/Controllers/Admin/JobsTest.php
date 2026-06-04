<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Jobs;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class JobsTest extends TestCase
{
	private SettingManager $_settingManager;
	private string $_versionFilePath;
	private string $_basePath;
	private $mockSettingManager;
	private $mockSessionManager;
	private IMvcApplication $_mockApp;

	protected function setUp(): void
	{
		parent::setUp();

		$router = $this->createMock( Router::class );
		$this->_mockApp = $this->createMock( IMvcApplication::class );
		$this->_mockApp->method( 'getRouter' )->willReturn( $router );

		$this->_versionFilePath = sys_get_temp_dir() . '/neuron-test-version-' . uniqid() . '.json';
		file_put_contents( $this->_versionFilePath, json_encode([ 'major' => 1, 'minor' => 0, 'patch' => 0 ]) );

		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );
		$settings->set( 'paths', 'version_file', $this->_versionFilePath );

		$this->_settingManager = new SettingManager( $settings );
		Registry::getInstance()->set( 'Settings', $this->_settingManager );

		$this->mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$this->mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		// Base path with a config/schedule.yaml fixture
		$this->_basePath = sys_get_temp_dir() . '/neuron-test-base-' . uniqid();
		mkdir( $this->_basePath . '/config', 0777, true );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( 'Settings', null );
		Registry::getInstance()->set( 'version', null );
		Registry::getInstance()->set( 'name', null );
		Registry::getInstance()->set( 'rss_url', null );
		Registry::getInstance()->set( 'DtoFactoryService', null );
		Registry::getInstance()->set( 'CsrfToken', null );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, null );

		if( isset( $this->_versionFilePath ) && file_exists( $this->_versionFilePath ) )
		{
			unlink( $this->_versionFilePath );
		}

		$scheduleFile = $this->_basePath . '/config/schedule.yaml';
		if( file_exists( $scheduleFile ) )
		{
			unlink( $scheduleFile );
		}
		if( is_dir( $this->_basePath . '/config' ) )
		{
			rmdir( $this->_basePath . '/config' );
		}
		if( is_dir( $this->_basePath ) )
		{
			rmdir( $this->_basePath );
		}

		parent::tearDown();
	}

	private function writeSchedule( string $yaml ): void
	{
		file_put_contents( $this->_basePath . '/config/schedule.yaml', $yaml );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, $this->_basePath );
	}

	public function testConstructor(): void
	{
		$controller = new Jobs( $this->_mockApp, $this->mockSettingManager, $this->mockSessionManager );
		$this->assertInstanceOf( Jobs::class, $controller );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testIndexRendersView(): void
	{
		$this->writeSchedule(
			"schedule:\n" .
			"  cleanupLogs:\n" .
			"    class: App\\Jobs\\CleanupLogsJob\n" .
			"    cron: \"0 2 * * *\"\n" .
			"    args:\n" .
			"      max_age_days: 30\n" .
			"  processReports:\n" .
			"    class: App\\Jobs\\GenerateReportsJob\n" .
			"    cron: \"0 8 * * 1\"\n" .
			"    queue: reports\n"
		);

		$controller = $this->getMockBuilder( Jobs::class )
			->setConstructorArgs( [ $this->_mockApp, $this->mockSettingManager, $this->mockSessionManager ] )
			->onlyMethods( [ 'view' ] )
			->getMock();

		$capturedJobs = null;

		$mockViewContext = $this->getMockBuilder( ViewContext::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'title', 'description', 'withCurrentUser', 'withCsrfToken', 'with', 'render' ] )
			->getMock();

		$mockViewContext->method( 'title' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'description' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'withCurrentUser' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'withCsrfToken' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'with' )->willReturnCallback(
			function( $data ) use ( $mockViewContext, &$capturedJobs )
			{
				if( is_array( $data ) && isset( $data['jobs'] ) )
				{
					$capturedJobs = $data['jobs'];
				}
				return $mockViewContext;
			}
		);
		$mockViewContext->method( 'render' )->willReturn( '<html>Jobs</html>' );

		$controller->method( 'view' )->willReturn( $mockViewContext );

		$result = $controller->index( new Request() );

		$this->assertEquals( '<html>Jobs</html>', $result );
		$this->assertIsArray( $capturedJobs );
		$this->assertCount( 2, $capturedJobs );

		$this->assertEquals( 'cleanupLogs', $capturedJobs[0]['name'] );
		$this->assertEquals( 'App\\Jobs\\CleanupLogsJob', $capturedJobs[0]['class'] );
		$this->assertEquals( '0 2 * * *', $capturedJobs[0]['cron'] );
		$this->assertTrue( $capturedJobs[0]['valid'] );
		$this->assertNotNull( $capturedJobs[0]['nextRun'] );
		$this->assertEquals( [ 'max_age_days' => 30 ], $capturedJobs[0]['args'] );
		$this->assertNull( $capturedJobs[0]['queue'] );

		$this->assertEquals( 'reports', $capturedJobs[1]['queue'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testIndexWithMissingScheduleFile(): void
	{
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, $this->_basePath );

		$controller = $this->getMockBuilder( Jobs::class )
			->setConstructorArgs( [ $this->_mockApp, $this->mockSettingManager, $this->mockSessionManager ] )
			->onlyMethods( [ 'view' ] )
			->getMock();

		$capturedExists = true;

		$mockViewContext = $this->getMockBuilder( ViewContext::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'title', 'description', 'withCurrentUser', 'withCsrfToken', 'with', 'render' ] )
			->getMock();

		$mockViewContext->method( 'title' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'description' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'withCurrentUser' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'withCsrfToken' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'with' )->willReturnCallback(
			function( $data ) use ( $mockViewContext, &$capturedExists )
			{
				if( is_array( $data ) && array_key_exists( 'scheduleFileExists', $data ) )
				{
					$capturedExists = $data['scheduleFileExists'];
				}
				return $mockViewContext;
			}
		);
		$mockViewContext->method( 'render' )->willReturn( '<html>Jobs</html>' );

		$controller->method( 'view' )->willReturn( $mockViewContext );

		$result = $controller->index( new Request() );

		$this->assertEquals( '<html>Jobs</html>', $result );
		$this->assertFalse( $capturedExists );
	}
}
