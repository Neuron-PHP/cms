<?php

namespace Neuron\Cms\Services\Media;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
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
	 * @throws \Exception If upload fails or URL is unsafe
	 */
	public function uploadFromUrl( string $url, array $options = [] ): array
	{
		// Validate URL against SSRF attacks
		$this->validateUrlAgainstSsrf( $url );

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
	 * List resources from Cloudinary
	 *
	 * @param array $options Options for listing (max_results, next_cursor, prefix, etc.)
	 * @return array Resources list with pagination info
	 * @throws \Exception If listing fails
	 */
	public function listResources( array $options = [] ): array
	{
		try
		{
			$adminApi = $this->_cloudinary->adminApi();

			// Get folder prefix from settings or options
			$folder = $options['folder'] ?? $this->_settings->get( 'cloudinary', 'folder' ) ?? 'neuron-cms/images';

			$listOptions = [
				'type' => 'upload',
				'prefix' => $folder,
				'max_results' => $options['max_results'] ?? 30,
				'resource_type' => 'image'
			];

			// Add pagination cursor if provided
			if( isset( $options['next_cursor'] ) )
			{
				$listOptions['next_cursor'] = $options['next_cursor'];
			}

			$result = $adminApi->assets( $listOptions );

			// Format the result
			return [
				'resources' => array_map( [ $this, 'formatResult' ], $result['resources'] ?? [] ),
				'next_cursor' => $result['next_cursor'] ?? null,
				'total_count' => $result['total_count'] ?? 0
			];
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary list resources failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Validate URL against SSRF (Server-Side Request Forgery) attacks
	 *
	 * Ensures the URL:
	 * - Is a valid URL
	 * - Uses HTTPS protocol only
	 * - Does not resolve to private/internal IP addresses
	 * - Does not target loopback, link-local, or cloud metadata addresses
	 *
	 * @param string $url The URL to validate
	 * @return void
	 * @throws \Exception If URL is invalid or unsafe
	 */
	private function validateUrlAgainstSsrf( string $url ): void
	{
		// Basic URL validation
		if( !filter_var( $url, FILTER_VALIDATE_URL ) )
		{
			throw new \Exception( "Invalid URL format: {$url}" );
		}

		// Parse URL components
		$parsedUrl = parse_url( $url );

		if( $parsedUrl === false || !isset( $parsedUrl['scheme'] ) || !isset( $parsedUrl['host'] ) )
		{
			throw new \Exception( "Failed to parse URL: {$url}" );
		}

		// Require HTTPS only
		if( strtolower( $parsedUrl['scheme'] ) !== 'https' )
		{
			throw new \Exception( "Only HTTPS URLs are allowed for security reasons. Provided: {$parsedUrl['scheme']}" );
		}

		$host = $parsedUrl['host'];

		// Check if host is already an IP address
		if( filter_var( $host, FILTER_VALIDATE_IP ) !== false )
		{
			// Host is an IP address, validate it directly
			if( $this->isPrivateOrReservedIp( $host ) )
			{
				throw new \Exception( "URL uses a private or reserved IP address ({$host}). Access denied for security reasons." );
			}
			$ips = [ $host ];
		}
		else
		{
			// Host is a hostname, resolve to IP addresses
			$ips = $this->resolveHostnameToIps( $host );

			if( empty( $ips ) )
			{
				throw new \Exception( "Unable to resolve hostname: {$host}" );
			}

			// Check each resolved IP against blocked ranges
			foreach( $ips as $ip )
			{
				if( $this->isPrivateOrReservedIp( $ip ) )
				{
					throw new \Exception( "URL resolves to a private or reserved IP address ({$ip}). Access denied for security reasons." );
				}
			}
		}
	}

	/**
	 * Resolve hostname to IP addresses (both IPv4 and IPv6)
	 *
	 * @param string $hostname The hostname to resolve
	 * @return array Array of IP addresses
	 */
	private function resolveHostnameToIps( string $hostname ): array
	{
		$ips = [];

		// Get IPv4 addresses
		$ipv4Records = @dns_get_record( $hostname, DNS_A );
		if( $ipv4Records !== false )
		{
			foreach( $ipv4Records as $record )
			{
				if( isset( $record['ip'] ) )
				{
					$ips[] = $record['ip'];
				}
			}
		}

		// Get IPv6 addresses
		$ipv6Records = @dns_get_record( $hostname, DNS_AAAA );
		if( $ipv6Records !== false )
		{
			foreach( $ipv6Records as $record )
			{
				if( isset( $record['ipv6'] ) )
				{
					$ips[] = $record['ipv6'];
				}
			}
		}

		return $ips;
	}

	/**
	 * Check if an IP address is private, loopback, link-local, or reserved
	 *
	 * Blocks the following ranges:
	 * - Private IPv4: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
	 * - Loopback IPv4: 127.0.0.0/8
	 * - Link-local IPv4: 169.254.0.0/16
	 * - Loopback IPv6: ::1
	 * - Private IPv6: fc00::/7
	 * - Link-local IPv6: fe80::/10
	 * - IPv4-mapped IPv6: ::ffff:0:0/96
	 *
	 * @param string $ip The IP address to check
	 * @return bool True if IP is private/reserved, false otherwise
	 */
	private function isPrivateOrReservedIp( string $ip ): bool
	{
		// Use filter_var with FILTER_VALIDATE_IP and appropriate flags
		// This checks for private and reserved ranges
		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

		// If filter_var returns false, the IP is in a private or reserved range
		return filter_var( $ip, FILTER_VALIDATE_IP, $flags ) === false;
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
