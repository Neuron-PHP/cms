<?php

namespace Neuron\Cms\Services\Media;

/**
 * Interface for media upload services.
 *
 * Defines the contract for uploading and managing media files.
 *
 * @package Neuron\Cms\Services\Media
 */
interface IMediaUploader
{
	/**
	 * Upload a file from local filesystem
	 *
	 * @param string $filePath Path to the file to upload
	 * @param array $options Upload options (folder, transformation, etc.)
	 * @return array Upload result with keys: url, public_id, width, height, format
	 * @throws \Exception If upload fails
	 */
	public function upload( string $filePath, array $options = [] ): array;

	/**
	 * Upload a file from URL
	 *
	 * @param string $url URL of the file to upload
	 * @param array $options Upload options (folder, transformation, etc.)
	 * @return array Upload result with keys: url, public_id, width, height, format
	 * @throws \Exception If upload fails
	 */
	public function uploadFromUrl( string $url, array $options = [] ): array;

	/**
	 * Delete a file by its public ID
	 *
	 * @param string $publicId The public ID of the file to delete
	 * @return bool True if deletion was successful
	 * @throws \Exception If deletion fails
	 */
	public function delete( string $publicId ): bool;
}
