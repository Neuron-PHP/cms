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
