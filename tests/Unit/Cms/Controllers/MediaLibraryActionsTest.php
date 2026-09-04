<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cms\Controllers\Admin\Media;
use Neuron\Cms\Models\User;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;

/**
 * Unit tests for media library list, update, move, and folder actions.
 */
class MediaLibraryActionsTest extends TestCase
{
	private array $_originalRegistry = [];
	private IMvcApplication $_mockApp;

	protected function setUp(): void
	{
		parent::setUp();

		$router = $this->createMock( Router::class );
		$this->_mockApp = $this->createMock( IMvcApplication::class );
		$this->_mockApp->method( 'getRouter' )->willReturn( $router );

		$this->_originalRegistry = [
			'Settings' => Registry::getInstance()->get( RegistryKeys::SETTINGS )
		];

		$memory = new Memory();
		$memory->set( 'cloudinary', 'cloud_name', 'test-cloud' );
		$memory->set( 'cloudinary', 'api_key', 'test-key' );
		$memory->set( 'cloudinary', 'api_secret', 'test-secret' );
		$memory->set( 'cloudinary', 'folder', 'test-folder' );
		$memory->set( 'cloudinary', 'max_file_size', 5242880 );
		$memory->set( 'cloudinary', 'allowed_formats', [ 'jpg', 'png', 'gif', 'webp' ] );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, new SettingManager( $memory ) );
	}

	protected function tearDown(): void
	{
		foreach( $this->_originalRegistry as $key => $value )
		{
			Registry::getInstance()->set( $key, $value );
		}

		$_FILES = [];

		parent::tearDown();
	}

	public function testListMediaReturnsJsonLibraryPayload(): void
	{
		$this->setUser();
		$uploader = $this->createMock( CloudinaryUploader::class );
		$uploader->method( 'getRootFolder' )->willReturn( 'test-folder' );
		$uploader->method( 'resolveLibraryFolder' )->willReturn( 'test-folder' );
		$uploader->method( 'sanitizeTags' )->willReturn( [] );
		$uploader->method( 'listResources' )->willReturn( [
			'resources' => [
				[
					'public_id' => 'test-folder/image1',
					'url' => 'https://res.cloudinary.com/test/image1.jpg',
					'name' => 'image1'
				]
			],
			'next_cursor' => null,
			'total_count' => 10
		] );
		$uploader->method( 'listFolders' )->willReturn( [
			[ 'name' => 'blog', 'path' => 'test-folder/blog' ]
		] );
		$uploader->method( 'listTags' )->willReturn( [ 'hero' ] );

		$media = $this->makeMedia( $uploader );
		$result = $media->listMedia( $this->requestWithQuery() );
		$json = json_decode( $result, true );

		$this->assertTrue( $json['success'] );
		$this->assertEquals( 10, $json['total_count'] );
		$this->assertCount( 1, $json['resources'] );
		$this->assertEquals( [ 'hero' ], $json['tags'] );
		$this->assertEquals( 'test-folder/blog', $json['folders'][0]['path'] );
	}

	public function testUpdateMediaRejectsInvalidPublicId(): void
	{
		$this->setUser();
		$media = $this->makeMedia();
		$result = $media->updateMedia( $this->requestWithPost( [ 'public_id' => 'test-folder/bad id$' ] ) );
		$json = json_decode( $result, true );

		$this->assertFalse( $json['success'] );
		$this->assertEquals( 'Invalid image identifier', $json['error'] );
	}

	public function testUpdateMediaRejectsOutsideFolder(): void
	{
		$this->setUser();
		$media = $this->makeMedia();
		$result = $media->updateMedia( $this->requestWithPost( [ 'public_id' => 'other-folder/image' ] ) );
		$json = json_decode( $result, true );

		$this->assertFalse( $json['success'] );
		$this->assertEquals( 'Image cannot be updated', $json['error'] );
	}

	public function testUpdateMediaSuccessful(): void
	{
		$this->setUser();
		$uploader = $this->createMock( CloudinaryUploader::class );
		$uploader->method( 'isLibraryAsset' )->willReturn( true );
		$uploader->expects( $this->once() )
			->method( 'updateResource' )
			->with( 'test-folder/image', [ 'name' => 'Hero', 'tags' => 'hero' ] )
			->willReturn( [
				'public_id' => 'test-folder/image',
				'name' => 'Hero',
				'tags' => [ 'hero' ]
			] );

		$media = $this->makeMedia( $uploader );
		$result = $media->updateMedia( $this->requestWithPost( [
			'public_id' => 'test-folder/image',
			'name' => 'Hero',
			'tags' => 'hero'
		] ) );
		$json = json_decode( $result, true );

		$this->assertTrue( $json['success'] );
		$this->assertEquals( 'Hero', $json['data']['name'] );
	}

	public function testMoveMediaRequiresFolder(): void
	{
		$this->setUser();
		$uploader = $this->createMock( CloudinaryUploader::class );
		$uploader->method( 'isLibraryAsset' )->willReturn( true );

		$media = $this->makeMedia( $uploader );
		$result = $media->moveMedia( $this->requestWithPost( [ 'public_id' => 'test-folder/image' ] ) );
		$json = json_decode( $result, true );

		$this->assertFalse( $json['success'] );
		$this->assertEquals( 'A destination folder is required', $json['error'] );
	}

	public function testMoveMediaSuccessful(): void
	{
		$this->setUser();
		$uploader = $this->createMock( CloudinaryUploader::class );
		$uploader->method( 'isLibraryAsset' )->willReturn( true );
		$uploader->expects( $this->once() )
			->method( 'moveResource' )
			->with( 'test-folder/image', 'test-folder/blog' )
			->willReturn( [
				'public_id' => 'test-folder/blog/image',
				'asset_folder' => 'test-folder/blog',
				'url' => 'https://example.com/blog/image.jpg',
				'url_changed' => true
			] );

		$media = $this->makeMedia( $uploader );
		$result = $media->moveMedia( $this->requestWithPost( [
			'public_id' => 'test-folder/image',
			'folder' => 'test-folder/blog'
		] ) );
		$json = json_decode( $result, true );

		$this->assertTrue( $json['success'] );
		$this->assertTrue( $json['data']['url_changed'] );
	}

	public function testCreateFolderRequiresName(): void
	{
		$this->setUser();
		$media = $this->makeMedia();
		$result = $media->createFolder( $this->requestWithPost( [] ) );
		$json = json_decode( $result, true );

		$this->assertFalse( $json['success'] );
		$this->assertEquals( 'A folder name is required', $json['error'] );
	}

	public function testCreateFolderSuccessful(): void
	{
		$this->setUser();
		$uploader = $this->createMock( CloudinaryUploader::class );
		$uploader->expects( $this->once() )
			->method( 'createFolder' )
			->with( 'blog' )
			->willReturn( [ 'name' => 'blog', 'path' => 'test-folder/blog' ] );

		$media = $this->makeMedia( $uploader );
		$result = $media->createFolder( $this->requestWithPost( [ 'name' => 'blog' ] ) );
		$json = json_decode( $result, true );

		$this->assertTrue( $json['success'] );
		$this->assertEquals( 'test-folder/blog', $json['data']['path'] );
	}

	public function testDeleteMediaAllowsDynamicFolderAsset(): void
	{
		$this->setUser();
		$uploader = $this->createMock( CloudinaryUploader::class );
		$uploader->method( 'isLibraryAsset' )
			->with( 'randomid', 'test-folder' )
			->willReturn( true );
		$uploader->expects( $this->once() )
			->method( 'delete' )
			->with( 'randomid' )
			->willReturn( true );

		$media = $this->makeMedia( $uploader );
		$result = $media->deleteMedia( $this->requestWithPost( [
			'public_id' => 'randomid',
			'asset_folder' => 'test-folder'
		] ) );
		$json = json_decode( $result, true );

		$this->assertTrue( $json['success'] );
	}

	private function setUser(): void
	{
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );
	}

	private function makeMedia( ?CloudinaryUploader $uploader = null ): Media
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$session = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$validator = $this->createMock( MediaValidator::class );

		return new Media(
			$this->_mockApp,
			$settings,
			$session,
			$uploader ?? $this->createMock( CloudinaryUploader::class ),
			$validator
		);
	}

	private function requestWithQuery( array $query = [] ): Request
	{
		$request = $this->createMock( Request::class );
		$request->method( 'get' )->willReturnCallback( function( $key, $default = null ) use ( $query ) {
			return $query[ $key ] ?? $default;
		} );

		return $request;
	}

	private function requestWithPost( array $post ): Request
	{
		$request = $this->createMock( Request::class );
		$request->method( 'post' )->willReturnCallback( function( $key, $default = null ) use ( $post ) {
			return $post[ $key ] ?? $default;
		} );

		return $request;
	}
}
