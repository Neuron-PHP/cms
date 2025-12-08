<?php

namespace Neuron\Cms\Services\Media;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Neuron\Data\Settings\SettingManager;

/**
 * Cloudinary implementation of media uploader.
 *
 * Handles file uploads and deletions using Cloudinary service.
 *
 * @package Neuron\Cms\Services\Media
 */
class CloudinaryUploader implements IMediaUploader
{
	private Cloudinary $_cloudinary;
	private SettingManager $_settings;

	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Application settings manager
	 * @throws \Exception If Cloudinary configuration is missing
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_settings = $settings;
		$this->_cloudinary = $this->initializeCloudinary();
	}

	/**
	 * Initialize Cloudinary instance
	 *
	 * @return Cloudinary
	 * @throws \Exception If configuration is invalid
	 */
	private function initializeCloudinary(): Cloudinary
	{
		$cloudName = $this->_settings->get( 'cloudinary', 'cloud_name' );
		$apiKey = $this->_settings->get( 'cloudinary', 'api_key' );
		$apiSecret = $this->_settings->get( 'cloudinary', 'api_secret' );

		if( !$cloudName || !$apiKey || !$apiSecret )
		{
			throw new \Exception( 'Cloudinary configuration is incomplete. Please set cloud_name, api_key, and api_secret in config/neuron.yaml' );
		}

		return new Cloudinary( [
			'cloud' => [
				'cloud_name' => $cloudName,
				'api_key' => $apiKey,
				'api_secret' => $apiSecret
			]
		] );
	}

	/**
	 * Upload a file from local filesystem
	 *
	 * @param string $filePath Path to the file to upload
	 * @param array $options Upload options (folder, transformation, etc.)
	 * @return array Upload result with keys: url, public_id, width, height, format
	 * @throws \Exception If upload fails
	 */
	public function upload( string $filePath, array $options = [] ): array
	{
		if( !file_exists( $filePath ) )
		{
			throw new \Exception( "File not found: {$filePath}" );
		}

		// Merge with default options from config
		$uploadOptions = $this->buildUploadOptions( $options );

		try
		{
			$uploadApi = $this->_cloudinary->uploadApi();
			$result = $uploadApi->upload( $filePath, $uploadOptions );

			return $this->formatResult( $result );
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary upload failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Upload a file from URL
	 *
	 * @param string $url URL of the file to upload
	 * @param array $options Upload options (folder, transformation, etc.)
	 * @return array Upload result with keys: url, public_id, width, height, format
	 * @throws \Exception If upload fails
	 */
	public function uploadFromUrl( string $url, array $options = [] ): array
	{
		// Validate URL
		if( !filter_var( $url, FILTER_VALIDATE_URL ) )
		{
			throw new \Exception( "Invalid URL: {$url}" );
		}

		// Merge with default options from config
		$uploadOptions = $this->buildUploadOptions( $options );

		try
		{
			$uploadApi = $this->_cloudinary->uploadApi();
			$result = $uploadApi->upload( $url, $uploadOptions );

			return $this->formatResult( $result );
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary upload from URL failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Delete a file by its public ID
	 *
	 * @param string $publicId The public ID of the file to delete
	 * @return bool True if deletion was successful
	 * @throws \Exception If deletion fails
	 */
	public function delete( string $publicId ): bool
	{
		try
		{
			$uploadApi = $this->_cloudinary->uploadApi();
			$result = $uploadApi->destroy( $publicId );

			return isset( $result['result'] ) && $result['result'] === 'ok';
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary deletion failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Build upload options by merging user options with config defaults
	 *
	 * @param array $options User-provided options
	 * @return array Complete upload options
	 */
	private function buildUploadOptions( array $options ): array
	{
		$defaultFolder = $this->_settings->get( 'cloudinary', 'folder' ) ?? 'neuron-cms/images';

		$uploadOptions = [
			'folder' => $options['folder'] ?? $defaultFolder,
			'resource_type' => 'image'
		];

		// Add any additional options passed by the user
		if( isset( $options['public_id'] ) )
		{
			$uploadOptions['public_id'] = $options['public_id'];
		}

		if( isset( $options['transformation'] ) )
		{
			$uploadOptions['transformation'] = $options['transformation'];
		}

		if( isset( $options['tags'] ) )
		{
			$uploadOptions['tags'] = $options['tags'];
		}

		return $uploadOptions;
	}

	/**
	 * Format Cloudinary result into standardized array
	 *
	 * @param array $result Cloudinary upload result
	 * @return array Formatted result
	 */
	private function formatResult( array $result ): array
	{
		return [
			'url' => $result['secure_url'] ?? $result['url'] ?? '',
			'public_id' => $result['public_id'] ?? '',
			'width' => $result['width'] ?? 0,
			'height' => $result['height'] ?? 0,
			'format' => $result['format'] ?? '',
			'bytes' => $result['bytes'] ?? 0,
			'resource_type' => $result['resource_type'] ?? 'image',
			'created_at' => $result['created_at'] ?? ''
		];
	}
}
