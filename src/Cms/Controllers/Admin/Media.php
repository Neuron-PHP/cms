<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Media upload controller.
 *
 * Handles image uploads and media library management for the admin interface.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Media extends Content
{
	private CloudinaryUploader $_uploader;
	private MediaValidator $_validator;

	/**
	 * Constructor
	 *
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param CloudinaryUploader $uploader
	 * @param MediaValidator $validator
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		CloudinaryUploader $uploader,
		MediaValidator $validator
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_uploader = $uploader;
		$this->_validator = $validator;
	}

	/**
	 * Display media library
	 *
	 * Shows uploaded images from Cloudinary in a grid view with folder
	 * navigation, tag filters, and cursor pagination.
	 *
	 * @param Request $request
	 * @return string Rendered view
	 * @throws \Exception
	 */
	#[Get('/media', name: 'admin_media')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$sessionManager = $this->getSessionManager();

		try
		{
			$library = $this->loadLibrary( $request );

			return $this->view()
				->title( 'Media Library' )
				->description( 'Manage uploaded images' )
				->withCurrentUser()
				->withCsrfToken()
				->with( $this->libraryViewData( $library, $sessionManager ) )
				->render( 'index', 'admin' );
		}
		catch( \Exception $e )
		{
			Log::error( 'Error fetching media resources: ' . $e->getMessage(), [
				'exception' => $e,
				'user_id' => user_id()
			] );

			return $this->view()
				->title( 'Media Library' )
				->description( 'Manage uploaded images' )
				->withCurrentUser()
				->withCsrfToken()
				->with( $this->libraryViewData( $this->emptyLibrary(), $sessionManager, 'Failed to load media library. Please try again.' ) )
				->render( 'index', 'admin' );
		}
	}

	/**
	 * JSON list of library resources, folders, and tags.
	 *
	 * Used by the media picker so it does not scrape the HTML library page.
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Get('/media/list', name: 'admin_media_list')]
	public function listMedia( Request $request ): string
	{
		try
		{
			$library = $this->loadLibrary( $request );

			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					FlashMessageType::SUCCESS->value => true,
					'resources' => $library['resources'],
					'next_cursor' => $library['nextCursor'],
					'total_count' => $library['totalCount'],
					'tags' => $library['tags'],
					'folders' => $library['folders'],
					'current_folder' => $library['currentFolder'],
					'root_folder' => $library['rootFolder'],
					'current_tag' => $library['currentTag']
				]
			);
		}
		catch( \InvalidArgumentException $e )
		{
			return $this->renderJson(
				HttpResponseStatus::BAD_REQUEST,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => $e->getMessage()
				]
			);
		}
		catch( \Exception $e )
		{
			Log::error( 'Error listing media resources: ' . $e->getMessage(), [
				'exception' => $e,
				'user_id' => user_id()
			] );

			return $this->renderJson(
				HttpResponseStatus::INTERNAL_SERVER_ERROR,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => 'Failed to load media library. Please try again.'
				]
			);
		}
	}

	/**
	 * Issue a fresh CSRF token for AJAX media actions.
	 *
	 * CSRF tokens are single-use ( consumed on validation ), so AJAX flows that
	 * stay on the page — the media picker modal and the library's upload / delete
	 * buttons — must request a fresh token before each request rather than reuse
	 * the one rendered into the page <meta> tag.
	 *
	 * @param Request $request
	 * @return string JSON response { token }
	 */
	#[Get('/csrf-token', name: 'admin_csrf_token')]
	public function csrfToken( Request $request ): string
	{
		$csrf = new CsrfToken( $this->getSessionManager() );

		return $this->renderJson( HttpResponseStatus::OK, [ 'token' => $csrf->getToken() ] );
	}

	/**
	 * Upload image for Editor.js
	 *
	 * Handles POST /admin/upload/image
	 * Returns JSON in Editor.js format
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Post('/upload/image', name: 'admin_upload_image', filters: ['csrf'])]
	public function uploadImage( Request $request ): string
	{
		try
		{
			// Check if file was uploaded
			if( !isset( $_FILES['image'] ) )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						FlashMessageType::SUCCESS->value => 0,
						'message' => 'No file was uploaded'
					]
				);
			}

			$file = $_FILES['image'];

			if( is_array( $file['name'] ?? null ) )
			{
				$file = $this->normalizeUploadedFiles( $file )[0] ?? null;
			}

			if( !$file )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						FlashMessageType::SUCCESS->value => 0,
						'message' => 'No file was uploaded'
					]
				);
			}

			// Validate file
			if( !$this->_validator->validate( $file ) )
			{
				Log::warning( 'Image upload validation failed', [
					'user_id' => user_id(),
					'filename' => $file['name'] ?? 'unknown',
					FlashMessageType::ERROR->value => $this->_validator->getFirstError()
				] );

				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						FlashMessageType::SUCCESS->value => 0,
						'message' => $this->_validator->getFirstError()
					]
				);
			}

			// Upload to Cloudinary
			$result = $this->_uploader->upload( $file['tmp_name'], $this->uploadOptionsFromRequest( $request, $file ) );

			Log::info( 'Image uploaded successfully', [
				'user_id' => user_id(),
				'filename' => $file['name'],
				'public_id' => $result['public_id'],
				'url' => $result['url']
			] );

			// Return success response in Editor.js format
			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					FlashMessageType::SUCCESS->value => 1,
					'file' => [
						'url' => $result['url'],
						'width' => $result['width'],
						'height' => $result['height']
					]
				]
			);
		}
		catch( \Exception $e )
		{
			// Safely retrieve filename with explicit isset check to prevent undefined index
			$filename = isset( $_FILES['image']['name'] ) ? $_FILES['image']['name'] : 'unknown';

			if( is_array( $filename ) )
			{
				$filename = $filename[0] ?? 'unknown';
			}

			Log::error( 'Image upload failed', [
				'user_id' => user_id(),
				'filename' => $filename,
				'exception' => $e,
				'message' => $e->getMessage()
			] );

			return $this->renderJson(
				HttpResponseStatus::INTERNAL_SERVER_ERROR,
				[
					FlashMessageType::SUCCESS->value => 0,
					'message' => 'Upload failed. Please try again.'
				]
			);
		}
	}

	/**
	 * Upload an image to the media library
	 *
	 * Handles POST /admin/media/upload
	 * Generic image upload used by the media library page and the media
	 * picker modal. Returns JSON: { success, data } or { success, error }.
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Post('/media/upload', name: 'admin_media_upload', filters: ['csrf'])]
	public function uploadMedia( Request $request ): string
	{
		return $this->handleImageUpload( 'Media library image', $request );
	}

	/**
	 * Upload a post/event featured image
	 *
	 * Handles POST /admin/upload/featured-image
	 * Used by the post and event editors to upload the content's featured
	 * (hero/thumbnail) image. Returns JSON: { success, data } or { success, error }.
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Post('/upload/featured-image', name: 'admin_upload_featured_image', filters: ['csrf'])]
	public function uploadFeaturedImage( Request $request ): string
	{
		return $this->handleImageUpload( 'Featured image', $request );
	}

	/**
	 * Create a subfolder under the library root.
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Post('/media/folders', name: 'admin_media_folders', filters: ['csrf'])]
	public function createFolder( Request $request ): string
	{
		$name = trim( (string)( $request->post( 'name' ) ?? $request->post( 'folder' ) ?? '' ) );

		if( $name === '' )
		{
			return $this->jsonError( HttpResponseStatus::BAD_REQUEST, 'A folder name is required' );
		}

		try
		{
			$folder = $this->_uploader->createFolder( $name );

			Log::info( 'Media folder created', [
				'user_id' => user_id(),
				'path' => $folder['path']
			] );

			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					FlashMessageType::SUCCESS->value => true,
					'data' => $folder
				]
			);
		}
		catch( \InvalidArgumentException $e )
		{
			return $this->jsonError( HttpResponseStatus::BAD_REQUEST, $e->getMessage() );
		}
		catch( \Exception $e )
		{
			Log::error( 'Media folder creation failed', [
				'user_id' => user_id(),
				'exception' => $e,
				'message' => $e->getMessage()
			] );

			return $this->jsonError( HttpResponseStatus::INTERNAL_SERVER_ERROR, 'Folder could not be created' );
		}
	}

	/**
	 * Update display name and tags on an existing asset.
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Post('/media/update', name: 'admin_media_update', filters: ['csrf'])]
	public function updateMedia( Request $request ): string
	{
		$publicId = $this->validatedPublicId( $request );

		if( $publicId === null )
		{
			return $this->jsonError( HttpResponseStatus::BAD_REQUEST, $this->publicIdError( $request ) );
		}

		if( !$this->isLibraryPublicId( $request, $publicId ) )
		{
			return $this->jsonError( HttpResponseStatus::FORBIDDEN, 'Image cannot be updated' );
		}

		try
		{
			$result = $this->_uploader->updateResource( $publicId, [
				'name' => (string)( $request->post( 'name' ) ?? '' ),
				'tags' => (string)( $request->post( 'tags' ) ?? '' )
			] );

			Log::info( 'Media image updated', [
				'user_id' => user_id(),
				'public_id' => $publicId
			] );

			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					FlashMessageType::SUCCESS->value => true,
					'data' => $result
				]
			);
		}
		catch( \Exception $e )
		{
			Log::error( 'Media image update failed', [
				'user_id' => user_id(),
				'public_id' => $publicId,
				'exception' => $e,
				'message' => $e->getMessage()
			] );

			return $this->jsonError( HttpResponseStatus::INTERNAL_SERVER_ERROR, 'Update failed. Please try again.' );
		}
	}

	/**
	 * Move an asset into another library folder.
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Post('/media/move', name: 'admin_media_move', filters: ['csrf'])]
	public function moveMedia( Request $request ): string
	{
		$publicId = $this->validatedPublicId( $request );

		if( $publicId === null )
		{
			return $this->jsonError( HttpResponseStatus::BAD_REQUEST, $this->publicIdError( $request ) );
		}

		if( !$this->isLibraryPublicId( $request, $publicId ) )
		{
			return $this->jsonError( HttpResponseStatus::FORBIDDEN, 'Image cannot be moved' );
		}

		$folder = trim( (string)( $request->post( 'folder' ) ?? '' ) );

		if( $folder === '' )
		{
			return $this->jsonError( HttpResponseStatus::BAD_REQUEST, 'A destination folder is required' );
		}

		try
		{
			$result = $this->_uploader->moveResource( $publicId, $folder );

			Log::info( 'Media image moved', [
				'user_id' => user_id(),
				'public_id' => $publicId,
				'folder' => $result['asset_folder'] ?? $folder,
				'url_changed' => $result['url_changed'] ?? false
			] );

			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					FlashMessageType::SUCCESS->value => true,
					'data' => $result
				]
			);
		}
		catch( \InvalidArgumentException $e )
		{
			return $this->jsonError( HttpResponseStatus::BAD_REQUEST, $e->getMessage() );
		}
		catch( \Exception $e )
		{
			Log::error( 'Media image move failed', [
				'user_id' => user_id(),
				'public_id' => $publicId,
				'exception' => $e,
				'message' => $e->getMessage()
			] );

			return $this->jsonError( HttpResponseStatus::INTERNAL_SERVER_ERROR, 'Move failed. Please try again.' );
		}
	}

	/**
	 * Delete an image from the media library
	 *
	 * Handles POST /admin/media/delete
	 * Removes the asset from Cloudinary by its public ID. The public ID is
	 * validated against a safe character set and constrained to the configured
	 * media folder so callers cannot delete assets outside the library.
	 * Returns JSON: { success: true } or { success: false, error }.
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	#[Post('/media/delete', name: 'admin_media_delete', filters: ['csrf'])]
	public function deleteMedia( Request $request ): string
	{
		$publicId = $this->validatedPublicId( $request );

		if( $publicId === null )
		{
			return $this->jsonError( HttpResponseStatus::BAD_REQUEST, $this->publicIdError( $request ) );
		}

		if( !$this->isLibraryPublicId( $request, $publicId ) )
		{
			Log::warning( 'Media deletion outside configured folder rejected', [
				'public_id' => substr( $publicId, 0, 100 ),
				'folder' => $this->_uploader->getRootFolder(),
				'user_id' => user_id(),
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
			] );

			return $this->jsonError( HttpResponseStatus::FORBIDDEN, 'Image cannot be deleted' );
		}

		try
		{
			$deleted = $this->_uploader->delete( $publicId );

			if( !$deleted )
			{
				return $this->jsonError( HttpResponseStatus::INTERNAL_SERVER_ERROR, 'Image could not be deleted' );
			}

			Log::info( 'Media image deleted successfully', [
				'user_id' => user_id(),
				'public_id' => $publicId
			] );

			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					FlashMessageType::SUCCESS->value => true
				]
			);
		}
		catch( \Exception $e )
		{
			Log::error( 'Media image deletion failed', [
				'user_id' => user_id(),
				'public_id' => $publicId,
				'exception' => $e,
				'message' => $e->getMessage()
			] );

			return $this->jsonError( HttpResponseStatus::INTERNAL_SERVER_ERROR, 'Delete failed. Please try again.' );
		}
	}

	/**
	 * Shared image upload handler
	 *
	 * Validates the uploaded file, pushes it to Cloudinary and returns a
	 * standard JSON envelope. Shared by every "upload an image" endpoint so
	 * the validation, logging and response shape stay consistent.
	 *
	 * @param string $logLabel Human-readable label used in log messages
	 * @param Request|null $request Request used for optional name, tags, and folder
	 * @return string JSON response
	 */
	private function handleImageUpload( string $logLabel, ?Request $request = null ): string
	{
		try
		{
			$files = $this->collectUploadedFiles();

			if( $files === [] )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						FlashMessageType::SUCCESS->value => false,
						FlashMessageType::ERROR->value => 'No file was uploaded'
					]
				);
			}

			$results = [];

			foreach( $files as $file )
			{
				if( !$this->_validator->validate( $file ) )
				{
					Log::warning( $logLabel . ' upload validation failed', [
						'user_id' => user_id(),
						'filename' => $file['name'] ?? 'unknown',
						FlashMessageType::ERROR->value => $this->_validator->getFirstError()
					] );

					return $this->renderJson(
						HttpResponseStatus::BAD_REQUEST,
						[
							FlashMessageType::SUCCESS->value => false,
							FlashMessageType::ERROR->value => $this->_validator->getFirstError()
						]
					);
				}

				$options = $request ? $this->uploadOptionsFromRequest( $request, $file ) : [];
				$result = $this->_uploader->upload( $file['tmp_name'], $options );
				$results[] = $result;

				Log::info( $logLabel . ' uploaded successfully', [
					'user_id' => user_id(),
					'filename' => $file['name'],
					'public_id' => $result['public_id'],
					'url' => $result['url']
				] );
			}

			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					FlashMessageType::SUCCESS->value => true,
					'data' => $results[0],
					'items' => $results
				]
			);
		}
		catch( \InvalidArgumentException $e )
		{
			return $this->renderJson(
				HttpResponseStatus::BAD_REQUEST,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => $e->getMessage()
				]
			);
		}
		catch( \Exception $e )
		{
			// Safely retrieve filename with explicit isset check to prevent undefined index
			$filename = isset( $_FILES['image']['name'] ) ? $_FILES['image']['name'] : 'unknown';

			if( is_array( $filename ) )
			{
				$filename = $filename[0] ?? 'unknown';
			}

			Log::error( $logLabel . ' upload failed', [
				'user_id' => user_id(),
				'filename' => $filename,
				'exception' => $e,
				'message' => $e->getMessage()
			] );

			return $this->renderJson(
				HttpResponseStatus::INTERNAL_SERVER_ERROR,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => 'Upload failed. Please try again.'
				]
			);
		}
	}

	/**
	 * Load folder, tag, and pagination state from Cloudinary.
	 *
	 * @param Request $request
	 * @return array
	 * @throws \Exception
	 */
	private function loadLibrary( Request $request ): array
	{
		$rootFolder = $this->_uploader->getRootFolder();
		$currentFolder = $this->validatedFolder( $request );
		$currentTag = $this->validatedTag( $request );
		$validatedCursor = $this->validatedCursor( $request );

		$options = [
			'max_results' => 30,
			'folder' => $currentFolder,
			'include_descendants' => $currentFolder === $rootFolder && $currentTag === ''
		];

		if( $currentTag !== '' )
		{
			$options['tag'] = $currentTag;
			$options['include_descendants'] = true;
		}

		if( $validatedCursor !== null )
		{
			$options['next_cursor'] = $validatedCursor;
		}

		$result = $this->_uploader->listResources( $options );
		$folders = [];
		$tags = [];

		try
		{
			$folders = $this->_uploader->listFolders( $currentFolder );
		}
		catch( \Exception $e )
		{
			Log::warning( 'Unable to list media folders: ' . $e->getMessage() );
		}

		try
		{
			$tags = $this->_uploader->listTags();
		}
		catch( \Exception $e )
		{
			Log::warning( 'Unable to list media tags: ' . $e->getMessage() );
		}

		return [
			'resources' => $result['resources'],
			'nextCursor' => $result['next_cursor'],
			'totalCount' => $result['total_count'],
			'folders' => $folders,
			'tags' => $tags,
			'currentFolder' => $currentFolder,
			'rootFolder' => $rootFolder,
			'currentTag' => $currentTag
		];
	}

	/**
	 * View data for the media library page.
	 *
	 * @param array $library
	 * @param SessionManager $sessionManager
	 * @param string|null $error
	 * @return array
	 */
	private function libraryViewData( array $library, SessionManager $sessionManager, ?string $error = null ): array
	{
		return [
			'resources' => $library['resources'],
			'nextCursor' => $library['nextCursor'],
			'totalCount' => $library['totalCount'],
			'folders' => $library['folders'],
			'tags' => $library['tags'],
			'currentFolder' => $library['currentFolder'],
			'rootFolder' => $library['rootFolder'],
			'currentTag' => $library['currentTag'],
			FlashMessageType::SUCCESS->viewKey() => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
			FlashMessageType::ERROR->viewKey() => $error ?? $sessionManager->getFlash( FlashMessageType::ERROR->value )
		];
	}

	/**
	 * Empty library payload used when listing fails.
	 *
	 * @return array
	 */
	private function emptyLibrary(): array
	{
		$root = $this->_uploader->getRootFolder();

		return [
			'resources' => [],
			'nextCursor' => null,
			'totalCount' => 0,
			'folders' => [],
			'tags' => [],
			'currentFolder' => $root,
			'rootFolder' => $root,
			'currentTag' => ''
		];
	}

	/**
	 * Validate and resolve the folder query parameter.
	 *
	 * @param Request $request
	 * @return string
	 */
	private function validatedFolder( Request $request ): string
	{
		$raw = trim( (string)( $request->get( 'folder' ) ?? '' ) );

		if( $raw === '' )
		{
			return $this->_uploader->getRootFolder();
		}

		return $this->_uploader->resolveLibraryFolder( $raw );
	}

	/**
	 * Validate the tag query parameter. Invalid tags are ignored.
	 *
	 * @param Request $request
	 * @return string
	 */
	private function validatedTag( Request $request ): string
	{
		$raw = trim( (string)( $request->get( 'tag' ) ?? '' ) );

		if( $raw === '' )
		{
			return '';
		}

		$tags = $this->_uploader->sanitizeTags( $raw );

		return $tags[0] ?? '';
	}

	/**
	 * Validate the pagination cursor. Invalid cursors are ignored.
	 *
	 * @param Request $request
	 * @return string|null
	 */
	private function validatedCursor( Request $request ): ?string
	{
		$rawCursor = $request->get( 'cursor' );

		if( empty( $rawCursor ) )
		{
			return null;
		}

		// Cloudinary cursors are typically base64-like strings with alphanumeric, _, -, and = characters
		if( preg_match( '/^[a-zA-Z0-9_\-=]+$/', $rawCursor ) )
		{
			return $rawCursor;
		}

		Log::warning( 'Invalid pagination cursor rejected', [
			'cursor' => substr( (string) $rawCursor, 0, 50 ),
			'user_id' => user_id(),
			'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
		] );

		return null;
	}

	/**
	 * Validate a posted public_id. Returns null when missing or unsafe.
	 *
	 * @param Request $request
	 * @return string|null
	 */
	private function validatedPublicId( Request $request ): ?string
	{
		$publicId = trim( (string)( $request->post( 'public_id' ) ?? '' ) );

		if( $publicId === '' )
		{
			return null;
		}

		// Validate the public ID. Cloudinary public IDs are made up of folder
		// segments and a name: letters, numbers, _, -, /, and . Fail closed on
		// anything else to avoid passing crafted identifiers to the API.
		if( !preg_match( '#^[A-Za-z0-9_\-/.]+$#', $publicId ) || str_contains( $publicId, '..' ) )
		{
			Log::warning( 'Invalid media public ID rejected', [
				'public_id' => substr( $publicId, 0, 100 ),
				'user_id' => user_id(),
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
			] );

			return null;
		}

		return $publicId;
	}

	/**
	 * Error message for a missing or invalid public_id.
	 *
	 * @param Request $request
	 * @return string
	 */
	private function publicIdError( Request $request ): string
	{
		$publicId = trim( (string)( $request->post( 'public_id' ) ?? '' ) );

		return $publicId === '' ? 'No image was specified' : 'Invalid image identifier';
	}

	/**
	 * Whether the posted asset is inside the configured library root.
	 *
	 * @param Request $request
	 * @param string $publicId
	 * @return bool
	 */
	private function isLibraryPublicId( Request $request, string $publicId ): bool
	{
		$assetFolder = trim( (string)( $request->post( 'asset_folder' ) ?? '' ) );

		return $this->_uploader->isLibraryAsset( $publicId, $assetFolder !== '' ? $assetFolder : null );
	}

	/**
	 * Upload options from the current request and source filename.
	 *
	 * @param Request $request
	 * @param array $file
	 * @return array
	 */
	private function uploadOptionsFromRequest( Request $request, array $file ): array
	{
		$options = [];
		$folder = trim( (string)( $request->post( 'folder' ) ?? '' ) );

		if( $folder !== '' )
		{
			$options['folder'] = $folder;
		}

		$name = trim( (string)( $request->post( 'name' ) ?? '' ) );

		if( $name !== '' )
		{
			$options['name'] = $name;
		}
		elseif( !empty( $file['name'] ) )
		{
			$options['name'] = pathinfo( (string) $file['name'], PATHINFO_FILENAME );
		}

		$tags = trim( (string)( $request->post( 'tags' ) ?? '' ) );

		if( $tags !== '' )
		{
			$options['tags'] = $tags;
		}

		return $options;
	}

	/**
	 * Collect one or more uploaded image files from $_FILES.
	 *
	 * @return array<int, array>
	 */
	private function collectUploadedFiles(): array
	{
		if( !isset( $_FILES['image'] ) )
		{
			return [];
		}

		return $this->normalizeUploadedFiles( $_FILES['image'] );
	}

	/**
	 * Normalize a $_FILES entry that may be a single file or a multiple upload.
	 *
	 * @param array $file
	 * @return array<int, array>
	 */
	private function normalizeUploadedFiles( array $file ): array
	{
		if( !is_array( $file['name'] ?? null ) )
		{
			return [ $file ];
		}

		$files = [];
		$count = count( $file['name'] );

		for( $i = 0; $i < $count; $i++ )
		{
			if( ( $file['error'][$i] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE )
			{
				continue;
			}

			$files[] = [
				'name' => $file['name'][$i] ?? '',
				'type' => $file['type'][$i] ?? '',
				'tmp_name' => $file['tmp_name'][$i] ?? '',
				'error' => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
				'size' => $file['size'][$i] ?? 0
			];
		}

		return $files;
	}

	/**
	 * JSON error envelope used by library mutation endpoints.
	 *
	 * @param HttpResponseStatus $status
	 * @param string $message
	 * @return string
	 */
	private function jsonError( HttpResponseStatus $status, string $message ): string
	{
		return $this->renderJson(
			$status,
			[
				FlashMessageType::SUCCESS->value => false,
				FlashMessageType::ERROR->value => $message
			]
		);
	}
}
