<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Log\Log;

/**
 * Composite filter combining authentication and CSRF protection.
 *
 * Validates both user authentication and CSRF tokens on state-changing requests.
 * This filter should be used for POST/PUT/DELETE endpoints that require both
 * authentication and CSRF protection.
 *
 * @package Neuron\Cms\Auth\Filters
 */
class AuthCsrfFilter extends Filter
{
	private Authentication $_authentication;
	private CsrfToken $_csrfToken;
	private string $_loginUrl;
	private array $_exemptMethods = ['GET', 'HEAD', 'OPTIONS'];

	/**
	 * Constructor
	 *
	 * @param Authentication $authentication Authentication service
	 * @param CsrfToken $csrfToken CSRF token service
	 * @param string $loginUrl URL to redirect to if not authenticated
	 */
	public function __construct( Authentication $authentication, CsrfToken $csrfToken, string $loginUrl = '/login' )
	{
		$this->_authentication = $authentication;
		$this->_csrfToken = $csrfToken;
		$this->_loginUrl = $loginUrl;

		parent::__construct(
			function( RouteMap $route ) { $this->validate( $route ); },
			null
		);
	}

	/**
	 * Validate both authentication and CSRF token
	 */
	protected function validate( RouteMap $route ): void
	{
		// 1. Check authentication first
		if( !$this->_authentication->isAuthenticated() )
		{
			Log::warning( 'Unauthenticated access attempt to protected route: ' . $route->Path );
			$this->redirectToLogin();
		}

		// 2. Check CSRF token for state-changing methods
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if( !in_array( strtoupper( $method ), $this->_exemptMethods ) )
		{
			$token = $this->getTokenFromRequest();

			if( !$token )
			{
				Log::warning( 'CSRF token missing from authenticated request to: ' . $route->Path );
				$this->respondForbidden( 'CSRF token missing' );
			}

			if( !$this->_csrfToken->validate( $token ) )
			{
				Log::warning( 'Invalid CSRF token from authenticated user on: ' . $route->Path );
				$this->respondForbidden( 'Invalid CSRF token' );
			}
		}
	}

	/**
	 * Get CSRF token from request
	 */
	private function getTokenFromRequest(): ?string
	{
		// Check POST data (filtered)
		$token = \Neuron\Data\Filters\Post::filterScalar( 'csrf_token' );
		if( $token )
		{
			return $token;
		}

		// Check headers (useful for AJAX requests)
		if( isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) )
		{
			return $_SERVER['HTTP_X_CSRF_TOKEN'];
		}

		return null;
	}

	/**
	 * Redirect to login page
	 */
	private function redirectToLogin(): void
	{
		header( 'Location: ' . $this->_loginUrl );
		exit;
	}

	/**
	 * Respond with 403 Forbidden
	 */
	private function respondForbidden( string $message ): void
	{
		http_response_code( 403 );
		echo '<h1>403 Forbidden</h1>';
		echo '<p>' . htmlspecialchars( $message ) . '</p>';
		exit;
	}
}
