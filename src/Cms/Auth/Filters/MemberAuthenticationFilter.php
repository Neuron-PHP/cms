<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Exceptions\UnauthenticatedException;
use Neuron\Cms\Exceptions\EmailVerificationRequiredException;
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
	 *
	 * @throws UnauthenticatedException When user is not authenticated
	 * @throws EmailVerificationRequiredException When email verification is required but not completed
	 */
	protected function checkAuthentication( RouteMap $route ): void
	{
		$user = $this->_authentication->user();

		// Check if user is authenticated
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

		// Check if email verification is required
		if( $this->_requireEmailVerification && !$user->isEmailVerified() )
		{
			throw new EmailVerificationRequiredException(
				$this->_verifyEmailUrl,
				'Email verification required for user ID: ' . $user->getId()
			);
		}

		// Set user in Registry for controllers to access
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );
		Registry::getInstance()->set( RegistryKeys::AUTH_USER_ID, $user->getId() );
		Registry::getInstance()->set( RegistryKeys::AUTH_USER_ROLE, $user->getRole() );
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
