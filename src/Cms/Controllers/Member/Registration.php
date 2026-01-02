<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Services\Security\ResendVerificationThrottle;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Cms\Services\Auth\IEmailVerifier;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\DefaultIpResolver;
use Neuron\Routing\IIpResolver;
use Exception;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

/**
 * Member registration controller.
 *
 * Handles user registration and email verification.
 *
 * @package Neuron\Cms\Controllers\Member
 */
class Registration extends Content
{
	private IRegistrationService $_registrationService;
	private IEmailVerifier $_emailVerifier;
	private ResendVerificationThrottle $_resendThrottle;
	private IIpResolver $_ipResolver;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IRegistrationService|null $registrationService
	 * @param IEmailVerifier|null $emailVerifier
	 * @param ResendVerificationThrottle|null $resendThrottle
	 * @param IIpResolver|null $ipResolver
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		?IRegistrationService $registrationService = null,
		?IEmailVerifier $emailVerifier = null,
		?ResendVerificationThrottle $resendThrottle = null,
		?IIpResolver $ipResolver = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		if( $registrationService === null )
		{
			throw new \InvalidArgumentException( 'IRegistrationService must be injected' );
		}
		$this->_registrationService = $registrationService;

		if( $emailVerifier === null )
		{
			throw new \InvalidArgumentException( 'IEmailVerifier must be injected' );
		}
		$this->_emailVerifier = $emailVerifier;

		if( $resendThrottle === null )
		{
			throw new \InvalidArgumentException( 'ResendVerificationThrottle must be injected' );
		}
		$this->_resendThrottle = $resendThrottle;

		if( $ipResolver === null )
		{
			throw new \InvalidArgumentException( 'IIpResolver must be injected' );
		}
		$this->_ipResolver = $ipResolver;
	}

	/**
	 * Show the registration form
	 *
	 * @param Request $request
	 * @return string
	 */
	#[Get('/register', name: 'register')]
	public function showRegistrationForm( Request $request ): string
	{
		// Check if registration is enabled
		if( !$this->_registrationService->isRegistrationEnabled() )
		{
			return $this->view()
				->title( 'Registration Disabled' )
				->description( 'User registration is currently disabled' )
				->render( 'registration-disabled', 'member' );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Register' )
			->description( 'Create your account' )
			->withCsrfToken()
			->with( 'Error', $this->getSessionManager()->getFlash( 'error' ) )
			->with( 'Success', $this->getSessionManager()->getFlash( 'success' ) )
			->render( 'register', 'member' );
	}

	/**
	 * Process registration
	 * @param Request $request
	 * @return never
	 */
	#[Post('/register', name: 'register_post', filters: ['csrf'])]
	public function processRegistration( Request $request ): never
	{
		try
		{
			// Create and populate RegisterUser DTO from request
			$dto = $this->createDto( 'users/register-user-request.yaml' );
			$this->mapRequestToDto( $dto, $request );

			// Validate the DTO
			if( !$dto->validate() )
			{
				$errors = [];
				foreach( $dto->getErrors() as $field => $fieldErrors )
				{
					foreach( $fieldErrors as $error )
					{
						$errors[] = $field . ': ' . $error;
					}
				}
				$errorMessage = implode( ', ', $errors );
				throw new \Exception( $errorMessage );
			}

			// Register user using DTO
			$this->_registrationService->registerWithDto( $dto );
			// Check if verification is required
			$requireVerification = $this->_settings->get( 'member', 'require_email_verification' ) ?? true;

			if( $requireVerification )
			{
				// Redirect to verification sent page
				$this->redirect( 'verify_email_sent', [], ['success', 'Registration successful! Please check your email to verify your account.'] );
			}
			else
			{
				// Redirect to login
				$this->redirect( 'login', [], ['success', 'Registration successful! You can now log in.'] );
			}
		}
		catch( Exception $e )
		{
			// Redirect back with error
			$this->redirect( 'register', [], ['error', $e->getMessage()] );
		}
	}

	/**
	 * Show verification email sent page
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/verify-email-sent', name: 'verify_email_sent')]
	public function showVerificationSent( Request $request ): string
	{
		return $this->view()
			->title( 'Verify Your Email' )
			->description( 'Please check your email' )
			->with( 'Success', $this->getSessionManager()->getFlash( 'success' ) )
			->render( 'verify-email-sent', 'member' );
	}

	/**
	 * Verify email address
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/verify-email', name: 'verify_email')]
	public function verify( Request $request ): string
	{
		// Get token from query string
		$token = $request->get( 'token' ) ?? '';

		if( empty( $token ) )
		{
			return $this->view()
				->title( 'Email Verification' )
				->description( 'Email verification' )
				->with( 'Success', false )
				->with( 'Message', 'Invalid or missing verification token.' )
				->render( 'email-verified', 'member', HttpResponseStatus::BAD_REQUEST );
		}

		try
		{
			// Verify the token
			$success = $this->_emailVerifier->verifyEmail( $token );

			if( $success )
			{
				return $this->view()
					->title( 'Email Verified' )
					->description( 'Your email has been verified' )
					->with( 'Success', true )
					->with( 'Message', 'Your email has been verified successfully! You can now log in.' )
					->render( 'email-verified', 'member' );
			}
			else
			{
				return $this->view()
					->title( 'Email Verification' )
					->description( 'Email verification failed' )
					->with( 'Success', false )
					->with( 'Message', 'This verification link is invalid or has expired.' )
					->render( 'email-verified', 'member', HttpResponseStatus::BAD_REQUEST );
			}
		}
		catch( Exception $e )
		{
			return $this->view()
				->title( 'Email Verification' )
				->description( 'Email verification error' )
				->with( 'Success', false )
				->with( 'Message', 'An error occurred during email verification. Please try again later.' )
				->render( 'email-verified', 'member', HttpResponseStatus::INTERNAL_SERVER_ERROR );
		}
	}

	/**
	 * Resend verification email
	 *
	 * @param Request $request
	 * @return never
	 */
	#[Post('/resend-verification', name: 'resend_verification', filters: ['csrf'])]
	public function resendVerification( Request $request ): never
	{
		// Get email and client IP
		$email = $request->post( 'email' ) ?? '';
		$clientIp = $this->_ipResolver->resolve( $_SERVER );

		if( empty( $email ) )
		{
			$this->redirect( 'register', [], ['error', 'Email address is required.'] );
		}

		// Check rate limits (combined IP and email throttling)
		if( !$this->_resendThrottle->allow( $clientIp, $email ) )
		{
			// Rate limit exceeded - return generic success to prevent enumeration
			// This prevents attackers from using rate limiting to determine if an email exists
			$this->redirect( 'verify_email_sent', [], ['success', 'If an account exists with that email, a verification email has been sent.'] );
		}

		try
		{
			// Resend verification email
			$this->_emailVerifier->resendVerification( $email );

			// Always show success message (don't reveal if email exists)
			$this->redirect( 'verify_email_sent', [], ['success', 'If an account exists with that email, a verification email has been sent.'] );
		}
		catch( Exception $e )
		{
			// Don't reveal specific error details to prevent enumeration
			// Log the error internally but show generic message to user
			$this->redirect( 'verify_email_sent', [], ['success', 'If an account exists with that email, a verification email has been sent.'] );
		}
	}
}
