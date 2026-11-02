<?php

namespace Neuron\Cms\Navigation;

/**
 * A single link in the member shell navigation.
 *
 * The href is either a URL ("/controls", "https://...") or a route name
 * ("member_profile"), resolved when the layout renders.
 *
 * @package Neuron\Cms\Navigation
 */
final class NavItem
{
	/**
	 * @param string $key Unique key, used to remove or replace the item.
	 * @param string $label Link text.
	 * @param string $href URL or route name.
	 * @param int $order Lower sorts first.
	 * @param string|null $icon Bootstrap Icons name without the "bi-" prefix.
	 * @param \Closure|null $visible fn(): bool, evaluated at render time.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $href,
		public readonly int $order = 100,
		public readonly ?string $icon = null,
		public readonly ?\Closure $visible = null
	)
	{
	}

	/**
	 * Whether the item should be shown for the current request.
	 */
	public function isVisible(): bool
	{
		if( $this->visible === null )
		{
			return true;
		}

		return (bool)( $this->visible )();
	}

	/**
	 * The resolved URL for this item.
	 *
	 * @param callable(string): string|null $routeResolver Resolves a route name
	 *     to a path. Defaults to route_path() when available.
	 */
	public function url( ?callable $routeResolver = null ): string
	{
		if( self::isUrl( $this->href ) )
		{
			return $this->href;
		}

		$resolver = $routeResolver ?? ( function_exists( 'route_path' ) ? 'route_path' : null );

		if( $resolver === null )
		{
			return '#';
		}

		$path = (string)$resolver( $this->href );

		return $path !== '' ? $path : '#';
	}

	/**
	 * Whether the given request path is this item or below it.
	 */
	public function isActive( string $path, ?callable $routeResolver = null ): bool
	{
		$url = rtrim( (string)( parse_url( $this->url( $routeResolver ), PHP_URL_PATH ) ?? '' ), '/' );
		$path = rtrim( $path, '/' );

		if( $url === '' || $url === '#' )
		{
			return false;
		}

		return $path === $url || str_starts_with( $path, $url . '/' );
	}

	private static function isUrl( string $href ): bool
	{
		return $href === ''
			|| str_starts_with( $href, '/' )
			|| str_starts_with( $href, '#' )
			|| str_contains( $href, '://' );
	}
}
