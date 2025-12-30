<?php

namespace Neuron\Cms\Services;

/**
 * Slug Generator Service
 *
 * Generates URL-safe slugs from text strings. Used across the CMS for creating
 * SEO-friendly URLs for posts, pages, events, categories, and tags.
 *
 * Handles:
 * - Converting text to lowercase
 * - Replacing non-alphanumeric characters with hyphens
 * - Removing consecutive hyphens
 * - Trimming hyphens from start/end
 * - Fallback for non-ASCII text (e.g., Chinese, Arabic)
 *
 * @package Neuron\Cms\Services
 */
class SlugGenerator
{
	/**
	 * Generate a URL-safe slug from a string
	 *
	 * @param string $text The text to convert to a slug
	 * @param string $fallbackPrefix Prefix for fallback slug when no ASCII characters (default: 'item')
	 * @return string The generated slug
	 */
	public function generate( string $text, string $fallbackPrefix = 'item' ): string
	{
		// Convert to lowercase and trim whitespace
		$slug = strtolower( trim( $text ) );

		// Replace non-alphanumeric characters with hyphens
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );

		// Replace consecutive hyphens with a single hyphen
		$slug = preg_replace( '/-+/', '-', $slug );

		// Trim hyphens from start and end
		$slug = trim( $slug, '-' );

		// Fallback for text with no ASCII characters (e.g., "你好", "مرحبا")
		if( $slug === '' )
		{
			$slug = $fallbackPrefix . '-' . uniqid();
		}

		return $slug;
	}

	/**
	 * Generate a slug with uniqueness check via callback
	 *
	 * If the generated slug already exists, appends a number suffix
	 * to make it unique (e.g., "my-post", "my-post-2", "my-post-3")
	 *
	 * @param string $text The text to convert to a slug
	 * @param callable $existsCallback Callback to check if slug exists: fn(string $slug): bool
	 * @param string $fallbackPrefix Prefix for fallback slug when no ASCII characters
	 * @return string A unique slug
	 */
	public function generateUnique( string $text, callable $existsCallback, string $fallbackPrefix = 'item' ): string
	{
		$baseSlug = $this->generate( $text, $fallbackPrefix );
		$slug = $baseSlug;
		$counter = 2;

		// Keep incrementing counter until we find a unique slug
		while( $existsCallback( $slug ) )
		{
			$slug = $baseSlug . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	/**
	 * Validate if a string is a valid slug format
	 *
	 * Valid slugs contain only lowercase letters, numbers, and hyphens.
	 * They cannot start or end with a hyphen.
	 *
	 * @param string $slug The slug to validate
	 * @return bool True if valid, false otherwise
	 */
	public function isValid( string $slug ): bool
	{
		// Empty slug is invalid
		if( $slug === '' )
		{
			return false;
		}

		// Must match pattern: lowercase letters, numbers, hyphens only
		// Cannot start or end with hyphen
		// Cannot have consecutive hyphens
		return (bool)preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug );
	}

	/**
	 * Clean an existing slug (useful for user-provided slugs)
	 *
	 * @param string $slug The slug to clean
	 * @param string $fallbackPrefix Prefix for fallback if slug becomes empty after cleaning
	 * @return string The cleaned slug
	 */
	public function clean( string $slug, string $fallbackPrefix = 'item' ): string
	{
		return $this->generate( $slug, $fallbackPrefix );
	}
}
