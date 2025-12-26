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
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;

/**
 * Unit tests for Media controller index method
 */
class MediaIndexTest extends TestCase
{
	private array $_originalRegistry = [];
	private SettingManager $_settings;

	protected function setUp(): void
	{
		parent::setUp();

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

		// Create a partial mock that mocks renderHtml but allows view() to work
		$media = $this->getMockBuilder( Media::class )
			->onlyMethods( ['renderHtml'] )
			->getMock();

		$media->method( 'renderHtml' )->willReturn( '<html>test</html>' );

		// Create a mock session manager
		$sessionManagerMock = $this->createMock( SessionManager::class );
		$sessionManagerMock->method( 'getFlash' )->willReturn( null );

		// Inject mock session manager via reflection
		$reflection = new \ReflectionClass( get_parent_class( Media::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );
		$sessionProperty->setValue( $media, $sessionManagerMock );

		// Create a mock uploader that returns resources
		$uploaderMock = $this->createMock( CloudinaryUploader::class );
		$uploaderMock->method( 'listResources' )->willReturn( [
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

		// Inject mock uploader via reflection
		$reflection = new \ReflectionClass( Media::class );
		$uploaderProperty = $reflection->getProperty( '_uploader' );
		$uploaderProperty->setAccessible( true );
		$uploaderProperty->setValue( $media, $uploaderMock );

		$request = $this->createMock( Request::class );
		$request->method( 'get' )->with( 'cursor' )->willReturn( null );

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

		// Create a partial mock that mocks renderHtml but allows view() to work
		$media = $this->getMockBuilder( Media::class )
			->onlyMethods( ['renderHtml'] )
			->getMock();

		$media->method( 'renderHtml' )->willReturn( '<html>test</html>' );

		// Create a mock session manager
		$sessionManagerMock = $this->createMock( SessionManager::class );
		$sessionManagerMock->method( 'getFlash' )->willReturn( null );

		// Inject mock session manager via reflection
		$reflection = new \ReflectionClass( get_parent_class( Media::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );
		$sessionProperty->setValue( $media, $sessionManagerMock );

		// Create a mock uploader that expects cursor parameter
		$uploaderMock = $this->createMock( CloudinaryUploader::class );
		$uploaderMock->expects( $this->once() )
			->method( 'listResources' )
			->with( $this->callback( function( $options ) {
				return $options['next_cursor'] === 'xyz789'
					&& $options['max_results'] === 30;
			} ) )
			->willReturn( [
				'resources' => [],
				'next_cursor' => null,
				'total_count' => 0
			] );

		// Inject mock uploader via reflection
		$reflection = new \ReflectionClass( Media::class );
		$uploaderProperty = $reflection->getProperty( '_uploader' );
		$uploaderProperty->setAccessible( true );
		$uploaderProperty->setValue( $media, $uploaderMock );

		$request = $this->createMock( Request::class );
		$request->method( 'get' )->with( 'cursor' )->willReturn( 'xyz789' );

		$result = $media->index( $request );

		$this->assertIsString( $result );
	}

	public function testIndexHandlesListResourcesException(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Create a partial mock that mocks renderHtml but allows view() to work
		$media = $this->getMockBuilder( Media::class )
			->onlyMethods( ['renderHtml'] )
			->getMock();

		$media->method( 'renderHtml' )->willReturn( '<html>test</html>' );

		// Create a mock session manager
		$sessionManagerMock = $this->createMock( SessionManager::class );
		$sessionManagerMock->method( 'getFlash' )->willReturn( null );

		// Inject mock session manager via reflection
		$reflection = new \ReflectionClass( get_parent_class( Media::class ) );
		$sessionProperty = $reflection->getProperty( '_sessionManager' );
		$sessionProperty->setAccessible( true );
		$sessionProperty->setValue( $media, $sessionManagerMock );

		// Create a mock uploader that throws exception
		$uploaderMock = $this->createMock( CloudinaryUploader::class );
		$uploaderMock->method( 'listResources' )
			->willThrowException( new \Exception( 'Cloudinary API error' ) );

		// Inject mock uploader via reflection
		$reflection = new \ReflectionClass( Media::class );
		$uploaderProperty = $reflection->getProperty( '_uploader' );
		$uploaderProperty->setAccessible( true );
		$uploaderProperty->setValue( $media, $uploaderMock );

		$request = $this->createMock( Request::class );
		$request->method( 'get' )->with( 'cursor' )->willReturn( null );

		$result = $media->index( $request );

		// Should return HTML response with error message (not throw exception)
		$this->assertIsString( $result );
	}
}
