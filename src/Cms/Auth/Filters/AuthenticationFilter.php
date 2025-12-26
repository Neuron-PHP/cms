<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Exceptions\UnauthenticatedException;
use Neuron\Patterns\Registry;

/**
 * Authentication filter.
 *
 * Ensures user is authenticated before accessing protected routes.
 * Redirects to login page if not authenticated.
 *
 * @package Neuron\Cms\Auth\Filters
 */
class AuthenticationFilter extends Filter
{
	private Authentication $_authentication;
	private string $_loginUrl = '/login';
	private string $_redirectParam = 'redirect';

	public function __construct( Authentication $authentication, string $loginUrl = '/login' )
	{
		$this->_authentication = $authentication;
		$this->_loginUrl = $loginUrl;

		parent::__construct(
			function( RouteMap $route ) { $this->checkAuthentication( $route ); },
			null
		);
	}

	/**
	 * Check if user is authenticated
	 *
	 * @throws UnauthenticatedException When user is not authenticated
	 */
	protected function checkAuthentication( RouteMap $route ): void
	{
		$user = $this->_authentication->user();
		if( !$user )
		{
			// Store intended URL for post-login redirect
			$intendedUrl = $_SERVER['REQUEST_URI'] ?? $route->getPath();

			// Build redirect URL to login page
			$separator = ( strpos( $this->_loginUrl, '?' ) === false ) ? '?' : '&';
			$query = http_build_query( [ $this->_redirectParam => $intendedUrl ] );
			$redirectUrl = $this->_loginUrl . $separator . $query;

			throw new UnauthenticatedException(
				$redirectUrl,
				$intendedUrl,
				'User not authenticated, redirect required to: ' . $redirectUrl
			);
		}

		Registry::getInstance()->set( 'Auth.User', $user );
		Registry::getInstance()->set( 'Auth.UserId', $user->getId() );
		Registry::getInstance()->set( 'Auth.UserRole', $user->getRole() );
	}

	/**
	 * Set login URL
	 */
	public function setLoginUrl( string $loginUrl ): self
	{
		$this->_loginUrl = $loginUrl;
		return $this;
	}
}
