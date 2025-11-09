<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\AuthManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;

/**
 * Login controller.
 *
 * Handles user authentication (login/logout).
 *
 * @package Neuron\Cms\Controllers\Auth
 */
class LoginController extends Content
{
	private AuthManager $_AuthManager;
	private SessionManager $_SessionManager;
	private CsrfTokenManager $_CsrfManager;

	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Initialize auth components
		$StoragePath = $this->getApp()->getBasePath() . '/storage/users';
		$UserRepository = new \Neuron\Cms\Repositories\UserRepository( $StoragePath );

		$this->_SessionManager = new SessionManager();
		$this->_SessionManager->start();

		$PasswordHasher = new \Neuron\Cms\Auth\PasswordHasher();

		$this->_AuthManager = new AuthManager(
			$UserRepository,
			$this->_SessionManager,
			$PasswordHasher
		);

		$this->_CsrfManager = new CsrfTokenManager( $this->_SessionManager );
	}

	/**
	 * Validate redirect URL to prevent open redirect vulnerabilities
	 *
	 * @param string $url The URL to validate
	 * @return bool True if the URL is safe to use
	 */
	private function isValidRedirectUrl( string $url ): bool
	{
		// Only allow relative URLs or same-origin absolute URLs
		if( empty( $url ) )
		{
			return false;
		}
		
		// Reject URLs with schemes (http://, https://, javascript:, etc.)
		if( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) )
		{
			return false;
		}
		
		// Reject protocol-relative URLs (//example.com)
		if( str_starts_with( $url, '//' ) )
		{
			return false;
		}
		
		// Must start with / for internal path
		return str_starts_with( $url, '/' );
	}

	/**
	 * Show login form
	 */
	public function showLoginForm( array $Parameters ): string
	{
		// If already logged in, redirect to dashboard
		if( $this->_AuthManager->check() )
		{
			header( 'Location: /admin/dashboard' );
			exit;
		}

		$requestedRedirect = $_GET['redirect'] ?? '/admin/dashboard';
		$redirectUrl = $this->isValidRedirectUrl( $requestedRedirect ) 
			? $requestedRedirect 
			: '/admin/dashboard';

		$ViewData = [
			'Title' => 'Login | ' . $this->getName(),
			'Description' => 'Login to ' . $this->getName(),
			'CsrfToken' => $this->_CsrfManager->getToken(),
			'Error' => $this->_SessionManager->getFlash( 'error' ),
			'Success' => $this->_SessionManager->getFlash( 'success' ),
			'RedirectUrl' => $redirectUrl
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'login',
			'auth'
		);
	}

	/**
	 * Process login
	 */
	public function login( array $Parameters ): string
	{
		// Validate CSRF token
		$Token = $_POST['csrf_token'] ?? '';
		if( !$this->_CsrfManager->validate( $Token ) )
		{
			$this->_SessionManager->flash( 'error', 'Invalid CSRF token. Please try again.' );
			header( 'Location: /login' );
			exit;
		}

		// Get credentials
		$Username = $_POST['username'] ?? '';
		$Password = $_POST['password'] ?? '';
		$Remember = isset( $_POST['remember'] );

		// Validate input
		if( empty( $Username ) || empty( $Password ) )
		{
			$this->_SessionManager->flash( 'error', 'Please enter both username and password.' );
			header( 'Location: /login' );
			exit;
		}

		// Attempt authentication
		if( !$this->_AuthManager->attempt( $Username, $Password, $Remember ) )
		{
			$this->_SessionManager->flash( 'error', 'Invalid username or password.' );
			header( 'Location: /login' );
			exit;
		}

		// Successful login
		$this->_SessionManager->flash( 'success', 'Welcome back!' );

		// Redirect to intended URL or dashboard
		$requestedRedirect = $_POST['redirect_url'] ?? '/admin/dashboard';
		$RedirectUrl = $this->isValidRedirectUrl( $requestedRedirect )
			? $requestedRedirect
			: '/admin/dashboard';
		header( 'Location: ' . $RedirectUrl );
		exit;
	}

	/**
	 * Process logout
	 */
	public function logout( array $Parameters ): string
	{
		$this->_AuthManager->logout();

		$this->_SessionManager->start();
		$this->_SessionManager->flash( 'success', 'You have been logged out successfully.' );

		header( 'Location: /login' );
		exit;
	}

	/**
	 * Validate if a redirect URL is safe to use.
	 * Only allows relative URLs (starting with /) to prevent open redirect vulnerabilities.
	 *
	 * @param string $url The URL to validate
	 * @return bool True if the URL is safe, false otherwise
	 */
	private function isValidRedirectUrl( string $url ): bool
	{
		// Empty URLs are not valid
		if( $url === '' )
		{
			return false;
		}

		// Only allow relative URLs that start with /
		if( $url[0] !== '/' )
		{
			return false;
		}

		// Prevent protocol-relative URLs (//example.com)
		if( strlen( $url ) > 1 && $url[1] === '/' )
		{
			return false;
		}

		// Check for malicious patterns
		// Prevent URLs with @ symbol (could be used for phishing: /path@evil.com)
		// Prevent URLs with backslashes (could bypass filters: /\evil.com)
		if( strpos( $url, '@' ) !== false || strpos( $url, '\\' ) !== false )
		{
			return false;
		}

		return true;
	}
}
