<?php

namespace Neuron\Cms\Cli\Commands\Email;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;

/**
 * CLI command for sending a test email.
 *
 * Uses the same Sender service and mail settings as the application's email
 * features (contact forms, receipts, notifications), so a successful test
 * verifies the full production sending path.
 */
class TestCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:email:test';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Send a test email using the configured mail settings';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addArgument( 'recipient', true, 'Recipient email address' );
		$this->addOption( 'subject', 's', true, 'Email subject', 'Neuron CMS test email' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$recipient = (string) $this->input->getArgument( 'recipient', '' );

		if( !filter_var( $recipient, FILTER_VALIDATE_EMAIL ) )
		{
			$this->output->error( "Invalid recipient email address: {$recipient}" );
			return 1;
		}

		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		if( !$settings instanceof SettingManager )
		{
			$this->output->error( "Application not initialized: Settings not found in Registry" );
			return 1;
		}

		$driver = $settings->get( 'mail', 'driver' ) ?? $settings->get( 'email', 'driver' ) ?? 'mail';
		$host = $settings->get( 'mail', 'host' ) ?? $settings->get( 'email', 'host' ) ?? '';
		$from = $settings->get( 'mail', 'from_address' ) ?? $settings->get( 'email', 'from_address' ) ?? 'noreply@example.com';
		$testMode = (bool) $settings->get( 'email', 'test_mode' );

		$this->output->title( 'Email Test' );
		$this->output->info( "Driver: {$driver}" . ( $driver === 'smtp' && $host ? " ({$host})" : '' ) );
		$this->output->info( "From:   {$from}" );
		$this->output->info( "To:     {$recipient}" );

		if( $driver === 'log' || $testMode )
		{
			$reason = $testMode ? 'email.test_mode is enabled' : "mail.driver is 'log'";
			$this->output->warning( "Note: {$reason}; the email will be written to the log instead of being sent." );
		}

		$subject = (string) $this->input->getOption( 'subject', 'Neuron CMS test email' );
		$siteName = $settings->get( 'site', 'name' ) ?? 'Neuron CMS';
		$timestamp = date( 'Y-m-d H:i:s T' );

		$body = "<p>This is a test email from <strong>" . htmlspecialchars( (string) $siteName ) . "</strong>.</p>"
			. "<p>Sent at {$timestamp} via the '{$driver}' driver.</p>"
			. "<p>If you received this, outbound email is configured correctly.</p>";

		$sender = $this->createSender( $settings );

		$result = $sender
			->to( $recipient )
			->subject( $subject )
			->body( $body )
			->send();

		if( !$result )
		{
			$this->output->error( "Send failed. Check the application log for details (look for 'Email send failed')." );
			return 1;
		}

		if( $driver === 'log' || $testMode )
		{
			$this->output->success( "Email logged (not sent). Check the application log for the message." );
		}
		else
		{
			$this->output->success( "Email sent to {$recipient}. Check the inbox (and spam folder)." );
		}

		return 0;
	}

	/**
	 * Build the email sender. Extracted for testability.
	 *
	 * @param SettingManager $settings
	 * @return Sender
	 */
	protected function createSender( SettingManager $settings ): Sender
	{
		return new Sender( $settings );
	}
}
