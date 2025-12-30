<?php

namespace Neuron\Cms\Config;

/**
 * Upload configuration constants
 *
 * @package Neuron\Cms\Config
 */
final class UploadConfig
{
	/**
	 * Bytes per megabyte (for conversion)
	 */
	public const BYTES_PER_MB = 1048576;

	/**
	 * 5 MB in bytes
	 */
	public const MAX_FILE_SIZE_5MB = 5242880;

	/**
	 * 10 MB in bytes
	 */
	public const MAX_FILE_SIZE_10MB = 10485760;

	/**
	 * 20 MB in bytes
	 */
	public const MAX_FILE_SIZE_20MB = 20971520;

	/**
	 * Allowed image formats
	 */
	public const ALLOWED_IMAGE_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

	/**
	 * Allowed document formats
	 */
	public const ALLOWED_DOCUMENT_FORMATS = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

	private function __construct()
	{
		// Prevent instantiation
	}
}
