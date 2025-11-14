<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\PasswordResetManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
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
class PasswordReset extends Content
{
	private ?PasswordResetManager $_resetManager;
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

		// Initialize CSRF manager with parent's session manager
		$this->_csrfManager = new CsrfTokenManager( $this->getSessionManager() );
	}

	/**
	 * Show forgot password form
	 *
	 * @param Request $request
	 * @return string
	 */
	public function showForgotPasswordForm( Request $request ): string
	{
		// Set CSRF token in Registry so csrf_field() helper works
		Registry::getInstance()->set( 'Auth.CsrfToken', $this->_csrfManager->getToken() );

		$viewData = [
			'Title' => 'Forgot Password | ' . $this->getName(),
			'Description' => 'Reset your password',
			'PageSubtitle' => 'Reset Password',
			'Error' => $this->_sessionManager->getFlash( 'error' ),
			'Success' => $this->_sessionManager->getFlash( 'success' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'forgot-password',
			'auth'
		);
	}

	/**
	 * Process forgot password request
	 *
	 * @param array $parameters
	 * @return string
	 */
	public function requestReset( Request $request ): string
	{
		// Validate CSRF token
		$token = $request->post( 'csrf_token', '' );
		if( !$this->_csrfManager->validate( $token ) )
		{
			$this->_sessionManager->flash( 'error', 'Invalid CSRF token. Please try again.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Get email
		$email = $request->post( 'email', '' );

		// Validate input
		if( empty( $email ) || !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			$this->redirect( 'admin_posts', [], ['error', 'Please enter a valid email address.'] );
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
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	public function showResetForm( Request $request ): string
	{
		// Get token from query string
		$token = $request->get( 'token', '' );

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

		// Set CSRF token in Registry so csrf_field() helper works
		Registry::getInstance()->set( 'Auth.CsrfToken', $this->_csrfManager->getToken() );

		$viewData = [
			'Title' => 'Reset Password | ' . $this->getName(),
			'Description' => 'Enter your new password',
			'PageSubtitle' => 'Create New Password',
			'Error' => $this->_sessionManager->getFlash( 'error' ),
			'Success' => $this->_sessionManager->getFlash( 'success' ),
			'Token' => $token,
			'Email' => $tokenObj->getEmail()
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'reset-password',
			'auth'
		);
	}

	/**
	 * Process password reset
	 * @param Request $request
	 * @return string
	 */
	public function resetPassword( Request $request ): string
	{
		// Validate CSRF token
		$csrfToken = $request->post( 'csrf_token', '' );
		if( !$this->_csrfManager->validate( $csrfToken ) )
		{
			$this->redirect( 'admin_posts', [], ['error', 'Invalid CSRF token.'] );
		}

		// Get form data
		$token = $request->post( 'token', '' );
		$password = $request->post( 'password', '' );
		$passwordConfirmation = $request->post( 'password_confirmation', '' );

		// Validate input
		if( empty( $token ) || empty( $password ) || empty( $passwordConfirmation ) )
		{
			$this->redirectToUrl( '/reset-password?token=' . urlencode( $token ), ['error', 'All fields are required.'] );
		}

		// Validate passwords match
		if( $password !== $passwordConfirmation )
		{
			$this->redirectToUrl( '/reset-password?token=' . urlencode( $token ), ['error', 'Passwords do not match.'] );
		}

		try
		{
			// Attempt password reset
			$success = $this->_resetManager->resetPassword( $token, $password );

			if( !$success )
			{
				$this->redirect( 'forgot_password', [], ['error', 'This password reset link is invalid or has expired.'] );
			}

			// Success
			$this->redirect( 'login', [], ['success', 'Your password has been reset successfully. You can now log in.'] );
		}
		catch( Exception $e )
		{
			$this->redirectToUrl( '/reset-password?token=' . urlencode( $token ), ['error', $e->getMessage() ] );
		}
	}
}
