<?php

namespace Neuron\Cms\Services\Media;

use Neuron\Cms\Config\UploadConfig;
use Neuron\Data\Settings\SettingManager;

/**
 * Media file validator.
 *
 * Validates uploaded files against configuration rules.
 *
 * @package Neuron\Cms\Services\Media
 */
class MediaValidator
{
	private SettingManager $_settings;
	private array $_errors = [];

	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Application settings manager
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_settings = $settings;
	}

	/**
	 * Validate an uploaded file
	 *
	 * @param array $file PHP $_FILES array entry
	 * @return bool True if valid, false otherwise
	 */
	public function validate( array $file ): bool
	{
		$this->_errors = [];

		// Check if file was uploaded
		if( !isset( $file['error'] ) || !isset( $file['tmp_name'] ) )
		{
			$this->_errors[] = 'No file was uploaded';
			return false;
		}

		// Check for upload errors
		if( $file['error'] !== UPLOAD_ERR_OK )
		{
			$this->_errors[] = $this->getUploadErrorMessage( $file['error'] );
			return false;
		}

		// Check if file exists
		if( !file_exists( $file['tmp_name'] ) )
		{
			$this->_errors[] = 'Uploaded file not found';
			return false;
		}

		// Validate file size
		if( !$this->validateFileSize( $file['size'] ) )
		{
			return false;
		}

		// Validate file type
		if( !$this->validateFileType( $file['tmp_name'], $file['name'] ) )
		{
			return false;
		}

		return true;
	}

	/**
	 * Validate file size
	 *
	 * @param int $size File size in bytes
	 * @return bool True if valid
	 */
	private function validateFileSize( int $size ): bool
	{
		$maxSize = $this->_settings->get( 'cloudinary', 'max_file_size' ) ?? UploadConfig::MAX_FILE_SIZE_5MB;

		if( $size > $maxSize )
		{
			$maxSizeMB = round( $maxSize / UploadConfig::BYTES_PER_MB, 2 );
			$this->_errors[] = "File size exceeds maximum allowed size of {$maxSizeMB}MB";
			return false;
		}

		if( $size === 0 )
		{
			$this->_errors[] = 'File is empty';
			return false;
		}

		return true;
	}

	/**
	 * Validate file type
	 *
	 * @param string $filePath Path to the file
	 * @param string $fileName Original filename
	 * @return bool True if valid
	 */
	private function validateFileType( string $filePath, string $fileName ): bool
	{
		$allowedFormats = $this->_settings->get( 'cloudinary', 'allowed_formats' )
			?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];

		// Get file extension
		$extension = strtolower( pathinfo( $fileName, PATHINFO_EXTENSION ) );

		if( !in_array( $extension, $allowedFormats ) )
		{
			$this->_errors[] = 'File type not allowed. Allowed types: ' . implode( ', ', $allowedFormats );
			return false;
		}

		// Verify MIME type
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mimeType = finfo_file( $finfo, $filePath );
		finfo_close( $finfo );

		$allowedMimeTypes = [
			'image/jpeg',
			'image/jpg',
			'image/png',
			'image/gif',
			'image/webp'
		];

		if( !in_array( $mimeType, $allowedMimeTypes ) )
		{
			$this->_errors[] = 'Invalid file type. Must be a valid image file.';
			return false;
		}

		// Additional security check: verify it's actually an image
		$imageInfo = @getimagesize( $filePath );
		if( $imageInfo === false )
		{
			$this->_errors[] = 'File is not a valid image';
			return false;
		}

		return true;
	}

	/**
	 * Get upload error message
	 *
	 * @param int $error PHP upload error code
	 * @return string Error message
	 */
	private function getUploadErrorMessage( int $error ): string
	{
		return match( $error )
		{
			UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
			UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
			UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
			UPLOAD_ERR_NO_FILE => 'No file was uploaded',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
			UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
			default => 'Unknown upload error'
		};
	}

	/**
	 * Get validation errors
	 *
	 * @return array Array of error messages
	 */
	public function getErrors(): array
	{
		return $this->_errors;
	}

	/**
	 * Get first validation error
	 *
	 * @return string|null First error message or null if no errors
	 */
	public function getFirstError(): ?string
	{
		return $this->_errors[0] ?? null;
	}
}
