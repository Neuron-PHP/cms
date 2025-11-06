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
	private AuthManager $_AuthManager;
	private string $_LoginUrl = '/login';
	private string $_RedirectParam = 'redirect';

	public function __construct( AuthManager $AuthManager, string $LoginUrl = '/login' )
	{
		$this->_AuthManager = $AuthManager;
		$this->_LoginUrl = $LoginUrl;

		parent::__construct(
			function( RouteMap $Route ) { $this->checkAuthentication( $Route ); },
			null
		);
	}

	/**
	 * Check if user is authenticated
	 */
	protected function checkAuthentication( RouteMap $Route ): void
	{
		if( !$this->_AuthManager->check() )
		{
			// Store intended URL for post-login redirect
			$IntendedUrl = $_SERVER['REQUEST_URI'] ?? $Route->getPath();

			// Redirect to login page
			$RedirectUrl = $this->_LoginUrl . '?' . $this->_RedirectParam . '=' . urlencode( $IntendedUrl );

			header( 'Location: ' . $RedirectUrl );
			exit;
		}

		// Store authenticated user in Registry for easy access
		$User = $this->_AuthManager->user();
		if( $User )
		{
			Registry::getInstance()->set( 'Auth.User', $User );
			Registry::getInstance()->set( 'Auth.UserId', $User->getId() );
			Registry::getInstance()->set( 'Auth.UserRole', $User->getRole() );
		}
	}

	/**
	 * Set login URL
	 */
	public function setLoginUrl( string $LoginUrl ): self
	{
		$this->_LoginUrl = $LoginUrl;
		return $this;
	}
}
