<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Patterns\Registry;

/**
 * Member authentication filter.
 *
 * Ensures user is authenticated and has member-level access (not admin-only).
 * Also checks for email verification if required.
 * Redirects to login page if not authenticated or to verification page if not verified.
 *
 * @package Neuron\Cms\Auth\Filters
 */
class MemberAuthenticationFilter extends Filter
{
	private Authentication $_authentication;
	private string $_loginUrl = '/login';
	private string $_verifyEmailUrl = '/verify-email-required';
	private string $_redirectParam = 'redirect';
	private bool $_requireEmailVerification = true;

	public function __construct(
		Authentication $authentication,
		string $loginUrl = '/login',
		bool $requireEmailVerification = true
	)
	{
		$this->_authentication = $authentication;
		$this->_loginUrl = $loginUrl;
		$this->_requireEmailVerification = $requireEmailVerification;

		parent::__construct(
			function( RouteMap $route ) { $this->checkAuthentication( $route ); },
			null
		);
	}

	/**
	 * Check if user is authenticated and verified
	 */
	protected function checkAuthentication( RouteMap $route ): void
	{
		$user = $this->_authentication->user();

		// Check if user is authenticated
		if( !$user )
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

		// Check if email verification is required
		if( $this->_requireEmailVerification && !$user->isEmailVerified() )
		{
			// Redirect to email verification required page
			header( 'Location: ' . $this->_verifyEmailUrl );
			exit;
		}

		// Set user in Registry for controllers to access
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

	/**
	 * Set verify email URL
	 */
	public function setVerifyEmailUrl( string $verifyEmailUrl ): self
	{
		$this->_verifyEmailUrl = $verifyEmailUrl;
		return $this;
	}

	/**
	 * Set whether email verification is required
	 */
	public function setRequireEmailVerification( bool $require ): self
	{
		$this->_requireEmailVerification = $require;
		return $this;
	}
}
