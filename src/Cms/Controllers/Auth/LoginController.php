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
	private ?AuthManager $_authManager;
	private SessionManager $_sessionManager;
	private CsrfTokenManager $_csrfManager;

	/**
	 * @param Application|null $app
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get AuthManager from Registry (set up by AuthInitializer)
		$this->_authManager = Registry::getInstance()->get( 'AuthManager' );

		if( !$this->_authManager )
		{
			throw new \RuntimeException( 'AuthManager not found in Registry. Ensure authentication is properly configured and that Application is set in Registry before initializers run.' );
		}

		// Initialize session and CSRF managers
		$this->_sessionManager = new SessionManager();
		$this->_sessionManager->start();

		$this->_csrfManager = new CsrfTokenManager( $this->_sessionManager );
	}

	/**
	 * Show login form
	 * @param array $parameters
	 * @return string
	 */
	public function showLoginForm( array $parameters ): string
	{
		// If already logged in, redirect to dashboard
		if( $this->_authManager->check() )
		{
			header( 'Location: /admin/dashboard' );
			exit;
		}

		$requestedRedirect = $_GET['redirect'] ?? '/admin/dashboard';
		$redirectUrl = $this->isValidRedirectUrl( $requestedRedirect )
			? $requestedRedirect
			: '/admin/dashboard';

		$viewData = [
			'Title' => 'Login | ' . $this->getName(),
			'Description' => 'Login to ' . $this->getName(),
			'CsrfToken' => $this->_csrfManager->getToken(),
			'Error' => $this->_sessionManager->getFlash( 'error' ),
			'Success' => $this->_sessionManager->getFlash( 'success' ),
			'RedirectUrl' => $redirectUrl
		];

		// Manually render with custom controller path
		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Auth' )
			 ->setLayout( 'auth' )
			 ->setPage( 'login' );

		return $view->render( $viewData );
	}

	/**
	 * Process login
	 * @param array $parameters
	 * @return string
	 */
	public function login( array $parameters ): string
	{
		// Validate CSRF token
		$token = $_POST['csrf_token'] ?? '';
		if( !$this->_csrfManager->validate( $token ) )
		{
			$this->_sessionManager->flash( 'error', 'Invalid CSRF token. Please try again.' );
			header( 'Location: /login' );
			exit;
		}

		// Get credentials
		$username = $_POST['username'] ?? '';
		$password = $_POST['password'] ?? '';
		$remember = isset( $_POST['remember'] );

		// Validate input
		if( empty( $username ) || empty( $password ) )
		{
			$this->_sessionManager->flash( 'error', 'Please enter both username and password.' );
			header( 'Location: /login' );
			exit;
		}

		// Attempt authentication
		if( !$this->_authManager->attempt( $username, $password, $remember ) )
		{
			$this->_sessionManager->flash( 'error', 'Invalid username or password.' );
			header( 'Location: /login' );
			exit;
		}

		// Successful login
		$this->_sessionManager->flash( 'success', 'Welcome back!' );

		// Redirect to intended URL or dashboard
		$requestedRedirect = $_POST['redirect_url'] ?? '/admin/dashboard';
		$redirectUrl = $this->isValidRedirectUrl( $requestedRedirect )
			? $requestedRedirect
			: '/admin/dashboard';
		header( 'Location: ' . $redirectUrl );
		exit;
	}

	/**
	 * Process logout
	 * @param array $parameters
	 * @return string
	 */
	public function logout( array $parameters ): string
	{
		$this->_authManager->logout();

		$this->_sessionManager->start();
		$this->_sessionManager->flash( 'success', 'You have been logged out successfully.' );

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
