<?php

namespace Neuron\Cms\Jobs;

use Neuron\Cms\Services\Email\Sender;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Jobs\IJob;
use Neuron\Jobs\Queue\IQueueable;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;

/**
 * Delivers an email that Sender::send() queued.
 *
 * The arguments are Sender::toArray(): a rendered message, so the worker only
 * needs mail settings, not templates or view paths. A delivery failure throws
 * so the queue retries with the configured backoff and, after the last
 * attempt, records the message in failed jobs where it can be retried by hand.
 *
 * Attachments are referenced by path and must still exist when the worker
 * runs; the web and worker hosts need to share that filesystem.
 *
 * @package Neuron\Cms\Jobs
 */
class SendEmailJob implements IJob, IQueueable
{
	private ?SettingManager $_settings;

	/**
	 * @param SettingManager|null $settings Injected by the container when the
	 *        worker booted the application; falls back to the Registry.
	 */
	public function __construct( ?SettingManager $settings = null )
	{
		$this->_settings = $settings;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'send_email';
	}

	/**
	 * @inheritDoc
	 */
	public function run( array $argv = [] ): mixed
	{
		$sender = Sender::fromArray( $argv, $this->getSettings(), $this->getBasePath() );

		if( !$sender->sendNow() )
		{
			throw new \RuntimeException( 'Email delivery failed for ' . $this->describeRecipients( $argv ) );
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getQueue(): ?string
	{
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getMaxAttempts(): ?int
	{
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getBackoff(): ?int
	{
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getTimeout(): ?int
	{
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function failed( \Throwable $exception ): void
	{
		Log::error( 'Email permanently failed: ' . $exception->getMessage() );
	}

	/**
	 * @param array $argv
	 * @return string
	 */
	private function describeRecipients( array $argv ): string
	{
		$emails = array_map( fn( array $recipient ) => $recipient['email'] ?? '?', $argv['to'] ?? [] );

		return $emails ? implode( ', ', $emails ) : 'no recipients';
	}

	/**
	 * @return SettingManager|null
	 */
	private function getSettings(): ?SettingManager
	{
		if( $this->_settings !== null )
		{
			return $this->_settings;
		}

		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		return $settings instanceof SettingManager ? $settings : null;
	}

	/**
	 * @return string
	 */
	private function getBasePath(): string
	{
		$basePath = Registry::getInstance()->get( RegistryKeys::BASE_PATH );

		return $basePath ? rtrim( (string)$basePath, '/' ) : '';
	}
}
