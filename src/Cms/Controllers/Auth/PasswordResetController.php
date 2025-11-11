<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\PasswordResetManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\Html;
use Neuron\Patterns\Registry;
use Exception;

/**
 * Password reset controller.
 *
 * Handles password reset requests and password changes via email tokens.
 *
 * @package Neuron\Cms\Controllers\Auth
 */
class PasswordResetController extends Content
{
	private ?PasswordResetManager $_resetManager;
	private SessionManager $_sessionManager;
	private CsrfTokenManager $_csrfManager;

	/**
	 * @param Application|null $app
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get PasswordResetManager from Registry
		$this->_resetManager = Registry::getInstance()->get( 'PasswordResetManager' );

		if( !$this->_resetManager )
		{
			throw new \RuntimeException( 'PasswordResetManager not found in Registry. Ensure password reset is properly configured.' );
		}

		// Initialize session and CSRF managers
		$this->_sessionManager = new SessionManager();
		$this->_sessionManager->start();

		$this->_csrfManager = new CsrfTokenManager( $this->_sessionManager );
	}

	/**
	 * Show forgot password form
	 * @param array $parameters
	 * @return string
	 */
	public function showForgotPasswordForm( array $parameters ): string
	{
		$viewData = [
			'Title' => 'Forgot Password | ' . $this->getName(),
			'Description' => 'Reset your password',
			'PageSubtitle' => 'Reset Password',
			'CsrfToken' => $this->_csrfManager->getToken(),
			'Error' => $this->_sessionManager->getFlash( 'error' ),
			'Success' => $this->_sessionManager->getFlash( 'success' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Auth' )
			 ->setLayout( 'auth' )
			 ->setPage( 'forgot-password' );

		return $view->render( $viewData );
	}

	/**
	 * Process forgot password request
	 * @param array $parameters
	 * @return string
	 */
	public function requestReset( array $parameters ): string
	{
		// Validate CSRF token
		$token = $_POST['csrf_token'] ?? '';
		if( !$this->_csrfManager->validate( $token ) )
		{
			$this->_sessionManager->flash( 'error', 'Invalid CSRF token. Please try again.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Get email
		$email = $_POST['email'] ?? '';

		// Validate input
		if( empty( $email ) )
		{
			$this->_sessionManager->flash( 'error', 'Please enter your email address.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Validate email format
		if( !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			$this->_sessionManager->flash( 'error', 'Please enter a valid email address.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		try
		{
			// Request password reset
			$this->_resetManager->requestReset( $email );

			// Always show success message (don't reveal if email exists)
			$this->_sessionManager->flash(
				'success',
				'If an account exists with that email address, you will receive password reset instructions shortly.'
			);
		}
		catch( Exception $e )
		{
			// Log error but show generic message to user
			Log::error('Password reset request failed: ' . $e->getMessage() );
			$this->_sessionManager->flash(
				'error',
				'Unable to process password reset request. Please try again later.'
			);
		}

		header( 'Location: /forgot-password' );
		exit;
	}

	/**
	 * Show reset password form (with token)
	 * @param array $parameters
	 * @return string
	 */
	public function showResetForm( array $parameters ): string
	{
		// Get token from query string
		$token = $_GET['token'] ?? '';

		if( empty( $token ) )
		{
			$this->_sessionManager->flash( 'error', 'Invalid or missing reset token.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Validate token
		$tokenObj = $this->_resetManager->validateToken( $token );

		if( !$tokenObj )
		{
			$this->_sessionManager->flash( 'error', 'This password reset link is invalid or has expired.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		$viewData = [
			'Title' => 'Reset Password | ' . $this->getName(),
			'Description' => 'Enter your new password',
			'PageSubtitle' => 'Create New Password',
			'CsrfToken' => $this->_csrfManager->getToken(),
			'Error' => $this->_sessionManager->getFlash( 'error' ),
			'Success' => $this->_sessionManager->getFlash( 'success' ),
			'Token' => $token,
			'Email' => $tokenObj->getEmail()
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Auth' )
			 ->setLayout( 'auth' )
			 ->setPage( 'reset-password' );

		return $view->render( $viewData );
	}

	/**
	 * Process password reset
	 * @param array $parameters
	 * @return string
	 */
	public function resetPassword( array $parameters ): string
	{
		// Validate CSRF token
		$csrfToken = $_POST['csrf_token'] ?? '';
		if( !$this->_csrfManager->validate( $csrfToken ) )
		{
			$this->_sessionManager->flash( 'error', 'Invalid CSRF token. Please try again.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Get form data
		$token = $_POST['token'] ?? '';
		$password = $_POST['password'] ?? '';
		$passwordConfirmation = $_POST['password_confirmation'] ?? '';

		// Validate input
		if( empty( $token ) || empty( $password ) || empty( $passwordConfirmation ) )
		{
			$this->_sessionManager->flash( 'error', 'All fields are required.' );
			header( 'Location: /reset-password?token=' . urlencode( $token ) );
			exit;
		}

		// Validate passwords match
		if( $password !== $passwordConfirmation )
		{
			$this->_sessionManager->flash( 'error', 'Passwords do not match.' );
			header( 'Location: /reset-password?token=' . urlencode( $token ) );
			exit;
		}

		try
		{
			// Attempt password reset
			$success = $this->_resetManager->resetPassword( $token, $password );

			if( !$success )
			{
				$this->_sessionManager->flash( 'error', 'This password reset link is invalid or has expired.' );
				header( 'Location: /forgot-password' );
				exit;
			}

			// Success
			$this->_sessionManager->flash( 'success', 'Your password has been reset successfully. You can now log in.' );
			header( 'Location: /login' );
			exit;
		}
		catch( Exception $e )
		{
			// Show validation or other errors
			$this->_sessionManager->flash( 'error', $e->getMessage() );
			header( 'Location: /reset-password?token=' . urlencode( $token ) );
			exit;
		}
	}
}
