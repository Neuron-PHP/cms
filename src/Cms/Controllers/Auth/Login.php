<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Controllers\Traits\UsesDtos;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Services\Auth\IAuthenticationService;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

/**
 * Login controller.
 *
 * Handles user authentication (login/logout).
 *
 * @package Neuron\Cms\Controllers\Auth
 */
class Login extends Content
{
	use UsesDtos;

	private IAuthenticationService $_authentication;

	/**
	 * @param IMvcApplication $app
	 * @param IAuthenticationService $authentication
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		IAuthenticationService $authentication,
		SettingManager $settings,
		SessionManager $sessionManager
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_authentication = $authentication;
	}

	/**
	 * Show login form
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/login', name: 'login')]
	public function showLoginForm( Request $request ): string
	{
		// If already logged in, redirect to the dashboard
		if( $this->_authentication->check() )
		{
			$this->redirect( 'admin_dashboard' );
		}

		$this->initializeCsrfToken();

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
	#[Post('/login', name: 'login_post', filters: ['csrf'])]
	public function login( Request $request ): never
	{
		// Create and validate DTO
		$dto = $this->createDto( 'auth/login-request.yaml' );
		$this->mapRequestToDto( $dto, $request );

		// Convert 'on' checkbox value to boolean
		if( $request->post( 'remember' ) === 'on' )
		{
			$dto->remember = true;
		}

		// Validate DTO
		if( !$dto->validate() )
		{
			$errors = implode( ', ', $dto->getErrors() );
			$this->redirect( 'login', [], [FlashMessageType::ERROR->value, $errors] );
		}

		// Attempt authentication
		if( !$this->_authentication->attempt( $dto->username, $dto->password, $dto->remember ) )
		{
			$this->redirect( 'login', [], [FlashMessageType::ERROR->value, 'Invalid username or password.'] );
		}

		// Successful login - redirect to intended URL or dashboard
		$defaultRedirect = $this->urlFor( 'admin_dashboard', [], '/admin/dashboard' ) ?? '/admin/dashboard';
		$requestedRedirect = $dto->redirect_url ?? $defaultRedirect;

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
	#[Post('/logout', name: 'logout', filters: ['auth', 'csrf'])]
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
