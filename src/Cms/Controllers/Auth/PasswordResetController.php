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
	private ?PasswordResetManager $_ResetManager;
	private SessionManager $_SessionManager;
	private CsrfTokenManager $_CsrfManager;

	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get PasswordResetManager from Registry
		$this->_ResetManager = Registry::getInstance()->get( 'PasswordResetManager' );

		if( !$this->_ResetManager )
		{
			throw new \RuntimeException( 'PasswordResetManager not found in Registry. Ensure password reset is properly configured.' );
		}

		// Initialize session and CSRF managers
		$this->_SessionManager = new SessionManager();
		$this->_SessionManager->start();

		$this->_CsrfManager = new CsrfTokenManager( $this->_SessionManager );
	}

	/**
	 * Show forgot password form
	 */
	public function showForgotPasswordForm( array $Parameters ): string
	{
		$ViewData = [
			'Title' => 'Forgot Password | ' . $this->getName(),
			'Description' => 'Reset your password',
			'PageSubtitle' => 'Reset Password',
			'CsrfToken' => $this->_CsrfManager->getToken(),
			'Error' => $this->_SessionManager->getFlash( 'error' ),
			'Success' => $this->_SessionManager->getFlash( 'success' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Auth' )
			 ->setLayout( 'auth' )
			 ->setPage( 'forgot-password' );

		return $View->render( $ViewData );
	}

	/**
	 * Process forgot password request
	 */
	public function requestReset( array $Parameters ): string
	{
		// Validate CSRF token
		$Token = $_POST['csrf_token'] ?? '';
		if( !$this->_CsrfManager->validate( $Token ) )
		{
			$this->_SessionManager->flash( 'error', 'Invalid CSRF token. Please try again.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Get email
		$Email = $_POST['email'] ?? '';

		// Validate input
		if( empty( $Email ) )
		{
			$this->_SessionManager->flash( 'error', 'Please enter your email address.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Validate email format
		if( !filter_var( $Email, FILTER_VALIDATE_EMAIL ) )
		{
			$this->_SessionManager->flash( 'error', 'Please enter a valid email address.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		try
		{
			// Request password reset
			$this->_ResetManager->requestReset( $Email );

			// Always show success message (don't reveal if email exists)
			$this->_SessionManager->flash(
				'success',
				'If an account exists with that email address, you will receive password reset instructions shortly.'
			);
		}
		catch( Exception $e )
		{
			// Log error but show generic message to user
			Log::error('Password reset request failed: ' . $e->getMessage() );
			$this->_SessionManager->flash(
				'error',
				'Unable to process password reset request. Please try again later.'
			);
		}

		header( 'Location: /forgot-password' );
		exit;
	}

	/**
	 * Show reset password form (with token)
	 */
	public function showResetForm( array $Parameters ): string
	{
		// Get token from query string
		$Token = $_GET['token'] ?? '';

		if( empty( $Token ) )
		{
			$this->_SessionManager->flash( 'error', 'Invalid or missing reset token.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Validate token
		$TokenObj = $this->_ResetManager->validateToken( $Token );

		if( !$TokenObj )
		{
			$this->_SessionManager->flash( 'error', 'This password reset link is invalid or has expired.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		$ViewData = [
			'Title' => 'Reset Password | ' . $this->getName(),
			'Description' => 'Enter your new password',
			'PageSubtitle' => 'Create New Password',
			'CsrfToken' => $this->_CsrfManager->getToken(),
			'Error' => $this->_SessionManager->getFlash( 'error' ),
			'Success' => $this->_SessionManager->getFlash( 'success' ),
			'Token' => $Token,
			'Email' => $TokenObj->getEmail()
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Auth' )
			 ->setLayout( 'auth' )
			 ->setPage( 'reset-password' );

		return $View->render( $ViewData );
	}

	/**
	 * Process password reset
	 */
	public function resetPassword( array $Parameters ): string
	{
		// Validate CSRF token
		$CsrfToken = $_POST['csrf_token'] ?? '';
		if( !$this->_CsrfManager->validate( $CsrfToken ) )
		{
			$this->_SessionManager->flash( 'error', 'Invalid CSRF token. Please try again.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Get form data
		$Token = $_POST['token'] ?? '';
		$Password = $_POST['password'] ?? '';
		$PasswordConfirmation = $_POST['password_confirmation'] ?? '';

		// Validate input
		if( empty( $Token ) || empty( $Password ) || empty( $PasswordConfirmation ) )
		{
			$this->_SessionManager->flash( 'error', 'All fields are required.' );
			header( 'Location: /reset-password?token=' . urlencode( $Token ) );
			exit;
		}

		// Validate passwords match
		if( $Password !== $PasswordConfirmation )
		{
			$this->_SessionManager->flash( 'error', 'Passwords do not match.' );
			header( 'Location: /reset-password?token=' . urlencode( $Token ) );
			exit;
		}

		try
		{
			// Attempt password reset
			$Success = $this->_ResetManager->resetPassword( $Token, $Password );

			if( !$Success )
			{
				$this->_SessionManager->flash( 'error', 'This password reset link is invalid or has expired.' );
				header( 'Location: /forgot-password' );
				exit;
			}

			// Success
			$this->_SessionManager->flash( 'success', 'Your password has been reset successfully. You can now log in.' );
			header( 'Location: /login' );
			exit;
		}
		catch( Exception $e )
		{
			// Show validation or other errors
			$this->_SessionManager->flash( 'error', $e->getMessage() );
			header( 'Location: /reset-password?token=' . urlencode( $Token ) );
			exit;
		}
	}
}
