<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Controllers\Traits\UsesDtos;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Auth\EmailVerifier;
use Neuron\Cms\Auth\ResendVerificationThrottle;
use Neuron\Cms\Services\Member\RegistrationService;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use Neuron\Routing\DefaultIpResolver;
use Neuron\Routing\IIpResolver;
use Exception;

/**
 * Member registration controller.
 *
 * Handles user registration and email verification.
 *
 * @package Neuron\Cms\Controllers\Member
 */
class Registration extends Content
{
	use UsesDtos;
	private RegistrationService $_registrationService;
	private EmailVerifier $_emailVerifier;
	private CsrfToken $_csrfToken;
	private ResendVerificationThrottle $_resendThrottle;
	private IIpResolver $_ipResolver;

	/**
	 * @param Application|null $app
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get services from Registry
		$this->_registrationService = Registry::getInstance()->get( 'RegistrationService' );
		$this->_emailVerifier = Registry::getInstance()->get( 'EmailVerifier' );

		if( !$this->_registrationService || !$this->_emailVerifier )
		{
			throw new \RuntimeException( 'Registration services not found in Registry.' );
		}

		// Initialize CSRF manager
		$this->_csrfToken = new CsrfToken( $this->getSessionManager() );

		// Initialize resend verification throttle
		$this->_resendThrottle = new ResendVerificationThrottle();

		// Initialize IP resolver
		$this->_ipResolver = new DefaultIpResolver();
	}

	/**
	 * Show the registration form
	 *
	 * @param Request $request
	 * @return string
	 */
	public function showRegistrationForm( Request $request ): string
	{
		// Check if registration is enabled
		if( !$this->_registrationService->isRegistrationEnabled() )
		{
			$viewData = [
				'Title' => 'Registration Disabled | ' . $this->getName(),
				'Description' => 'User registration is currently disabled'
			];

			return $this->renderHtml(
				HttpResponseStatus::OK,
				$viewData,
				'registration-disabled',
				'member'
			);
		}

		// Set CSRF token in Registry
		Registry::getInstance()->set( 'Auth.CsrfToken', $this->_csrfToken->getToken() );

		$viewData = [
			'Title' => 'Register | ' . $this->getName(),
			'Description' => 'Create your account',
			'Error' => $this->getSessionManager()->getFlash( 'error' ),
			'Success' => $this->getSessionManager()->getFlash( 'success' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'register',
			'member'
		);
	}

	/**
	 * Process registration
	 * @param Request $request
	 * @return never
	 */
	public function processRegistration( Request $request ): never
	{
		// Validate CSRF token - defensively handle null/non-string values
		$tokenRaw = $request->post( 'csrf_token' );

		if( $tokenRaw === null || $tokenRaw === '' )
		{
			$this->redirect( 'register', [], ['error', 'Invalid CSRF token. Please try again.'] );
		}

		$token = (string)$tokenRaw;

		if( !$this->_csrfToken->validate( $token ) )
		{
			$this->redirect( 'register', [], ['error', 'Invalid CSRF token. Please try again.'] );
		}

		try
		{
			// Create and populate RegisterUser DTO from request
			$dto = $this->createDtoFromRequest( 'RegisterUser', $request );

			// Validate the DTO
			$this->validateDtoOrFail( $dto );

			// Register user using DTO
			$this->_registrationService->registerWithDto( $dto );
			// Check if verification is required
			$settings = Registry::getInstance()->get( 'Settings' );
			$requireVerification = $settings->get( 'member', 'require_email_verification' ) ?? true;

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
	public function showVerificationSent( Request $request ): string
	{
		$viewData = [
			'Title' => 'Verify Your Email | ' . $this->getName(),
			'Description' => 'Please check your email',
			'Success' => $this->getSessionManager()->getFlash( 'success' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'verify-email-sent',
			'member'
		);
	}

	/**
	 * Verify email address
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	public function verify( Request $request ): string
	{
		// Get token from query string
		$token = $request->get( 'token' ) ?? '';

		if( empty( $token ) )
		{
			$viewData = [
				'Title' => 'Email Verification | ' . $this->getName(),
				'Description' => 'Email verification',
				'Success' => false,
				'Message' => 'Invalid or missing verification token.'
			];

			return $this->renderHtml(
				HttpResponseStatus::BAD_REQUEST,
				$viewData,
				'email-verified',
				'member'
			);
		}

		try
		{
			// Verify the token
			$success = $this->_emailVerifier->verifyEmail( $token );

			if( $success )
			{
				$viewData = [
					'Title' => 'Email Verified | ' . $this->getName(),
					'Description' => 'Your email has been verified',
					'Success' => true,
					'Message' => 'Your email has been verified successfully! You can now log in.'
				];

				return $this->renderHtml(
					HttpResponseStatus::OK,
					$viewData,
					'email-verified',
					'member'
				);
			}
			else
			{
				$viewData = [
					'Title' => 'Email Verification | ' . $this->getName(),
					'Description' => 'Email verification failed',
					'Success' => false,
					'Message' => 'This verification link is invalid or has expired.'
				];

				return $this->renderHtml(
					HttpResponseStatus::BAD_REQUEST,
					$viewData,
					'email-verified',
					'member'
				);
			}
		}
		catch( Exception $e )
		{
			$viewData = [
				'Title' => 'Email Verification | ' . $this->getName(),
				'Description' => 'Email verification error',
				'Success' => false,
				'Message' => 'An error occurred during email verification. Please try again later.'
			];

			return $this->renderHtml(
				HttpResponseStatus::INTERNAL_SERVER_ERROR,
				$viewData,
				'email-verified',
				'member'
			);
		}
	}

	/**
	 * Resend verification email
	 *
	 * @param Request $request
	 * @return never
	 */
	public function resendVerification( Request $request ): never
	{
		// Validate CSRF token - defensively handle null/non-string values
		$tokenRaw = $request->post( 'csrf_token' );

		if( $tokenRaw === null || $tokenRaw === '' )
		{
			$this->redirect( 'register', [], ['error', 'Invalid CSRF token. Please try again.'] );
		}

		$token = (string)$tokenRaw;

		if( !$this->_csrfToken->validate( $token ) )
		{
			$this->redirect( 'register', [], ['error', 'Invalid CSRF token. Please try again.'] );
		}

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
