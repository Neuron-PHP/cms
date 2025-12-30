<?php

namespace Neuron\Cms\Listeners;

use Neuron\Events\IListener;
use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Sends a welcome email to newly created users.
 *
 * Uses the Email\Sender service with editable template from
 * resources/views/emails/welcome.php
 *
 * @package Neuron\Cms\Listeners
 */
class SendWelcomeEmailListener implements IListener
{
	private SettingManager $settings;
	private string $basePath;

	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Application settings
	 * @param string $basePath Base application path for templates
	 */
	public function __construct( SettingManager $settings, string $basePath )
	{
		$this->settings = $settings;
		$this->basePath = $basePath;
	}

	/**
	 * Handle the user.created event
	 *
	 * @param UserCreatedEvent $event
	 * @return void
	 */
	public function event( $event ): void
	{
		if( !$event instanceof UserCreatedEvent )
		{
			return;
		}

		$user = $event->user;

		$siteName = $this->settings->get( 'site', 'name' ) ?? 'Neuron CMS';
		$siteUrl = $this->settings->get( 'site', 'url' ) ?? 'http://localhost';

		// Prepare template data
		$templateData = [
			'Username' => $user->getUsername(),
			'SiteName' => $siteName,
			'SiteUrl' => $siteUrl
		];

		// Send email using Sender service with template
		try
		{
			$sender = new Sender( $this->settings, $this->basePath );
			$result = $sender
				->to( $user->getEmail(), $user->getUsername() )
				->subject( "Welcome to {$siteName}!" )
				->template( 'emails/welcome', $templateData )
				->send();

			if( $result )
			{
				Log::info( "Welcome email sent to: {$user->getEmail()}" );
			}
			else
			{
				Log::warning( "Failed to send welcome email to: {$user->getEmail()}" );
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Error sending welcome email to {$user->getEmail()}: " . $e->getMessage() );
		}
	}
}
