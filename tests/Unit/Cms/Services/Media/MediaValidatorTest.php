<?php

namespace Tests\Cms\Services\Media;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use org\bovigo\vfs\vfsStream;

/**
 * Test MediaValidator service
 */
class MediaValidatorTest extends TestCase
{
	private SettingManager $_settings;
	private MediaValidator $_validator;
	private $vfs;

	protected function setUp(): void
	{
		parent::setUp();

		// Set up virtual filesystem
		$this->vfs = vfsStream::setup( 'uploads' );

		// Create in-memory settings for testing
		$memory = new Memory();
		$memory->set( 'cloudinary', 'max_file_size', 5242880 ); // 5MB
		$memory->set( 'cloudinary', 'allowed_formats', ['jpg', 'jpeg', 'png', 'gif', 'webp'] );

		$this->_settings = new SettingManager( $memory );
		$this->_validator = new MediaValidator( $this->_settings );
	}

	public function testValidateReturnsFalseForMissingFile(): void
	{
		$file = [];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertCount( 1, $this->_validator->getErrors() );
		$this->assertEquals( 'No file was uploaded', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForUploadError(): void
	{
		$file = [
			'error' => UPLOAD_ERR_NO_FILE,
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'No file was uploaded', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForIniSizeError(): void
	{
		$file = [
			'error' => UPLOAD_ERR_INI_SIZE,
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'upload_max_filesize', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForFormSizeError(): void
	{
		$file = [
			'error' => UPLOAD_ERR_FORM_SIZE,
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'MAX_FILE_SIZE', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForPartialUploadError(): void
	{
		$file = [
			'error' => UPLOAD_ERR_PARTIAL,
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'partially uploaded', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForNoTmpDirError(): void
	{
		$file = [
			'error' => UPLOAD_ERR_NO_TMP_DIR,
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'temporary folder', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForCantWriteError(): void
	{
		$file = [
			'error' => UPLOAD_ERR_CANT_WRITE,
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'write file to disk', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForExtensionError(): void
	{
		$file = [
			'error' => UPLOAD_ERR_EXTENSION,
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'extension stopped', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForUnknownError(): void
	{
		$file = [
			'error' => 999, // Unknown error code
			'tmp_name' => ''
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'Unknown', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForNonExistentFile(): void
	{
		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => '/nonexistent/file.jpg',
			'name' => 'test.jpg',
			'size' => 1024
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertEquals( 'Uploaded file not found', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForEmptyFile(): void
	{
		// Create empty file
		$testFile = vfsStream::newFile( 'empty.jpg' )->at( $this->vfs );
		$testFile->setContent( '' );

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $testFile->url(),
			'name' => 'empty.jpg',
			'size' => 0
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertEquals( 'File is empty', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForOversizedFile(): void
	{
		// Create a file
		$testFile = vfsStream::newFile( 'large.jpg' )->at( $this->vfs );
		$testFile->setContent( 'fake content' );

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $testFile->url(),
			'name' => 'large.jpg',
			'size' => 10485760 // 10MB - exceeds 5MB limit
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'exceeds maximum allowed size', $this->_validator->getFirstError() );
	}

	public function testValidateReturnsFalseForDisallowedExtension(): void
	{
		// Create a file
		$testFile = vfsStream::newFile( 'test.txt' )->at( $this->vfs );
		$testFile->setContent( 'not an image' );

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $testFile->url(),
			'name' => 'test.txt',
			'size' => 1024
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'File type not allowed', $this->_validator->getFirstError() );
	}

	public function testGetErrorsReturnsAllErrors(): void
	{
		$file = [];

		$this->_validator->validate( $file );

		$errors = $this->_validator->getErrors();

		$this->assertIsArray( $errors );
		$this->assertNotEmpty( $errors );
	}

	public function testGetFirstErrorReturnsFirstError(): void
	{
		$file = [];

		$this->_validator->validate( $file );

		$firstError = $this->_validator->getFirstError();

		$this->assertIsString( $firstError );
		$this->assertEquals( 'No file was uploaded', $firstError );
	}

	public function testGetFirstErrorReturnsNullWhenNoErrors(): void
	{
		$firstError = $this->_validator->getFirstError();

		$this->assertNull( $firstError );
	}

	public function testValidatePassesForValidJpegFile(): void
	{
		// Create a minimal valid JPEG file
		$jpegData = base64_decode(
			'/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a' .
			'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIy' .
			'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIA' .
			'AhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEB' .
			'AQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA=='
		);

		$tmpFile = tmpfile();
		$tmpPath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, $jpegData );

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $tmpPath,
			'name' => 'test.jpg',
			'size' => strlen( $jpegData )
		];

		$result = $this->_validator->validate( $file );

		$this->assertTrue( $result );
		$this->assertEmpty( $this->_validator->getErrors() );

		fclose( $tmpFile );
	}

	public function testValidatePassesForValidPngFile(): void
	{
		// Create a minimal valid PNG file (1x1 transparent pixel)
		$pngData = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
		);

		$tmpFile = tmpfile();
		$tmpPath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, $pngData );

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $tmpPath,
			'name' => 'test.png',
			'size' => strlen( $pngData )
		];

		$result = $this->_validator->validate( $file );

		$this->assertTrue( $result );
		$this->assertEmpty( $this->_validator->getErrors() );

		fclose( $tmpFile );
	}

	public function testValidateFailsForInvalidMimeType(): void
	{
		// Create a text file with .jpg extension (MIME type mismatch)
		$tmpFile = tmpfile();
		$tmpPath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, 'This is not an image' );

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $tmpPath,
			'name' => 'fake.jpg',
			'size' => 20
		];

		$result = $this->_validator->validate( $file );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'Invalid file type', $this->_validator->getFirstError() );

		fclose( $tmpFile );
	}

	public function testValidateFailsForNonImageFile(): void
	{
		// Create a file that has correct MIME but isn't a valid image
		$tmpFile = tmpfile();
		$tmpPath = stream_get_meta_data( $tmpFile )['uri'];
		// Write some binary data that might pass MIME check but fail getimagesize
		fwrite( $tmpFile, "\xFF\xD8\xFF" . str_repeat( 'X', 100 ) ); // Fake JPEG header

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $tmpPath,
			'name' => 'corrupt.jpg',
			'size' => 103
		];

		$result = $this->_validator->validate( $file );

		// Should fail either on MIME or getimagesize check
		$this->assertFalse( $result );

		fclose( $tmpFile );
	}

	public function testValidatePassesForValidGifFile(): void
	{
		// Create a minimal valid GIF file (1x1 transparent pixel)
		$gifData = base64_decode(
			'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
		);

		$tmpFile = tmpfile();
		$tmpPath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, $gifData );

		$file = [
			'error' => UPLOAD_ERR_OK,
			'tmp_name' => $tmpPath,
			'name' => 'test.gif',
			'size' => strlen( $gifData )
		];

		$result = $this->_validator->validate( $file );

		$this->assertTrue( $result );
		$this->assertEmpty( $this->_validator->getErrors() );

		fclose( $tmpFile );
	}
}
