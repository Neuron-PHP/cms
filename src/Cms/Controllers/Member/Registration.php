<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Cms\Auth\EmailVerificationManager;
use Neuron\Cms\Auth\ResendVerificationThrottle;
use Neuron\Cms\Services\Member\RegistrationService;
use Neuron\Data\Filter\Get;
use Neuron\Data\Filter\Post;
use Neuron\Mvc\Application;
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
	private RegistrationService $_registrationService;
	private EmailVerificationManager $_verificationManager;
	private CsrfTokenManager $_csrfManager;
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
		$this->_verificationManager = Registry::getInstance()->get( 'EmailVerificationManager' );

		if( !$this->_registrationService || !$this->_verificationManager )
		{
			throw new \RuntimeException( 'Registration services not found in Registry.' );
		}

		// Initialize CSRF manager
		$this->_csrfManager = new CsrfTokenManager( $this->getSessionManager() );

		// Initialize resend verification throttle
		$this->_resendThrottle = new ResendVerificationThrottle();

		// Initialize IP resolver
		$this->_ipResolver = new DefaultIpResolver();
	}

	/**
	 * Show registration form
	 * @param array $parameters
	 * @return string
	 */
	public function showRegistrationForm( array $parameters ): string
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
		Registry::getInstance()->set( 'Auth.CsrfToken', $this->_csrfManager->getToken() );

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
	 * @param array $parameters
	 * @return never
	 */
	public function processRegistration( array $parameters ): never
	{
		$post = new Post();

		// Validate CSRF token
		$token = $post->filterScalar( 'csrf_token' );

		if( !$this->_csrfManager->validate( $token ) )
		{
			$this->redirect( 'register', [], ['error', 'Invalid CSRF token. Please try again.'] );
		}

		// Get form data
		$username = $post->filterScalar( 'username' ) ?? '';
		$email = $post->filterScalar( 'email' ) ?? '';
		$password = $post->filterScalar( 'password' ) ?? '';
		$passwordConfirmation = $post->filterScalar( 'password_confirmation' ) ?? '';

		try
		{
			// Register user
			$user = $this->_registrationService->register(
				$username,
				$email,
				$password,
				$passwordConfirmation
			);

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
	 * @param array $parameters
	 * @return string
	 */
	public function showVerificationSent( array $parameters ): string
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
	 * @param array $parameters
	 * @return string
	 */
	public function verify( array $parameters ): string
	{
		$get = new Get();

		// Get token from query string
		$token = $get->filterScalar( 'token' ) ?? '';

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
			$success = $this->_verificationManager->verifyEmail( $token );

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
	 * @param array $parameters
	 * @return never
	 */
	public function resendVerification( array $parameters ): never
	{
		$post = new Post();

		// Validate CSRF token
		$token = $post->filterScalar( 'csrf_token' );

		if( !$this->_csrfManager->validate( $token ) )
		{
			$this->redirect( 'register', [], ['error', 'Invalid CSRF token. Please try again.'] );
		}

		// Get email and client IP
		$email = $post->filterScalar( 'email' ) ?? '';
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
			$this->_verificationManager->resendVerification( $email );

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
