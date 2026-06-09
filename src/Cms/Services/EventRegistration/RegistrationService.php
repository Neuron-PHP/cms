<?php

namespace Neuron\Cms\Services\EventRegistration;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventRegistration;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Handles notifications for event registrations.
 *
 * Persistence is owned by the controller (persist-first, so a registration is
 * never lost on email failure); this service resolves the admin recipient from
 * configuration and delivers the admin notification plus an optional registrant
 * confirmation using the CMS email Sender.
 *
 * Configuration lives under the `events.registration` section:
 *   notify_email           recipient for admin notifications (falls back to email.from_address)
 *   confirmation_enabled   send a confirmation email to the registrant (default: false)
 *   success_message        thank-you message shown after registering
 *
 * @package Neuron\Cms\Services\EventRegistration
 */
class RegistrationService
{
	private SettingManager $_settings;
	private ?Sender $_sender;
	private string $_basePath;

	/**
	 * @param SettingManager $settings
	 * @param Sender|null $sender Optional Sender (injectable for testing)
	 * @param string|null $basePath Base path for email template resolution
	 */
	public function __construct( SettingManager $settings, ?Sender $sender = null, ?string $basePath = null )
	{
		$this->_settings = $settings;
		$this->_sender   = $sender;
		$this->_basePath = $basePath
			?? ( $this->getRegistrationSetting( 'base_path' ) ?: ( $settings->get( 'system', 'base_path' ) ?: getcwd() ) );
	}

	/**
	 * Resolve the admin notification recipient.
	 *
	 * @return string|null Null when no recipient can be determined
	 */
	public function getNotifyEmail(): ?string
	{
		$recipient = $this->getRegistrationSetting( 'notify_email' );

		if( empty( $recipient ) )
		{
			$recipient = $this->_settings->get( 'email', 'from_address' )
				?? $this->_settings->get( 'mail', 'from_address' );
		}

		return !empty( $recipient ) ? (string)$recipient : null;
	}

	/**
	 * Whether a confirmation email should be sent to the registrant.
	 *
	 * @return bool
	 */
	public function isConfirmationEnabled(): bool
	{
		return (bool)$this->getRegistrationSetting( 'confirmation_enabled' );
	}

	/**
	 * The thank-you message shown after a successful registration.
	 *
	 * @return string
	 */
	public function getSuccessMessage(): string
	{
		$message = $this->getRegistrationSetting( 'success_message' );

		return !empty( $message )
			? (string)$message
			: 'Thank you for registering. We look forward to seeing you!';
	}

	/**
	 * Notify the configured admin recipient of a new registration.
	 *
	 * @param EventRegistration $registration
	 * @param Event $event
	 * @return bool True when the email was sent (or logged in test/log mode)
	 */
	public function notifyAdmin( EventRegistration $registration, Event $event ): bool
	{
		$recipient = $this->getNotifyEmail();

		if( $recipient === null )
		{
			Log::warning( 'RegistrationService: no admin notification recipient configured' );
			return false;
		}

		$sender  = $this->_sender ?? new Sender( $this->_settings, $this->_basePath );
		$subject = 'New Event Registration: ' . $event->getTitle();

		try
		{
			$sender->to( $recipient );
			$sender->replyTo( $registration->getEmail(), $registration->getName() );
			$sender->subject( $subject );

			$templateData = [
				'registration' => $registration,
				'event'        => $event
			];

			try
			{
				$sender->template( 'emails/event-registration-admin', $templateData );
			}
			catch( \Throwable $templateError )
			{
				Log::warning( 'RegistrationService: admin template render failed, using plain body: ' . $templateError->getMessage() );
				$sender->body( $this->buildAdminPlainBody( $registration, $event ), false );
			}

			return $sender->send();
		}
		catch( \Throwable $e )
		{
			Log::error( 'RegistrationService: failed to notify admin for event ' . $event->getId() . ': ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Send a confirmation email to the registrant when enabled.
	 *
	 * @param EventRegistration $registration
	 * @param Event $event
	 * @return bool True when sent (or skipped because confirmations are disabled)
	 */
	public function sendConfirmation( EventRegistration $registration, Event $event ): bool
	{
		if( !$this->isConfirmationEnabled() )
		{
			return true;
		}

		if( empty( $registration->getEmail() ) )
		{
			return false;
		}

		// A fresh Sender per message: recipients/state are not reset between sends.
		$sender  = $this->_sender ?? new Sender( $this->_settings, $this->_basePath );
		$subject = 'Registration Confirmed: ' . $event->getTitle();

		try
		{
			$sender->to( $registration->getEmail(), $registration->getName() );
			$sender->subject( $subject );

			$templateData = [
				'registration' => $registration,
				'event'        => $event
			];

			try
			{
				$sender->template( 'emails/event-registration-confirmation', $templateData );
			}
			catch( \Throwable $templateError )
			{
				Log::warning( 'RegistrationService: confirmation template render failed, using plain body: ' . $templateError->getMessage() );
				$sender->body( $this->buildConfirmationPlainBody( $registration, $event ), false );
			}

			return $sender->send();
		}
		catch( \Throwable $e )
		{
			Log::error( 'RegistrationService: failed to send confirmation for event ' . $event->getId() . ': ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Read a value from the events.registration config section.
	 *
	 * @param string $key
	 * @return mixed
	 */
	private function getRegistrationSetting( string $key ): mixed
	{
		$registration = $this->_settings->get( 'events', 'registration' );

		if( is_array( $registration ) && array_key_exists( $key, $registration ) )
		{
			return $registration[ $key ];
		}

		return null;
	}

	/**
	 * Plain-text admin body fallback.
	 *
	 * @param EventRegistration $registration
	 * @param Event $event
	 * @return string
	 */
	private function buildAdminPlainBody( EventRegistration $registration, Event $event ): string
	{
		$lines = [
			'New registration for: ' . $event->getTitle(),
			'Date: ' . $event->getStartDate()->format( 'l, F j, Y g:i A' ),
			'',
			'Name: ' . $registration->getName(),
			'Email: ' . $registration->getEmail()
		];

		if( $registration->getNotes() )
		{
			$lines[] = 'Notes: ' . $registration->getNotes();
		}

		return implode( "\n", $lines );
	}

	/**
	 * Plain-text confirmation body fallback.
	 *
	 * @param EventRegistration $registration
	 * @param Event $event
	 * @return string
	 */
	private function buildConfirmationPlainBody( EventRegistration $registration, Event $event ): string
	{
		return implode( "\n", [
			'Hi ' . $registration->getName() . ',',
			'',
			'Your registration for "' . $event->getTitle() . '" is confirmed.',
			'Date: ' . $event->getStartDate()->format( 'l, F j, Y g:i A' ),
			$event->getLocation() ? 'Location: ' . $event->getLocation() : '',
			'',
			'Thank you!'
		] );
	}
}
