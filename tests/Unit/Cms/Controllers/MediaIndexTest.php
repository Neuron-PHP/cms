<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Media;
use Neuron\Cms\Models\User;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
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

	public function testIndexThrowsExceptionWhenUserNotAuthenticated(): void
	{
		// Ensure no user in registry
		Registry::getInstance()->set( 'Auth.User', null );

		$media = new Media();
		$request = $this->createMock( Request::class );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Authenticated user not found' );

		$media->index( $request );
	}

	/**
	 * Note: Additional tests for index() method that verify the full rendering
	 * would require view files to be present and the framework to be fully bootstrapped.
	 * These are better suited for integration tests. The authentication check test above
	 * covers the critical security logic in the unit test context.
	 */
}
