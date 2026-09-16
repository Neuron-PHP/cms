<?php

namespace Neuron\Cms\Services\Redirect;

use Neuron\Cms\Models\Redirect;
use Neuron\Cms\Repositories\IRedirectRepository;
use Neuron\Log\Log;
use Throwable;

/**
 * Normalize, validate, and apply admin-managed HTTP redirects.
 *
 * @package Neuron\Cms\Services\Redirect
 */
class RedirectService
{
	private IRedirectRepository $_redirects;

	public function __construct( IRedirectRepository $redirects )
	{
		$this->_redirects = $redirects;
	}

	/**
	 * Look up an active redirect for the incoming URI and emit it.
	 *
	 * Returns null when no rule matches so routing can continue.
	 */
	public function resolveAndApply( string $uri ): mixed
	{
		$redirect = $this->resolve( $uri );

		if( $redirect === null )
		{
			return null;
		}

		$this->apply( $redirect );
	}

	public function resolve( string $uri ): ?Redirect
	{
		try
		{
			$path = self::normalizePath( $uri );

			if( self::isProtectedPath( $path ) )
			{
				return null;
			}

			return $this->_redirects->findActiveByFromPath( $path );
		}
		catch( Throwable $e )
		{
			Log::debug( 'Redirect lookup skipped: ' . $e->getMessage() );

			return null;
		}
	}

	public function apply( Redirect $redirect ): never
	{
		$location = $this->destinationUrl( $redirect, $this->incomingQuery() );

		http_response_code( $redirect->getStatusCode() );
		header( 'Location: ' . $location );
		exit;
	}

	public function destinationUrl( Redirect $redirect, string $query = '' ): string
	{
		$destination = $redirect->getToUrl();

		if( !$redirect->getPreserveQuery() || $query === '' )
		{
			return $destination;
		}

		if( str_contains( $destination, '?' ) )
		{
			return $destination;
		}

		return $destination . '?' . $query;
	}

	/**
	 * Validate a rule before save. Returns an error message or null.
	 */
	public function validate( string $fromPath, string $toUrl, int $statusCode = Redirect::STATUS_PERMANENT, ?int $excludeId = null ): ?string
	{
		if( trim( $fromPath ) === '' )
		{
			return 'Source path is required.';
		}

		if( trim( $toUrl ) === '' )
		{
			return 'Destination URL is required.';
		}

		$from = self::normalizePath( $fromPath );
		$to = self::normalizeDestination( $toUrl );

		if( self::isProtectedPath( $from ) )
		{
			return 'Admin paths cannot be redirected.';
		}

		if( $statusCode !== Redirect::STATUS_PERMANENT && $statusCode !== Redirect::STATUS_TEMPORARY )
		{
			return 'Status must be 301 or 302.';
		}

		if( $to === $from )
		{
			return 'Source and destination cannot be the same.';
		}

		if( $this->_redirects->fromPathExists( $from, $excludeId ) )
		{
			return 'A redirect from that path already exists.';
		}

		$reverse = $this->_redirects->findActiveByFromPath( $to );

		if( $reverse && self::normalizePath( $reverse->getToUrl() ) === $from )
		{
			if( $excludeId === null || $reverse->getId() !== $excludeId )
			{
				return 'That destination already redirects back to this path.';
			}
		}

		return null;
	}

	public function normalizeForSave( Redirect $redirect ): Redirect
	{
		$redirect->setFromPath( self::normalizePath( $redirect->getFromPath() ) );
		$redirect->setToUrl( self::normalizeDestination( $redirect->getToUrl() ) );

		return $redirect;
	}

	public static function normalizePath( string $path ): string
	{
		$path = trim( $path );

		if( preg_match( '#^https?://#i', $path ) )
		{
			$parsed = parse_url( $path );
			$path = $parsed['path'] ?? '/';
		}

		$path = explode( '?', $path, 2 )[0];
		$path = explode( '#', $path, 2 )[0];
		$path = trim( $path );

		if( $path === '' )
		{
			return '/';
		}

		if( $path[0] !== '/' )
		{
			$path = '/' . $path;
		}

		$path = preg_replace( '#/+#', '/', $path ) ?? $path;

		if( strlen( $path ) > 1 && str_ends_with( $path, '/' ) )
		{
			$path = rtrim( $path, '/' );
		}

		return $path;
	}

	public static function normalizeDestination( string $url ): string
	{
		$url = trim( $url );

		if( $url === '' )
		{
			return '';
		}

		if( preg_match( '#^https?://#i', $url ) )
		{
			return $url;
		}

		return self::normalizePath( $url );
	}

	public static function isProtectedPath( string $path ): bool
	{
		return $path === '/admin' || str_starts_with( $path, '/admin/' );
	}

	private function incomingQuery(): string
	{
		$query = $_SERVER['QUERY_STRING'] ?? '';

		if( $query === '' )
		{
			return '';
		}

		parse_str( $query, $params );
		unset( $params['route'] );

		return http_build_query( $params );
	}
}
