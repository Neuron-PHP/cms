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
	 * Shows all uploaded images from Cloudinary in a grid view.
	 * Supports pagination and search/filter.
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
			// Get pagination cursor from query string
			$rawCursor = $request->get( 'cursor' );

			// Validate cursor - must be alphanumeric plus underscore/hyphen/equals (Cloudinary format)
			// Fail closed by not passing invalid cursors to the API
			$validatedCursor = null;
			if( !empty( $rawCursor ) )
			{
				// Cloudinary cursors are typically base64-like strings with alphanumeric, _, -, and = characters
				if( preg_match( '/^[a-zA-Z0-9_\-=]+$/', $rawCursor ) )
				{
					$validatedCursor = $rawCursor;
				}
				else
				{
					// Log invalid cursor attempt for security monitoring
					Log::warning( 'Invalid pagination cursor rejected', [
						'cursor' => substr( $rawCursor, 0, 50 ), // Limit logged data
						'user_id' => user_id(),
						'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
					] );
				}
			}

			// Build options array - only include next_cursor if validated
			$options = ['max_results' => 30];
			if( $validatedCursor !== null )
			{
				$options['next_cursor'] = $validatedCursor;
			}

			// List resources from Cloudinary with validated cursor
			$result = $this->_uploader->listResources( $options );

			return $this->view()
				->title( 'Media Library' )
				->description( 'Manage uploaded images' )
				->withCurrentUser()
				->withCsrfToken()
				->with([
					'resources' => $result['resources'],
					'nextCursor' => $result['next_cursor'],
					'totalCount' => $result['total_count'],
					FlashMessageType::SUCCESS->viewKey() => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
					FlashMessageType::ERROR->viewKey() => $sessionManager->getFlash( FlashMessageType::ERROR->value )
				])
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
				->with([
					'resources' => [],
					'nextCursor' => null,
					'totalCount' => 0,
					FlashMessageType::SUCCESS->viewKey() => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
					FlashMessageType::ERROR->viewKey() => 'Failed to load media library. Please try again.'
				])
				->render( 'index', 'admin' );
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
			$result = $this->_uploader->upload( $file['tmp_name'] );

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
		return $this->handleImageUpload( 'Media library image' );
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
		return $this->handleImageUpload( 'Featured image' );
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
		$publicId = trim( (string)( $request->post( 'public_id' ) ?? '' ) );

		if( $publicId === '' )
		{
			return $this->renderJson(
				HttpResponseStatus::BAD_REQUEST,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => 'No image was specified'
				]
			);
		}

		// Validate the public ID. Cloudinary public IDs are made up of folder
		// segments and a name: letters, numbers, _, -, /, and . Fail closed on
		// anything else to avoid passing crafted identifiers to the API.
		if( !preg_match( '#^[A-Za-z0-9_\-/.]+$#', $publicId ) || str_contains( $publicId, '..' ) )
		{
			Log::warning( 'Invalid media public ID rejected for deletion', [
				'public_id' => substr( $publicId, 0, 100 ),
				'user_id' => user_id(),
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
			] );

			return $this->renderJson(
				HttpResponseStatus::BAD_REQUEST,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => 'Invalid image identifier'
				]
			);
		}

		// Constrain deletion to the configured media folder so only library
		// assets can be removed through this endpoint.
		$folder = (string)( $this->_settings->get( 'cloudinary', 'folder' ) ?? 'neuron-cms/images' );
		$folder = trim( $folder, '/' );

		if( $folder !== '' && !str_starts_with( $publicId, $folder . '/' ) )
		{
			Log::warning( 'Media deletion outside configured folder rejected', [
				'public_id' => substr( $publicId, 0, 100 ),
				'folder' => $folder,
				'user_id' => user_id(),
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
			] );

			return $this->renderJson(
				HttpResponseStatus::FORBIDDEN,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => 'Image cannot be deleted'
				]
			);
		}

		try
		{
			$deleted = $this->_uploader->delete( $publicId );

			if( !$deleted )
			{
				return $this->renderJson(
					HttpResponseStatus::INTERNAL_SERVER_ERROR,
					[
						FlashMessageType::SUCCESS->value => false,
						FlashMessageType::ERROR->value => 'Image could not be deleted'
					]
				);
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

			return $this->renderJson(
				HttpResponseStatus::INTERNAL_SERVER_ERROR,
				[
					FlashMessageType::SUCCESS->value => false,
					FlashMessageType::ERROR->value => 'Delete failed. Please try again.'
				]
			);
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
	 * @return string JSON response
	 */
	private function handleImageUpload( string $logLabel ): string
	{
		try
		{
			// Check if file was uploaded
			if( !isset( $_FILES['image'] ) )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						FlashMessageType::SUCCESS->value => false,
						FlashMessageType::ERROR->value => 'No file was uploaded'
					]
				);
			}

			$file = $_FILES['image'];

			// Validate file
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

			// Upload to Cloudinary
			$result = $this->_uploader->upload( $file['tmp_name'] );

			Log::info( $logLabel . ' uploaded successfully', [
				'user_id' => user_id(),
				'filename' => $file['name'],
				'public_id' => $result['public_id'],
				'url' => $result['url']
			] );

			// Return success response
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
			// Safely retrieve filename with explicit isset check to prevent undefined index
			$filename = isset( $_FILES['image']['name'] ) ? $_FILES['image']['name'] : 'unknown';

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
}
