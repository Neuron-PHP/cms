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
	/** @var array<string, string> */
	private const MIME_ALIASES = [
		'image/jpeg' => 'jpg',
		'image/jpg' => 'jpg',
		'image/pjpeg' => 'jpg',
		'image/png' => 'png',
		'image/x-png' => 'png',
		'image/apng' => 'png',
		'image/gif' => 'gif',
		'image/webp' => 'webp'
	];

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
		if( !$this->validateFileSize( (int) $file['size'] ) )
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
		$maxSize = $this->maxFileSize();

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
		$allowedFormats = $this->allowedFormats();
		$extension = strtolower( pathinfo( $fileName, PATHINFO_EXTENSION ) );

		if( !in_array( $extension, $allowedFormats, true ) )
		{
			$this->_errors[] = 'File type not allowed. Allowed types: ' . implode( ', ', $allowedFormats );
			return false;
		}

		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$mimeType = (string) $finfo->file( $filePath );
		$signatureOk = $this->matchesImageSignature( $filePath, $extension );

		// finfo sometimes reports PNG as image/x-png or application/octet-stream.
		if( !$this->isMimeAllowed( $mimeType, $allowedFormats ) && !( $extension === 'png' && $signatureOk ) )
		{
			$this->_errors[] = 'Invalid file type. Must be a valid image file.';
			return false;
		}

		// iOS/optimized PNGs can fail getimagesize() while still being a PNG.
		$imageInfo = @getimagesize( $filePath );
		if( $imageInfo === false && !( $extension === 'png' && $signatureOk ) )
		{
			$this->_errors[] = 'File is not a valid image';
			return false;
		}

		return true;
	}

	/**
	 * Configured upload size limit in bytes.
	 */
	private function maxFileSize(): int
	{
		$maxSize = $this->_settings->get( 'cloudinary', 'max_file_size' )
			?? UploadConfig::MAX_FILE_SIZE_20MB;

		return max( 1, (int) $maxSize );
	}

	/**
	 * @return list<string>
	 */
	private function allowedFormats(): array
	{
		$formats = $this->_settings->get( 'cloudinary', 'allowed_formats' )
			?? UploadConfig::ALLOWED_IMAGE_FORMATS;

		if( is_string( $formats ) )
		{
			$formats = preg_split( '/\s*,\s*/', $formats ) ?: [];
		}

		if( !is_array( $formats ) )
		{
			$formats = UploadConfig::ALLOWED_IMAGE_FORMATS;
		}

		return array_values( array_filter( array_map(
			static fn( mixed $format ): string => strtolower( trim( (string) $format ) ),
			$formats
		) ) );
	}

	/**
	 * @param list<string> $allowedFormats
	 */
	private function isMimeAllowed( string $mimeType, array $allowedFormats ): bool
	{
		$format = self::MIME_ALIASES[ strtolower( $mimeType ) ] ?? null;

		if( $format === null )
		{
			return false;
		}

		if( $format === 'jpg' )
		{
			return in_array( 'jpg', $allowedFormats, true )
				|| in_array( 'jpeg', $allowedFormats, true );
		}

		return in_array( $format, $allowedFormats, true );
	}

	private function matchesImageSignature( string $filePath, string $extension ): bool
	{
		$handle = fopen( $filePath, 'rb' );

		if( $handle === false )
		{
			return false;
		}

		$header = fread( $handle, 12 );
		fclose( $handle );

		if( !is_string( $header ) || $header === '' )
		{
			return false;
		}

		return match( $extension )
		{
			'png' => str_starts_with( $header, "\x89PNG\r\n\x1a\n" ),
			'jpg', 'jpeg' => str_starts_with( $header, "\xFF\xD8\xFF" ),
			'gif' => str_starts_with( $header, 'GIF87a' ) || str_starts_with( $header, 'GIF89a' ),
			'webp' => strlen( $header ) >= 12
				&& str_starts_with( $header, 'RIFF' )
				&& substr( $header, 8, 4 ) === 'WEBP',
			default => false
		};
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
