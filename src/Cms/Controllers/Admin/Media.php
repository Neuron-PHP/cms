<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Media upload controller.
 *
 * Handles image uploads for the admin interface.
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
	 * @param \Neuron\Mvc\Application|null $app
	 */
	public function __construct( ?\Neuron\Mvc\Application $app = null )
	{
		parent::__construct( $app );

		$settings = Registry::getInstance()->get( 'Settings' );

		if( !$settings instanceof SettingManager )
		{
			throw new \Exception( 'Settings not found in Registry' );
		}

		$this->_uploader = new CloudinaryUploader( $settings );
		$this->_validator = new MediaValidator( $settings );
	}

	/**
	 * Display media library
	 *
	 * Shows all uploaded images from Cloudinary in a grid view.
	 * Supports pagination and search/filter.
	 *
	 * @return string Rendered view
	 */
	public function index(): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			// Get pagination cursor from query string
			$nextCursor = isset($_GET['cursor']) && is_string($_GET['cursor']) 
				? filter_var($_GET['cursor'], FILTER_SANITIZE_STRING) 
				: null;

			// List resources from Cloudinary
			$result = $this->_uploader->listResources( [
				'next_cursor' => $nextCursor,
				'max_results' => 30
			] );

			$viewData = [
				'Title' => 'Media Library | ' . $this->getName(),
				'Description' => 'Manage uploaded images',
				'User' => $user,
				'resources' => $result['resources'],
				'nextCursor' => $result['next_cursor'],
				'totalCount' => $result['total_count']
			];

			return $this->renderHtml(
				HttpResponseStatus::OK,
				$viewData,
				'index',
				'admin'
			);
		}
		catch( \Exception $e )
		{
			Log::error( 'Error fetching media resources: ' . $e->getMessage() );

			$viewData = [
				'Title' => 'Media Library | ' . $this->getName(),
				'Description' => 'Manage uploaded images',
				'User' => $user,
				'resources' => [],
				'nextCursor' => null,
				'totalCount' => 0,
				'error' => $e->getMessage()
			];

			return $this->renderHtml(
				HttpResponseStatus::OK,
				$viewData,
				'index',
				'admin'
			);
		}
	}

	/**
	 * Upload image for Editor.js
	 *
	 * Handles POST /admin/upload/image
	 * Returns JSON in Editor.js format
	 *
	 * @return void
	 */
	public function uploadImage(): void
	{
		// Set JSON response header
		header( 'Content-Type: application/json' );

		try
		{
			// Check if file was uploaded
			if( !isset( $_FILES['image'] ) )
			{
				$this->returnEditorJsError( 'No file was uploaded' );
				return;
			}

			$file = $_FILES['image'];

			// Validate file
			if( !$this->_validator->validate( $file ) )
			{
				$this->returnEditorJsError( $this->_validator->getFirstError() );
				return;
			}

			// Upload to Cloudinary
			$result = $this->_uploader->upload( $file['tmp_name'] );

			// Return success response in Editor.js format
			$this->returnEditorJsSuccess( $result );
		}
		catch( \Exception $e )
		{
			$this->returnEditorJsError( $e->getMessage() );
		}
	}

	/**
	 * Upload featured image
	 *
	 * Handles POST /admin/upload/featured-image
	 * Returns JSON with upload result
	 *
	 * @return void
	 */
	public function uploadFeaturedImage(): void
	{
		// Set JSON response header
		header( 'Content-Type: application/json' );

		try
		{
			// Check if file was uploaded
			if( !isset( $_FILES['image'] ) )
			{
				$this->returnError( 'No file was uploaded' );
				return;
			}

			$file = $_FILES['image'];

			// Validate file
			if( !$this->_validator->validate( $file ) )
			{
				$this->returnError( $this->_validator->getFirstError() );
				return;
			}

			// Upload to Cloudinary
			$result = $this->_uploader->upload( $file['tmp_name'] );

			// Return success response
			$this->returnSuccess( $result );
		}
		catch( \Exception $e )
		{
			$this->returnError( $e->getMessage() );
		}
	}

	/**
	 * Return Editor.js success response
	 *
	 * @param array $result Upload result
	 * @return void
	 */
	private function returnEditorJsSuccess( array $result ): void
	{
		echo json_encode( [
			'success' => 1,
			'file' => [
				'url' => $result['url'],
				'width' => $result['width'],
				'height' => $result['height']
			]
		] );
		exit;
	}

	/**
	 * Return Editor.js error response
	 *
	 * @param string $message Error message
	 * @return void
	 */
	private function returnEditorJsError( string $message ): void
	{
		http_response_code( 400 );
		echo json_encode( [
			'success' => 0,
			'message' => $message
		] );
		exit;
	}

	/**
	 * Return standard success response
	 *
	 * @param array $result Upload result
	 * @return void
	 */
	private function returnSuccess( array $result ): void
	{
		echo json_encode( [
			'success' => true,
			'data' => $result
		] );
		exit;
	}

	/**
	 * Return standard error response
	 *
	 * @param string $message Error message
	 * @return void
	 */
	private function returnError( string $message ): void
	{
		http_response_code( 400 );
		echo json_encode( [
			'success' => false,
			'error' => $message
		] );
		exit;
	}
}
