<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Services\Auth\CsrfToken;
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
	private ?Authentication $_authentication;
	private CsrfToken $_csrfToken;

	/**
	 * @param Application|null $app
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get Authentication from Registry (set up by AuthInitializer)
		$this->_authentication = Registry::getInstance()->get( 'Authentication' );

		if( !$this->_authentication )
		{
			throw new \RuntimeException( 'Authentication not found in Registry.' );
		}

		// Initialize CSRF manager with parent's session manager
		$this->_csrfToken = new CsrfToken( $this->getSessionManager() );
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
		if( $this->_authentication->check() )
		{
			$this->redirect( 'admin_dashboard' );
		}

		// Set CSRF token in Registry so csrf_field() helper works
		Registry::getInstance()->set( 'Auth.CsrfToken', $this->_csrfToken->getToken() );

		// Get redirect parameter from URL or default to admin dashboard
		$defaultRedirect = $this->urlFor( 'admin_dashboard', [], '/admin/dashboard' ) ?? '/admin/dashboard';
		$requestedRedirect = $request->get( 'redirect', $defaultRedirect ) ?? $defaultRedirect;

		// Validate and use requested redirect, fallback to default if invalid
		$redirectUrl = $this->isValidRedirectUrl( $requestedRedirect )
			? $requestedRedirect
			: $defaultRedirect;

		return $this->view()
			->title( 'Login' )
			->description( 'Login to ' . $this->getName() )
			->withCsrfToken()
			->with( FlashMessageType::ERROR->value, $this->getSessionManager()->getFlash( 'error' ) )
			->with( FlashMessageType::SUCCESS->value, $this->getSessionManager()->getFlash( 'success' ) )
			->with( 'RedirectUrl', $redirectUrl )
			->render( 'login', 'auth' );
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

		if( !$this->_csrfToken->validate( $token ) )
		{
			$this->redirect( 'login', [], [FlashMessageType::ERROR->value, 'Invalid CSRF token. Please try again.'] );
		}

		// Get credentials
		$username = $request->post( 'username', '' );
		$password = $request->post( 'password', '' );
		$remember = $request->post( 'remember' ) === 'on';

		// Validate input
		if( empty( $username ) || empty( $password ) )
		{
			$this->redirect( 'login', [], [FlashMessageType::ERROR->value, 'Please enter both username and password.'] );
		}

		// Attempt authentication
		if( !$this->_authentication->attempt( $username, $password, $remember ) )
		{
			$this->redirect( 'login', [], [FlashMessageType::ERROR->value, 'Invalid username or password.'] );
		}

		// Successful login - redirect to intended URL or dashboard
		$defaultRedirect = $this->urlFor( 'admin_dashboard', [], '/admin/dashboard' ) ?? '/admin/dashboard';
		$requestedRedirect = $request->post( 'redirect_url', $defaultRedirect ) ?? $defaultRedirect;

		// Validate and use requested redirect, fallback to default if invalid
		$redirectUrl = $this->isValidRedirectUrl( $requestedRedirect )
			? $requestedRedirect
			: $defaultRedirect;

		$this->redirectToUrl( $redirectUrl, [ FlashMessageType::SUCCESS->value, 'Welcome back!' ] );
	}

	/**
	 * Process logout
	 * @param Request $request
	 * @return never
	 */
	public function logout( Request $request ): never
	{
		$this->_authentication->logout();
		$this->redirect( 'home', [], [FlashMessageType::SUCCESS->value, 'You have been logged out successfully.'] );
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
