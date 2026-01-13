<?php

namespace Tests\Cms\Controllers;

use Neuron\Cms\Controllers\Calendar;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Routing\Router;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class CalendarTest extends TestCase
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

		// Create mock settings
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );
		$settings->set( 'paths', 'version_file', $this->_versionFilePath );

		$this->_settingManager = new SettingManager( $settings );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $this->_settingManager );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		Registry::getInstance()->set( RegistryKeys::APP_VERSION, null );
		Registry::getInstance()->set( RegistryKeys::APP_NAME, null );
		Registry::getInstance()->set( RegistryKeys::APP_RSS_URL, null );
		Registry::getInstance()->set( 'DtoFactoryService', null );

		// Clean up temp version file
		if( isset( $this->_versionFilePath ) && file_exists( $this->_versionFilePath ) )
		{
			unlink( $this->_versionFilePath );
		}

		parent::tearDown();
	}

	public function testConstructorWithDependencies(): void
	{
		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );
		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Calendar( $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository );

		$this->assertInstanceOf( Calendar::class, $controller );
	}

	public function testIndexRendersCalendarForCurrentMonth(): void
	{
		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );

		$mockEventRepository->method( 'getByDateRange' )->willReturn( [] );
		$mockCategoryRepository->method( 'all' )->willReturn( [] );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Calendar::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) {
					return isset( $data['Title'] ) &&
					       isset( $data['events'] ) &&
					       isset( $data['categories'] ) &&
					       isset( $data['currentMonth'] ) &&
					       isset( $data['currentYear'] );
				} ),
				'index',
				'default'
			)
			->willReturn( '<html>Calendar</html>' );

		$request = new Request();
		$result = $controller->index( $request );

		$this->assertEquals( '<html>Calendar</html>', $result );
	}

	public function testIndexRendersCalendarForSpecificMonth(): void
	{
		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );

		// Expect date range for March 2024
		$mockEventRepository->expects( $this->once() )
			->method( 'getByDateRange' )
			->with(
				$this->callback( function( $date ) {
					return $date->format( 'Y-m-d' ) === '2024-03-01';
				} ),
				$this->callback( function( $date ) {
					return $date->format( 'Y-m-d' ) === '2024-03-31';
				} ),
				'published'
			)
			->willReturn( [] );

		$mockCategoryRepository->method( 'all' )->willReturn( [] );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Calendar::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->method( 'renderHtml' )->willReturn( '<html>Calendar March 2024</html>' );

		$request = $this->getMockBuilder( Request::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$request->method( 'get' )
			->willReturnCallback( function( $key, $default ) {
				return match( $key ) {
					'month' => '3',
					'year' => '2024',
					default => $default
				};
			} );

		$result = $controller->index( $request );

		$this->assertEquals( '<html>Calendar March 2024</html>', $result );
	}

	public function testShowRendersPublishedEvent(): void
	{
		$mockEvent = $this->createMock( Event::class );
		$mockEvent->method( 'getTitle' )->willReturn( 'Test Event' );
		$mockEvent->method( 'getDescription' )->willReturn( 'Event description' );
		$mockEvent->method( 'isPublished' )->willReturn( true );

		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockEventRepository->method( 'findBySlug' )->with( 'test-event' )->willReturn( $mockEvent );
		$mockEventRepository->expects( $this->once() )
			->method( 'incrementViewCount' )
			->with( $mockEvent );

		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Calendar::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) use ( $mockEvent ) {
					return $data['event'] === $mockEvent &&
					       isset( $data['Title'] ) &&
					       isset( $data['Description'] );
				} ),
				'show',
				'default'
			)
			->willReturn( '<html>Event Detail</html>' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'test-event' ] );
		$result = $controller->show( $request );

		$this->assertEquals( '<html>Event Detail</html>', $result );
	}

	public function testShowThrowsExceptionForNonexistentEvent(): void
	{
		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockEventRepository->method( 'findBySlug' )->willReturn( null );

		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Calendar( $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Event not found' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'nonexistent' ] );
		$controller->show( $request );
	}

	public function testShowThrowsExceptionForUnpublishedEvent(): void
	{
		$mockEvent = $this->createMock( Event::class );
		$mockEvent->method( 'isPublished' )->willReturn( false );

		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockEventRepository->method( 'findBySlug' )->willReturn( $mockEvent );

		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Calendar( $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Event not found' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'unpublished-event' ] );
		$controller->show( $request );
	}

	public function testCategoryRendersEventsForCategory(): void
	{
		$mockCategory = $this->createMock( EventCategory::class );
		$mockCategory->method( 'getId' )->willReturn( 5 );
		$mockCategory->method( 'getName' )->willReturn( 'Workshops' );

		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockEventRepository->method( 'getByCategory' )->with( 5, 'published' )->willReturn( [] );

		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );
		$mockCategoryRepository->method( 'findBySlug' )->with( 'workshops' )->willReturn( $mockCategory );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Calendar::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) use ( $mockCategory ) {
					return $data['category'] === $mockCategory &&
					       isset( $data['events'] ) &&
					       isset( $data['Title'] ) &&
					       isset( $data['Description'] );
				} ),
				'category',
				'default'
			)
			->willReturn( '<html>Category Events</html>' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'workshops' ] );
		$result = $controller->category( $request );

		$this->assertEquals( '<html>Category Events</html>', $result );
	}

	public function testCategoryThrowsExceptionForNonexistentCategory(): void
	{
		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );
		$mockCategoryRepository->method( 'findBySlug' )->willReturn( null );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Calendar( $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Category not found' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'nonexistent' ] );
		$controller->category( $request );
	}

	public function testShowUsesEventTitleWhenDescriptionNotSet(): void
	{
		$mockEvent = $this->createMock( Event::class );
		$mockEvent->method( 'getTitle' )->willReturn( 'Event Title' );
		$mockEvent->method( 'getDescription' )->willReturn( null );  // No description
		$mockEvent->method( 'isPublished' )->willReturn( true );

		$mockEventRepository = $this->createMock( IEventRepository::class );
		$mockEventRepository->method( 'findBySlug' )->willReturn( $mockEvent );
		$mockEventRepository->method( 'incrementViewCount' );

		$mockCategoryRepository = $this->createMock( IEventCategoryRepository::class );

		$mockSettingManager = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Calendar::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockEventRepository, $mockCategoryRepository ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) {
					// Description should default to event title when null
					return $data['Description'] === 'Event Title';
				} ),
				'show',
				'default'
			)
			->willReturn( '<html>Event</html>' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'test-event' ] );
		$controller->show( $request );
	}
}
