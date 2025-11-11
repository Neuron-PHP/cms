<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Cms\Auth\AuthManager;
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
	private AuthManager $_authManager;
	private string $_loginUrl = '/login';
	private string $_redirectParam = 'redirect';

	public function __construct( AuthManager $authManager, string $loginUrl = '/login' )
	{
		$this->_authManager = $authManager;
		$this->_loginUrl = $loginUrl;

		parent::__construct(
			function( RouteMap $route ) { $this->checkAuthentication( $route ); },
			null
		);
	}

	/**
	 * Check if user is authenticated
	 */
	protected function checkAuthentication( RouteMap $route ): void
	{
		if( !$this->_authManager->check() )
		{
			// Store intended URL for post-login redirect
			$intendedUrl = $_SERVER['REQUEST_URI'] ?? $route->getPath();

			// Redirect to login page
			$separator = ( strpos( $this->_loginUrl, '?' ) === false ) ? '?' : '&';
			$query = http_build_query( [ $this->_redirectParam => $intendedUrl ] );
			$redirectUrl = $this->_loginUrl . $separator . $query;

			header( 'Location: ' . $redirectUrl );
			exit;
		}

		// Store authenticated user in Registry for easy access
		$user = $this->_authManager->user();
		if( $user )
		{
			Registry::getInstance()->set( 'Auth.User', $user );
			Registry::getInstance()->set( 'Auth.UserId', $user->getId() );
			Registry::getInstance()->set( 'Auth.UserRole', $user->getRole() );
		}
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
