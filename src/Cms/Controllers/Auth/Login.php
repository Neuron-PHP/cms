<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\AuthManager;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use Neuron\Mvc\Requests\Request;

/**
 * Login controller.
 *
 * Handles user authentication (login/logout).
 *
 * @package Neuron\Cms\Controllers\Auth
 */
class Login extends Content
{
	private ?AuthManager $_authManager;
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
			throw new \RuntimeException( 'AuthManager not found in Registry.' );
		}

		// Initialize CSRF manager with parent's session manager
		$this->_csrfManager = new CsrfTokenManager( $this->getSessionManager() );
	}

	/**
	 * Show login form
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	public function showLoginForm( Request $request ): string
	{
		// If already logged in, redirect to the dashboard
		if( $this->_authManager->check() )
		{
			$this->redirect( 'admin_dashboard' );
		}

		// Set CSRF token in Registry so csrf_field() helper works
		Registry::getInstance()->set( 'Auth.CsrfToken', $this->_csrfManager->getToken() );

		$defaultRedirect = $this->urlFor( 'admin_dashboard' ) ?? '/admin/dashboard';
		$requestedRedirect = $request->get( 'redirect', $defaultRedirect );
		$redirectUrl = $this->isValidRedirectUrl( $requestedRedirect )
			? $requestedRedirect
			: $defaultRedirect;

		$viewData = [
			'Title' => 'Login | ' . $this->getName(),
			'Description' => 'Login to ' . $this->getName(),
			'Error' => $this->getSessionManager()->getFlash( 'error' ),
			'Success' => $this->getSessionManager()->getFlash( 'success' ),
			'RedirectUrl' => $redirectUrl
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'login',
			'auth'
		);
	}

	/**
	 * Process login
	 *
	 * @param Request $request
	 * @return never
	 */
	public function login( Request $request ): never
	{
		// Validate CSRF token
		$token = $request->post( 'csrf_token' );

		if( !$this->_csrfManager->validate( $token ) )
		{
			$this->redirect( 'login', [], ['error', 'Invalid CSRF token. Please try again.'] );
		}

		// Get credentials
		$username = $request->post( 'username', '' );
		$password = $request->post( 'password', '' );
		$remember = $request->post( 'remember' ) === 'on';

		// Validate input
		if( empty( $username ) || empty( $password ) )
		{
			$this->redirect( 'login', [], ['error', 'Please enter both username and password.'] );
		}

		// Attempt authentication
		if( !$this->_authManager->attempt( $username, $password, $remember ) )
		{
			$this->redirect( 'login', [], ['error', 'Invalid username or password.'] );
		}

		// Successful login - redirect to intended URL or dashboard
		$defaultRedirect = $this->urlFor( 'admin_dashboard' ) ?? '/admin/dashboard';
		$requestedRedirect = $_POST['redirect_url'] ?? $defaultRedirect;
		$redirectUrl = $this->isValidRedirectUrl( $requestedRedirect )
			? $requestedRedirect
			: $defaultRedirect;

		$this->redirectToUrl( $redirectUrl, [ 'success', 'Welcome back!' ] );
	}

	/**
	 * Process logout
	 * @param Request $request
	 * @return never
	 */
	public function logout( Request $request ): never
	{
		$this->_authManager->logout();
		$this->redirect( 'login', [], ['success', 'You have been logged out successfully.'] );
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
		if( str_contains( $url, '@' ) || str_contains( $url, '\\' ) )
		{
			return false;
		}

		return true;
	}
}
