<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Media;
use Neuron\Cms\Models\User;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;

/**
 * Unit tests for Media controller index method
 */
class MediaIndexTest extends TestCase
{
	private array $_originalRegistry = [];
	private SettingManager $_settings;
	private IMvcApplication $_mockApp;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock application
		$router = $this->createMock( Router::class );
		$this->_mockApp = $this->createMock( IMvcApplication::class );
		$this->_mockApp->method( 'getRouter' )->willReturn( $router );

		// Store original registry values
		$this->_originalRegistry = [
			'Settings' => Registry::getInstance()->get( 'Settings' ),
			'Auth.User' => Registry::getInstance()->get( 'Auth.User' )
		];

		// Set up Settings
		$memory = new Memory();
		$memory->set( 'cloudinary', 'cloud_name', 'test-cloud' );
		$memory->set( 'cloudinary', 'api_key', 'test-key' );
		$memory->set( 'cloudinary', 'api_secret', 'test-secret' );
		$memory->set( 'cloudinary', 'folder', 'test-folder' );
		$memory->set( 'cloudinary', 'max_file_size', 5242880 );
		$memory->set( 'cloudinary', 'allowed_formats', ['jpg', 'png', 'gif', 'webp'] );
		$memory->set( 'site', 'name', 'Test Site' );

		$this->_settings = new SettingManager( $memory );
		Registry::getInstance()->set( 'Settings', $this->_settings );
	}

	protected function tearDown(): void
	{
		// Restore original registry values
		foreach( $this->_originalRegistry as $key => $value )
		{
			Registry::getInstance()->set( $key, $value );
		}

		parent::tearDown();
	}

	public function testIndexReturnsSuccessWithResources(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Create mocks for required dependencies
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create a partial mock that mocks renderHtml but allows view() to work
		$media = $this->getMockBuilder( Media::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockCloudinaryUploader, $mockMediaValidator ] )
			->onlyMethods( ['renderHtml'] )
			->getMock();

		$media->method( 'renderHtml' )->willReturn( '<html>test</html>' );

		$mockSessionManager->method( 'getFlash' )->willReturn( null );

		// Configure the uploader mock to return resources
		$mockCloudinaryUploader->method( 'listResources' )->willReturn( [
			'resources' => [
				[
					'public_id' => 'test-folder/image1',
					'url' => 'https://res.cloudinary.com/test/image/upload/v1/image1.jpg',
					'width' => 800,
					'height' => 600
				],
				[
					'public_id' => 'test-folder/image2',
					'url' => 'https://res.cloudinary.com/test/image/upload/v1/image2.jpg',
					'width' => 1024,
					'height' => 768
				]
			],
			'next_cursor' => 'abc123',
			'total_count' => 50
		] );

		$mockCloudinaryUploader->method( 'getRootFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'resolveLibraryFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'listFolders' )->willReturn( [] );
		$mockCloudinaryUploader->method( 'listTags' )->willReturn( [ 'hero' ] );
		$mockCloudinaryUploader->method( 'sanitizeTags' )->willReturn( [] );

		$request = $this->requestWithQuery();

		$result = $media->index( $request );

		// Should return HTML response
		$this->assertIsString( $result );
	}

	public function testIndexHandlesCursorParameter(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Create mocks for required dependencies
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create a partial mock that mocks renderHtml but allows view() to work
		$media = $this->getMockBuilder( Media::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockCloudinaryUploader, $mockMediaValidator ] )
			->onlyMethods( ['renderHtml'] )
			->getMock();

		$media->method( 'renderHtml' )->willReturn( '<html>test</html>' );

		$mockSessionManager->method( 'getFlash' )->willReturn( null );

		// Configure the uploader mock to expect cursor parameter
		$mockCloudinaryUploader->expects( $this->once() )
			->method( 'listResources' )
			->with( $this->callback( function( $options ) {
				return $options['next_cursor'] === 'xyz789'
					&& $options['max_results'] === 30
					&& $options['folder'] === 'test-folder';
			} ) )
			->willReturn( [
				'resources' => [],
				'next_cursor' => null,
				'total_count' => 0
			] );

		$mockCloudinaryUploader->method( 'getRootFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'resolveLibraryFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'listFolders' )->willReturn( [] );
		$mockCloudinaryUploader->method( 'listTags' )->willReturn( [] );
		$mockCloudinaryUploader->method( 'sanitizeTags' )->willReturn( [] );

		$request = $this->requestWithQuery( [ 'cursor' => 'xyz789' ] );

		$result = $media->index( $request );

		$this->assertIsString( $result );
	}

	public function testIndexHandlesListResourcesException(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Create mocks for required dependencies
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create a partial mock that mocks renderHtml but allows view() to work
		$media = $this->getMockBuilder( Media::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockCloudinaryUploader, $mockMediaValidator ] )
			->onlyMethods( ['renderHtml'] )
			->getMock();

		$media->method( 'renderHtml' )->willReturn( '<html>test</html>' );

		$mockSessionManager->method( 'getFlash' )->willReturn( null );

		// Configure the uploader mock to throw exception
		$mockCloudinaryUploader->method( 'listResources' )
			->willThrowException( new \Exception( 'Cloudinary API error' ) );

		$mockCloudinaryUploader->method( 'getRootFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'resolveLibraryFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'listFolders' )->willReturn( [] );
		$mockCloudinaryUploader->method( 'listTags' )->willReturn( [] );

		$request = $this->requestWithQuery();

		$result = $media->index( $request );

		// Should return HTML response with error message (not throw exception)
		$this->assertIsString( $result );
	}

	public function testIndexPassesTagFilter(): void
	{
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		$media = $this->getMockBuilder( Media::class )
			->setConstructorArgs( [ $this->_mockApp, $mockSettingManager, $mockSessionManager, $mockCloudinaryUploader, $mockMediaValidator ] )
			->onlyMethods( ['renderHtml'] )
			->getMock();

		$media->method( 'renderHtml' )->willReturn( '<html>test</html>' );
		$mockSessionManager->method( 'getFlash' )->willReturn( null );

		$mockCloudinaryUploader->method( 'getRootFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'resolveLibraryFolder' )->willReturn( 'test-folder' );
		$mockCloudinaryUploader->method( 'sanitizeTags' )->willReturn( [ 'hero' ] );
		$mockCloudinaryUploader->method( 'listFolders' )->willReturn( [] );
		$mockCloudinaryUploader->method( 'listTags' )->willReturn( [ 'hero' ] );
		$mockCloudinaryUploader->expects( $this->once() )
			->method( 'listResources' )
			->with( $this->callback( function( $options ) {
				return $options['tag'] === 'hero'
					&& $options['include_descendants'] === true;
			} ) )
			->willReturn( [
				'resources' => [],
				'next_cursor' => null,
				'total_count' => 0
			] );

		$result = $media->index( $this->requestWithQuery( [ 'tag' => 'hero' ] ) );

		$this->assertIsString( $result );
	}

	private function requestWithQuery( array $query = [] ): Request
	{
		$request = $this->createMock( Request::class );
		$request->method( 'get' )->willReturnCallback( function( $key, $default = null ) use ( $query ) {
			return $query[ $key ] ?? $default;
		} );

		return $request;
	}
}
