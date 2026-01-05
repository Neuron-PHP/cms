<?php

namespace Neuron\Cms\Controllers\Auth;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Services\Auth\IPasswordResetter;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\Html;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
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
	private IPasswordResetter $_passwordResetter;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPasswordResetter $passwordResetter
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IPasswordResetter $passwordResetter
	)
	{
		parent::__construct( $app, $settings, $sessionManager );
		$this->_passwordResetter = $passwordResetter;
	}

	/**
	 * Show forgot password form
	 *
	 * @param Request $request
	 * @return string
	 */
	#[Get('/forgot-password', name: 'forgot_password')]
	public function showForgotPasswordForm( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Forgot Password' )
			->description( 'Reset your password' )
			->withCsrfToken()
			->with( 'PageSubtitle', 'Reset Password' )
			->with( FlashMessageType::ERROR->value, $this->_sessionManager->getFlash( 'error' ) )
			->with( FlashMessageType::SUCCESS->value, $this->_sessionManager->getFlash( 'success' ) )
			->render( 'forgot-password', 'auth' );
	}

	/**
	 * Process forgot password request
	 *
	 * @param Request $request
	 * @return string
	 */
	#[Post('/forgot-password', name: 'forgot_password_post', filters: ['csrf'])]
	public function requestReset( Request $request ): string
	{
		// Create and validate DTO
		$dto = $this->createDto( 'auth/forgot-password-request.yaml' );
		$this->mapRequestToDto( $dto, $request );

		// Validate DTO
		if( !$dto->validate() )
		{
			$errors = implode( ', ', $dto->getErrors() );
			$this->redirect( 'forgot_password', [], [FlashMessageType::ERROR->value, $errors] );
		}

		try
		{
			// Request password reset
			$this->_passwordResetter->requestReset( $dto->email );

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
	#[Get('/reset-password', name: 'reset_password')]
	public function showResetForm( Request $request ): string
	{
		// Get token from query string
		$token = $request->get( 'token', '' );

		if( empty( $token ) )
		{
			$this->_sessionManager->flash( FlashMessageType::ERROR->value,'Invalid or missing reset token.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		// Validate token
		$tokenObj = $this->_passwordResetter->validateToken( $token );

		if( !$tokenObj )
		{
			$this->_sessionManager->flash( FlashMessageType::ERROR->value,'This password reset link is invalid or has expired.' );
			header( 'Location: /forgot-password' );
			exit;
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Reset Password' )
			->description( 'Enter your new password' )
			->withCsrfToken()
			->with( 'PageSubtitle', 'Create New Password' )
			->with( FlashMessageType::ERROR->value, $this->_sessionManager->getFlash( 'error' ) )
			->with( FlashMessageType::SUCCESS->value, $this->_sessionManager->getFlash( 'success' ) )
			->with( 'Token', $token )
			->with( 'Email', $tokenObj->getEmail() )
			->render( 'reset-password', 'auth' );
	}

	/**
	 * Process password reset
	 * @param Request $request
	 * @return string
	 */
	#[Post('/reset-password', name: 'reset_password_post', filters: ['csrf'])]
	public function resetPassword( Request $request ): string
	{
		// Create and validate DTO
		$dto = $this->createDto( 'auth/reset-password-request.yaml' );
		$this->mapRequestToDto( $dto, $request );

		// Validate DTO
		if( !$dto->validate() )
		{
			$errors = implode( ', ', $dto->getErrors() );
			$this->redirectToUrl( '/reset-password?token=' . urlencode( $dto->token ?? '' ), [FlashMessageType::ERROR->value, $errors] );
		}

		// Validate passwords match
		if( $dto->password !== $dto->password_confirmation )
		{
			$this->redirectToUrl( '/reset-password?token=' . urlencode( $dto->token ), [FlashMessageType::ERROR->value, 'Passwords do not match.'] );
		}

		try
		{
			// Attempt password reset
			$success = $this->_passwordResetter->resetPassword( $dto->token, $dto->password );

			if( !$success )
			{
				$this->redirect( 'forgot_password', [], [FlashMessageType::ERROR->value, 'This password reset link is invalid or has expired.'] );
			}

			// Success
			$this->redirect( 'login', [], [FlashMessageType::SUCCESS->value, 'Your password has been reset successfully. You can now log in.'] );
		}
		catch( Exception $e )
		{
			$this->redirectToUrl( '/reset-password?token=' . urlencode( $dto->token ), [FlashMessageType::ERROR->value, $e->getMessage() ] );
		}
	}
}
