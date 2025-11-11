<?php

namespace Neuron\Cms\Listeners;

use Neuron\Events\IListener;
use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;

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

		// Get site settings from Registry
		$settings = Registry::getInstance()->get( 'Settings' );

		if( !$settings )
		{
			Log::debug( "Settings not available - welcome email skipped for: {$user->getEmail()}" );
			return;
		}

		$siteName = $settings->get( 'site', 'name' ) ?? 'Neuron CMS';
		$siteUrl = $settings->get( 'site', 'url' ) ?? 'http://localhost';
		$basePath = Registry::getInstance()->get( 'Base.Path' ) ?? getcwd();

		// Prepare template data
		$templateData = [
			'Username' => $user->getUsername(),
			'SiteName' => $siteName,
			'SiteUrl' => $siteUrl
		];

		// Send email using Sender service with template
		try
		{
			$sender = new Sender( $settings, $basePath );
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
