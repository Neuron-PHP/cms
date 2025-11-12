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

if (!function_exists('route_path')) {
	/**
	 * Generate a relative URL path for a named route
	 *
	 * @param string $routeName The name of the route
	 * @param array $parameters Route parameters
	 * @return string Relative URL path
	 *
	 * @example
	 * route_path('blog_post', ['slug' => 'my-post']) // Returns: /blog/post/my-post
	 * route_path('admin_dashboard') // Returns: /admin/dashboard
	 */
	function route_path(string $routeName, array $parameters = []): string
	{
		$app = \Neuron\Patterns\Registry::getInstance()->get('App');
		if (!$app) {
			return '';
		}

		$urlHelper = new \Neuron\Mvc\Helpers\UrlHelper($app->getRouter());
		return $urlHelper->routePath($routeName, $parameters) ?? '';
	}
}

if (!function_exists('route_url')) {
	/**
	 * Generate an absolute URL for a named route
	 *
	 * @param string $routeName The name of the route
	 * @param array $parameters Route parameters
	 * @return string Absolute URL
	 *
	 * @example
	 * route_url('blog_post', ['slug' => 'my-post']) // Returns: https://example.com/blog/post/my-post
	 * route_url('admin_dashboard') // Returns: https://example.com/admin/dashboard
	 */
	function route_url(string $routeName, array $parameters = []): string
	{
		$app = \Neuron\Patterns\Registry::getInstance()->get('App');
		if (!$app) {
			return '';
		}

		$urlHelper = new \Neuron\Mvc\Helpers\UrlHelper($app->getRouter());
		return $urlHelper->routeUrl($routeName, $parameters) ?? '';
	}
}

if (!function_exists('format_user_datetime')) {
	/**
	 * Format a DateTimeImmutable object in the authenticated user's timezone
	 *
	 * Converts a DateTime from UTC to the user's preferred timezone and formats it.
	 * Falls back to system timezone if no user is authenticated or user has no timezone set.
	 *
	 * @param \DateTimeImmutable|null $dateTime DateTime to format (assumed to be in UTC)
	 * @param string $format Date format string (default: 'Y-m-d H:i')
	 * @return string Formatted date/time string, or 'N/A' if dateTime is null
	 *
	 * @example
	 * format_user_datetime($post->getCreatedAt()) // Returns: 2024-11-10 15:30
	 * format_user_datetime($post->getCreatedAt(), 'F j, Y g:i A') // Returns: November 10, 2024 3:30 PM
	 */
	function format_user_datetime(?\DateTimeImmutable $dateTime, string $format = 'Y-m-d H:i'): string
	{
		if ($dateTime === null) {
			return 'N/A';
		}

		// Get authenticated user's timezone
		$user = \Neuron\Patterns\Registry::getInstance()->get('Auth.User');
		$userTimezone = $user ? $user->getTimezone() : null;

		// Fall back to system timezone if user timezone not available
		if (!$userTimezone) {
			$settings = \Neuron\Patterns\Registry::getInstance()->get('Settings');
			$userTimezone = $settings ? $settings->get('system', 'timezone') : date_default_timezone_get();
		}

		try {
			// Convert to user's timezone
			$timezone = new \DateTimeZone($userTimezone);
			$localDateTime = $dateTime->setTimezone($timezone);
			return $localDateTime->format($format);
		} catch (\Exception $e) {
			// If timezone conversion fails, return original format
			return $dateTime->format($format);
		}
	}
}

if (!function_exists('format_user_date')) {
	/**
	 * Format a DateTimeImmutable object as a date (no time) in the authenticated user's timezone
	 *
	 * Convenience wrapper around format_user_datetime for date-only display.
	 *
	 * @param \DateTimeImmutable|null $dateTime DateTime to format (assumed to be in UTC)
	 * @param string $format Date format string (default: 'Y-m-d')
	 * @return string Formatted date string, or 'N/A' if dateTime is null
	 *
	 * @example
	 * format_user_date($post->getCreatedAt()) // Returns: 2024-11-10
	 * format_user_date($post->getCreatedAt(), 'F j, Y') // Returns: November 10, 2024
	 */
	function format_user_date(?\DateTimeImmutable $dateTime, string $format = 'Y-m-d'): string
	{
		return format_user_datetime($dateTime, $format);
	}
}

if (!function_exists('get_timezones')) {
	/**
	 * Get a list of all available timezones grouped by region
	 *
	 * Returns an associative array with regions as keys and arrays of timezones as values.
	 * Useful for populating timezone dropdown selects.
	 *
	 * @return array<string, array<string>> Grouped timezones
	 *
	 * @example
	 * $timezones = get_timezones();
	 * // Returns: ['America' => ['America/New_York', 'America/Chicago', ...], ...]
	 */
	function get_timezones(): array
	{
		$timezones = \DateTimeZone::listIdentifiers();
		$grouped = [];

		foreach ($timezones as $timezone) {
			$parts = explode('/', $timezone, 2);
			if (count($parts) === 2) {
				$region = $parts[0];
				if (!isset($grouped[$region])) {
					$grouped[$region] = [];
				}
				$grouped[$region][] = $timezone;
			}
		}

		return $grouped;
	}
}
