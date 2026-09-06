<?php

namespace Neuron\Cms\Services\Media;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
use Neuron\Data\Settings\SettingManager;

/**
 * Cloudinary implementation of media uploader.
 *
 * Handles file uploads, deletions, listing, folders, tags, and display names
 * using Cloudinary as the source of truth.
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
			],
			// SDK 2.14.0 Logger.php still references E_STRICT, which PHP 8.4
			// deprecates. Neuron already logs through its own Log singleton.
			'logging' => [
				'enabled' => false
			]
		] );
	}

	/**
	 * Configured Cloudinary library root folder.
	 *
	 * @return string
	 */
	public function getRootFolder(): string
	{
		return trim( (string)( $this->_settings->get( 'cloudinary', 'folder' ) ?? 'neuron-cms/images' ), '/' );
	}

	/**
	 * Resolve a requested folder to a full path under the library root.
	 *
	 * Empty input returns the root. Relative segments are appended to the root.
	 * Paths outside the root, traversal, or unsafe characters throw.
	 *
	 * @param string|null $folder Requested folder (relative or full path)
	 * @return string Absolute library folder path
	 * @throws \InvalidArgumentException If the path is unsafe or outside the root
	 */
	public function resolveLibraryFolder( ?string $folder ): string
	{
		$root = $this->getRootFolder();
		$folder = trim( (string) $folder, '/' );

		if( $folder === '' )
		{
			return $root;
		}

		if( !$this->isSafeFolderPath( $folder ) )
		{
			throw new \InvalidArgumentException( 'Invalid folder path' );
		}

		if( $root !== '' && ( $folder === $root || str_starts_with( $folder, $root . '/' ) ) )
		{
			return $folder;
		}

		$resolved = $root === '' ? $folder : $root . '/' . $folder;

		if( !$this->isSafeFolderPath( $resolved ) )
		{
			throw new \InvalidArgumentException( 'Invalid folder path' );
		}

		return $resolved;
	}

	/**
	 * Whether an asset belongs to the configured library root.
	 *
	 * Accepts fixed-folder public IDs (root prefix) and dynamic-folder assets
	 * whose asset_folder is under the root.
	 *
	 * @param string $publicId Cloudinary public ID
	 * @param string|null $assetFolder Cloudinary asset_folder, if known
	 * @return bool
	 */
	public function isLibraryAsset( string $publicId, ?string $assetFolder = null ): bool
	{
		$root = $this->getRootFolder();

		if( $root === '' )
		{
			return $publicId !== '' && !str_contains( $publicId, '..' );
		}

		if( str_starts_with( $publicId, $root . '/' ) )
		{
			return !str_contains( $publicId, '..' );
		}

		$assetFolder = trim( (string) $assetFolder, '/' );

		if( $assetFolder === '' )
		{
			return false;
		}

		return $assetFolder === $root || str_starts_with( $assetFolder, $root . '/' );
	}

	/**
	 * Sanitize a comma-separated or array of Cloudinary tags.
	 *
	 * @param string|array $tags
	 * @return array<int, string>
	 */
	public function sanitizeTags( string|array $tags ): array
	{
		$parts = is_array( $tags ) ? $tags : explode( ',', $tags );
		$clean = [];

		foreach( $parts as $tag )
		{
			$tag = strtolower( trim( (string) $tag ) );
			$tag = preg_replace( '/[^a-z0-9_-]/', '', $tag ) ?? '';

			if( $tag !== '' )
			{
				$clean[] = $tag;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Turn a display name into a Cloudinary public_id segment.
	 *
	 * @param string $name
	 * @return string
	 */
	public function slugifyName( string $name ): string
	{
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug ) ?? '';
		$slug = trim( $slug, '-' );

		return $slug !== '' ? $slug : 'image';
	}

	/**
	 * Whether moving this public ID between folders will change the delivery URL.
	 *
	 * Fixed-folder public IDs include the folder path; renaming them changes the URL.
	 *
	 * @param string $publicId
	 * @return bool
	 */
	public function willMoveChangeUrl( string $publicId ): bool
	{
		$root = $this->getRootFolder();

		return $root !== '' && str_starts_with( $publicId, $root . '/' );
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
	 * List resources from Cloudinary via the Search API.
	 *
	 * Search returns total_count and can filter by folder and tag. Options:
	 * folder, tag, include_descendants, max_results, next_cursor.
	 *
	 * @param array $options Options for listing
	 * @return array{resources: array, next_cursor: ?string, total_count: int}
	 * @throws \Exception If listing fails
	 */
	public function listResources( array $options = [] ): array
	{
		try
		{
			$folder = $this->resolveLibraryFolder( $options['folder'] ?? null );
			$includeDescendants = (bool)( $options['include_descendants'] ?? false );
			$tag = isset( $options['tag'] ) ? $this->sanitizeSingleTag( (string) $options['tag'] ) : '';

			$search = $this->_cloudinary->searchApi()
				->expression( $this->buildSearchExpression( $folder, $tag, $includeDescendants ) )
				->withField( 'tags' )
				->withField( 'context' )
				->maxResults( (int)( $options['max_results'] ?? 30 ) )
				->sortBy( 'created_at', 'desc' );

			if( isset( $options['next_cursor'] ) && $options['next_cursor'] !== '' )
			{
				$search->nextCursor( $options['next_cursor'] );
			}

			$result = $search->execute();

			return [
				'resources' => array_map( [ $this, 'formatResult' ], $this->toArray( $result['resources'] ?? [] ) ),
				'next_cursor' => $result['next_cursor'] ?? null,
				'total_count' => (int)( $result['total_count'] ?? 0 )
			];
		}
		catch( \InvalidArgumentException $e )
		{
			throw $e;
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary list resources failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * List immediate subfolders of a library folder.
	 *
	 * @param string|null $parent Parent folder (relative or full). Defaults to the root.
	 * @return array<int, array{name: string, path: string}>
	 * @throws \Exception If the Folders API fails
	 */
	public function listFolders( ?string $parent = null ): array
	{
		try
		{
			$parent = $this->resolveLibraryFolder( $parent );
			$adminApi = $this->_cloudinary->adminApi();
			$result = $adminApi->subFolders( $parent );
			$folders = [];

			foreach( $this->toArray( $result['folders'] ?? [] ) as $folder )
			{
				$path = trim( (string)( $folder['path'] ?? '' ), '/' );
				$name = (string)( $folder['name'] ?? basename( $path ) );

				if( $path === '' || !$this->isSafeFolderPath( $path ) )
				{
					continue;
				}

				if( !$this->isUnderRoot( $path ) )
				{
					continue;
				}

				$folders[] = [
					'name' => $name,
					'path' => $path
				];
			}

			return $folders;
		}
		catch( \InvalidArgumentException $e )
		{
			throw $e;
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary list folders failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Create a subfolder under the library root.
	 *
	 * @param string $path Relative or full path under the root
	 * @return array{name: string, path: string}
	 * @throws \Exception If creation fails
	 */
	public function createFolder( string $path ): array
	{
		$resolved = $this->resolveLibraryFolder( $path );
		$root = $this->getRootFolder();

		if( $resolved === $root )
		{
			throw new \InvalidArgumentException( 'A subfolder name is required' );
		}

		try
		{
			$adminApi = $this->_cloudinary->adminApi();
			$result = $adminApi->createFolder( $resolved );

			return [
				'name' => (string)( $result['name'] ?? basename( $resolved ) ),
				'path' => (string)( $result['path'] ?? $resolved )
			];
		}
		catch( \InvalidArgumentException $e )
		{
			throw $e;
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary create folder failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * List Cloudinary tags currently used for images.
	 *
	 * @return array<int, string>
	 * @throws \Exception If listing fails
	 */
	public function listTags(): array
	{
		try
		{
			$adminApi = $this->_cloudinary->adminApi();
			$result = $adminApi->tags( [ 'max_results' => 100 ] );
			$tags = [];

			foreach( $this->toArray( $result['tags'] ?? [] ) as $tag )
			{
				$clean = $this->sanitizeSingleTag( (string) $tag );

				if( $clean !== '' )
				{
					$tags[] = $clean;
				}
			}

			return array_values( array_unique( $tags ) );
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary list tags failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Update display name and/or tags on an existing asset.
	 *
	 * Does not change public_id or the delivery URL.
	 *
	 * @param string $publicId
	 * @param array $options Keys: name, tags (string|array)
	 * @return array Formatted resource
	 * @throws \Exception If update fails
	 */
	public function updateResource( string $publicId, array $options = [] ): array
	{
		$update = [];

		if( array_key_exists( 'tags', $options ) )
		{
			$update['tags'] = $this->sanitizeTags( $options['tags'] );
		}

		if( array_key_exists( 'name', $options ) )
		{
			$name = trim( (string) $options['name'] );
			$update['context'] = [ 'name' => $name ];
			$update['display_name'] = $name;
		}

		try
		{
			$adminApi = $this->_cloudinary->adminApi();
			$result = $adminApi->update( $publicId, $update );

			return $this->formatResult( $result );
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary update failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Move an asset into another library folder.
	 *
	 * Dynamic-folder accounts update asset_folder (URL unchanged). Fixed-folder
	 * public IDs are renamed into the destination path (URL changes).
	 *
	 * @param string $publicId
	 * @param string $destinationFolder Relative or full path under the root
	 * @return array Formatted resource plus url_changed
	 * @throws \Exception If the move fails
	 */
	public function moveResource( string $publicId, string $destinationFolder ): array
	{
		$destination = $this->resolveLibraryFolder( $destinationFolder );
		$shouldRename = $this->willMoveChangeUrl( $publicId );

		try
		{
			$result = [];

			try
			{
				$result = $this->updateAssetFolder( $publicId, $destination );
				$movedFolder = trim( (string)( $result['asset_folder'] ?? $result['folder'] ?? '' ), '/' );

				// Dynamic-folder accounts (including those that prefix public_id)
				// treat asset_folder as the real location. Stop here so we do not
				// rewrite the delivery URL.
				if( $movedFolder === $destination )
				{
					return $this->formatResult( $result ) + [ 'url_changed' => false ];
				}

				if( !$shouldRename )
				{
					return $this->formatResult( $result ) + [ 'url_changed' => false ];
				}
			}
			catch( \Exception $e )
			{
				if( !$shouldRename )
				{
					throw $e;
				}
			}

			$newPublicId = $destination . '/' . basename( $publicId );

			if( $newPublicId === $publicId )
			{
				return $this->formatResult( [
					'public_id' => $publicId,
					'asset_folder' => $destination
				] ) + [ 'url_changed' => false ];
			}

			$uploadApi = $this->_cloudinary->uploadApi();
			$result = $uploadApi->rename( $publicId, $newPublicId, [ 'invalidate' => true ] );

			return $this->formatResult( $result ) + [ 'url_changed' => true ];
		}
		catch( \InvalidArgumentException $e )
		{
			throw $e;
		}
		catch( \Exception $e )
		{
			throw new \Exception( "Cloudinary move failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Set asset_folder on an existing asset.
	 *
	 * This is the move on dynamic-folder accounts. Fixed-folder accounts
	 * ignore the field; the caller then falls back to rename().
	 *
	 * @param string $publicId
	 * @param string $destination
	 * @return array|\ArrayAccess
	 * @throws \Exception
	 */
	private function updateAssetFolder( string $publicId, string $destination ): array|\ArrayAccess
	{
		$adminApi = $this->_cloudinary->adminApi();

		return $adminApi->update( $publicId, [ 'asset_folder' => $destination ] );
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
		$folder = $this->resolveLibraryFolder( $options['folder'] ?? null );

		$uploadOptions = [
			'folder' => $folder,
			'resource_type' => 'image',
			'unique_filename' => true
		];

		if( isset( $options['public_id'] ) && $options['public_id'] !== '' )
		{
			$uploadOptions['public_id'] = $this->slugifyName( (string) $options['public_id'] );
		}
		elseif( isset( $options['name'] ) && trim( (string) $options['name'] ) !== '' )
		{
			$uploadOptions['public_id'] = $this->slugifyName( (string) $options['name'] );
		}
		else
		{
			$uploadOptions['use_filename'] = true;
		}

		$displayName = trim( (string)( $options['name'] ?? '' ) );

		if( $displayName !== '' )
		{
			$uploadOptions['display_name'] = $displayName;
			$uploadOptions['context'] = [ 'name' => $displayName ];
		}

		if( isset( $options['transformation'] ) )
		{
			$uploadOptions['transformation'] = $options['transformation'];
		}

		if( isset( $options['tags'] ) )
		{
			$uploadOptions['tags'] = $this->sanitizeTags( $options['tags'] );
		}

		return $uploadOptions;
	}

	/**
	 * Format Cloudinary result into standardized array
	 *
	 * The Cloudinary SDK returns ApiResponse objects (which implement
	 * ArrayAccess) from upload calls, while nested resource entries from
	 * listing calls are plain arrays - accept both.
	 *
	 * @param array|\ArrayAccess $result Cloudinary upload/resource result
	 * @return array Formatted result
	 */
	private function formatResult( array|\ArrayAccess $result ): array
	{
		$publicId = (string)( $result['public_id'] ?? '' );
		$assetFolder = (string)( $result['asset_folder'] ?? $result['folder'] ?? '' );

		if( $assetFolder === '' && $publicId !== '' )
		{
			$dir = dirname( $publicId );
			$assetFolder = $dir !== '.' ? $dir : $this->getRootFolder();
		}

		return [
			'url' => $result['secure_url'] ?? $result['url'] ?? '',
			'public_id' => $publicId,
			'name' => $this->extractDisplayName( $result, $publicId ),
			'tags' => $this->extractTags( $result ),
			'asset_folder' => $assetFolder,
			'width' => $result['width'] ?? 0,
			'height' => $result['height'] ?? 0,
			'format' => $result['format'] ?? '',
			'bytes' => $result['bytes'] ?? 0,
			'resource_type' => $result['resource_type'] ?? 'image',
			'created_at' => $result['created_at'] ?? '',
			'url_change_on_move' => $this->willMoveChangeUrl( $publicId )
		];
	}

	/**
	 * Build a Search API expression for a folder (and optional tag).
	 *
	 * @param string $folder
	 * @param string $tag
	 * @param bool $includeDescendants
	 * @return string
	 */
	private function buildSearchExpression( string $folder, string $tag, bool $includeDescendants ): string
	{
		$quoted = $this->quoteSearchValue( $folder );
		$parts = [
			'folder:' . $quoted,
			'asset_folder:' . $quoted
		];

		if( $includeDescendants )
		{
			$descendants = $this->quoteSearchValue( $folder . '/*' );
			$parts[] = 'folder:' . $descendants;
			$parts[] = 'asset_folder:' . $descendants;
		}

		$expression = '(' . implode( ' OR ', $parts ) . ') AND resource_type:image';

		if( $tag !== '' )
		{
			$expression .= ' AND tags=' . $this->quoteSearchValue( $tag );
		}

		return $expression;
	}

	/**
	 * Quote a value for a Cloudinary search expression.
	 *
	 * @param string $value
	 * @return string
	 */
	private function quoteSearchValue( string $value ): string
	{
		return '"' . str_replace( '"', '', $value ) . '"';
	}

	/**
	 * Whether a folder path uses only safe characters and has no traversal.
	 *
	 * @param string $path
	 * @return bool
	 */
	private function isSafeFolderPath( string $path ): bool
	{
		return $path !== ''
			&& !str_contains( $path, '..' )
			&& (bool) preg_match( '#^[A-Za-z0-9_\-/.]+$#', $path );
	}

	/**
	 * Whether a path is the library root or a descendant.
	 *
	 * @param string $path
	 * @return bool
	 */
	private function isUnderRoot( string $path ): bool
	{
		$root = $this->getRootFolder();

		if( $root === '' )
		{
			return true;
		}

		return $path === $root || str_starts_with( $path, $root . '/' );
	}

	/**
	 * Sanitize a single tag value. Empty string if nothing usable remains.
	 *
	 * @param string $tag
	 * @return string
	 */
	private function sanitizeSingleTag( string $tag ): string
	{
		$tags = $this->sanitizeTags( $tag );

		return $tags[0] ?? '';
	}

	/**
	 * Display name from Cloudinary fields, falling back to the public_id basename.
	 *
	 * @param array|\ArrayAccess $result
	 * @param string $publicId
	 * @return string
	 */
	private function extractDisplayName( array|\ArrayAccess $result, string $publicId ): string
	{
		if( !empty( $result['display_name'] ) )
		{
			return (string) $result['display_name'];
		}

		$context = $result['context'] ?? null;

		if( is_array( $context ) || $context instanceof \ArrayAccess )
		{
			$custom = $context['custom'] ?? $context;

			if( is_array( $custom ) || $custom instanceof \ArrayAccess )
			{
				if( !empty( $custom['name'] ) )
				{
					return (string) $custom['name'];
				}

				if( !empty( $custom['caption'] ) )
				{
					return (string) $custom['caption'];
				}
			}
		}

		return $publicId !== '' ? basename( $publicId ) : '';
	}

	/**
	 * Tags from a Cloudinary resource payload.
	 *
	 * @param array|\ArrayAccess $result
	 * @return array<int, string>
	 */
	private function extractTags( array|\ArrayAccess $result ): array
	{
		$tags = $result['tags'] ?? [];

		if( !is_array( $tags ) )
		{
			return [];
		}

		return $this->sanitizeTags( $tags );
	}

	/**
	 * Normalize Cloudinary list payloads to a plain array.
	 *
	 * @param mixed $value
	 * @return array
	 */
	private function toArray( mixed $value ): array
	{
		if( is_array( $value ) )
		{
			return $value;
		}

		if( $value instanceof \Traversable )
		{
			return iterator_to_array( $value );
		}

		return [];
	}
}
