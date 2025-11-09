<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\AuthManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\Html;
use Neuron\Patterns\Registry;

/**
 * Login controller.
 *
 * Handles user authentication (login/logout).
 *
 * @package Neuron\Cms\Controllers\Auth
 */
class LoginController extends Content
{
	private ?AuthManager $_AuthManager;
	private SessionManager $_SessionManager;
	private CsrfTokenManager $_CsrfManager;

	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get AuthManager from Registry (set up by AuthInitializer)
		$this->_AuthManager = Registry::getInstance()->get( 'AuthManager' );

		if( !$this->_AuthManager )
		{
			throw new \RuntimeException( 'AuthManager not found in Registry. Ensure authentication is properly configured and that Application is set in Registry before initializers run.' );
		}

		// Initialize session and CSRF managers
		$this->_SessionManager = new SessionManager();
		$this->_SessionManager->start();

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

		// Manually render with custom controller path
		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Auth' )
			 ->setLayout( 'auth' )
			 ->setPage( 'login' );

		return $View->render( $ViewData );
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
}
