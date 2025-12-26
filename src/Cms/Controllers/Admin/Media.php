<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
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
	 * @param Application|null $app
	 */
	public function __construct( ?Application $app = null )
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
	 * @param Request $request
	 * @return string Rendered view
	 * @throws \Exception
	 */
	public function index( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			// Get pagination cursor from query string using framework's filter
			$nextCursor = $request->get( 'cursor' );

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
	 * @return string JSON response
	 */
	public function uploadImage(): string
	{
		try
		{
			// Check if file was uploaded
			if( !isset( $_FILES['image'] ) )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						'success' => 0,
						'message' => 'No file was uploaded'
					]
				);
			}

			$file = $_FILES['image'];

			// Validate file
			if( !$this->_validator->validate( $file ) )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						'success' => 0,
						'message' => $this->_validator->getFirstError()
					]
				);
			}

			// Upload to Cloudinary
			$result = $this->_uploader->upload( $file['tmp_name'] );

			// Return success response in Editor.js format
			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					'success' => 1,
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
			return $this->renderJson(
				HttpResponseStatus::INTERNAL_SERVER_ERROR,
				[
					'success' => 0,
					'message' => $e->getMessage()
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
	 * @return string JSON response
	 */
	public function uploadFeaturedImage(): string
	{
		try
		{
			// Check if file was uploaded
			if( !isset( $_FILES['image'] ) )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						'success' => false,
						'error' => 'No file was uploaded'
					]
				);
			}

			$file = $_FILES['image'];

			// Validate file
			if( !$this->_validator->validate( $file ) )
			{
				return $this->renderJson(
					HttpResponseStatus::BAD_REQUEST,
					[
						'success' => false,
						'error' => $this->_validator->getFirstError()
					]
				);
			}

			// Upload to Cloudinary
			$result = $this->_uploader->upload( $file['tmp_name'] );

			// Return success response
			return $this->renderJson(
				HttpResponseStatus::OK,
				[
					'success' => true,
					'data' => $result
				]
			);
		}
		catch( \Exception $e )
		{
			return $this->renderJson(
				HttpResponseStatus::INTERNAL_SERVER_ERROR,
				[
					'success' => false,
					'error' => $e->getMessage()
				]
			);
		}
	}
}
