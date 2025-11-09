<?php

/**
 * View helper functions for Neuron CMS.
 *
 * Provides convenient view-related functionality including Gravatar support.
 *
 * @package Neuron\Cms\View
 */

if (!function_exists('gravatar_url')) {
	/**
	 * Generate a Gravatar URL for the given email address
	 *
	 * @param string $email Email address
	 * @param int $size Image size in pixels (default: 80)
	 * @param string $default Default image type (mp, identicon, monsterid, wavatar, retro, robohash, blank)
	 * @return string Gravatar URL
	 *
	 * @example
	 * gravatar_url('user@example.com', 128, 'identicon')
	 */
	function gravatar_url(string $email, int $size = 80, string $default = 'mp'): string
	{
		$hash = md5(strtolower(trim($email)));
		return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}";
	}
}

if (!function_exists('gravatar')) {
	/**
	 * Generate a complete Gravatar image tag
	 *
	 * @param string $email Email address
	 * @param int $size Image size in pixels (default: 80)
	 * @param string $default Default image type (mp, identicon, monsterid, wavatar, retro, robohash, blank)
	 * @param string $class Additional CSS classes to add to the img tag
	 * @return string Complete HTML img tag
	 *
	 * @example
	 * gravatar('user@example.com', 32, 'mp', 'rounded-circle')
	 */
	function gravatar(string $email, int $size = 80, string $default = 'mp', string $class = ''): string
	{
		$url = gravatar_url($email, $size, $default);
		$classes = $class ? ' ' . htmlspecialchars($class) : '';

		return sprintf(
			'<img src="%s" width="%d" height="%d" alt="Profile Picture" class="gravatar%s">',
			htmlspecialchars($url),
			$size,
			$size,
			$classes
		);
	}
}
