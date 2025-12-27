<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Media upload controller.
 *
 * Handles image uploads and media library management for the admin interface.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Media extends Content
{
	private CloudinaryUploader $_uploader;
	private MediaValidator $_validator;

	/**
	 * Constructor
	 *
	 * @param Application|null $app
	 * @param CloudinaryUploader|null $uploader
	 * @param MediaValidator|null $validator
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?CloudinaryUploader $uploader = null,
		?MediaValidator $validator = null
	)
	{
		parent::__construct( $app );

		// Get settings once if we need to create any dependencies
		$settings = null;
		if( $uploader === null || $validator === null )
		{
			$settings = Registry::getInstance()->get( 'Settings' );

			if( !$settings instanceof SettingManager )
			{
				throw new \Exception( 'Settings not found in Registry' );
			}
		}

		// Create uploader if not provided
		if( $uploader === null )
		{
			$uploader = new CloudinaryUploader( $settings );
		}

		// Create validator if not provided
		if( $validator === null )
		{
			$validator = new MediaValidator( $settings );
		}

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
	public function index( Request $request ): string
	{
		// Generate CSRF token
		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

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
					FlashMessageType::SUCCESS->value => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
					FlashMessageType::ERROR->value => $sessionManager->getFlash( FlashMessageType::ERROR->value )
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
					FlashMessageType::SUCCESS->value => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
					FlashMessageType::ERROR->value => 'Failed to load media library. Please try again.'
				])
				->render( 'index', 'admin' );
		}
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
	 * Upload featured image
	 *
	 * Handles POST /admin/upload/featured-image
	 * Returns JSON with upload result
	 *
	 * @param Request $request
	 * @return string JSON response
	 */
	public function uploadFeaturedImage( Request $request ): string
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
				Log::warning( 'Featured image upload validation failed', [
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

			Log::info( 'Featured image uploaded successfully', [
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

			Log::error( 'Featured image upload failed', [
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
