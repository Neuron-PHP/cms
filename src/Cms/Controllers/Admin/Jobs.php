<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Jobs\Queue\DatabaseQueue;
use Neuron\Jobs\Queue\QueueManager;
use Neuron\Jobs\Schedule\DatabaseScheduleLock;
use Neuron\Jobs\Schedule\ScheduleEntry;
use Neuron\Jobs\Schedule\ScheduleFile;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Scheduled jobs controller.
 *
 * Displays the scheduled jobs defined in the application's
 * config/schedule.yaml file, with the last time the scheduler claimed each
 * one when a database schedule lock is in use. Read-only.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['admin'])]
class Jobs extends Content
{
	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @return void
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager
	)
	{
		parent::__construct( $app, $settings, $sessionManager );
	}

	/**
	 * List all scheduled jobs from config/schedule.yaml
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/jobs', name: 'admin_jobs')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$schedule   = $this->schedule();
		$fileExists = $schedule !== null && $schedule->exists();
		$lastClaims = $fileExists ? $this->lastClaims() : null;

		$jobs = [];

		if( $fileExists )
		{
			foreach( $schedule->entries() as $entry )
			{
				$jobs[] = $this->present( $entry, $lastClaims );
			}
		}

		return $this->view()
			->title( 'Scheduled Jobs' )
			->description( 'Scheduled Jobs' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'jobs' => $jobs,
				'scheduleFileExists' => $fileExists,
				'lastTriggeredAvailable' => $lastClaims !== null,
				FlashMessageType::SUCCESS->viewKey() => $this->getSessionManager()->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey() => $this->getSessionManager()->getFlash( FlashMessageType::ERROR->value )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Row data for one schedule entry.
	 *
	 * @param ScheduleEntry $entry
	 * @param array<string,int>|null $lastClaims
	 * @return array
	 */
	private function present( ScheduleEntry $entry, ?array $lastClaims ): array
	{
		$row = $entry->toArray();

		$claimed = $lastClaims[ $entry->getName() ] ?? null;

		$row['lastTriggered'] = $claimed !== null ? date( 'Y-m-d H:i:s', $claimed ) : null;
		$row['lastTriggeredAt'] = $claimed;

		return $row;
	}

	/**
	 * The application's schedule file, or null when no base path is known.
	 *
	 * @return ScheduleFile|null
	 */
	private function schedule(): ?ScheduleFile
	{
		$basePath = $this->resolveBasePath();

		if( $basePath === null || $basePath === '' )
		{
			return null;
		}

		return ScheduleFile::fromBasePath( $basePath );
	}

	/**
	 * Latest claim per schedule entry from the database schedule lock, or
	 * null when the application is not using one (nothing to show).
	 *
	 * @return array<string,int>|null
	 */
	private function lastClaims(): ?array
	{
		if( $this->_settings->get( 'queue', 'schedule_lock' ) !== 'database' )
		{
			return null;
		}

		try
		{
			$driver = QueueManager::fromRegistry()->getDriver();

			if( !$driver instanceof DatabaseQueue )
			{
				return null;
			}

			return ( new DatabaseScheduleLock( $driver->getConnection() ) )->getLastClaims();
		}
		catch( \Throwable $e )
		{
			Log::warning( 'Scheduled Jobs: could not read schedule lock claims: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Determine the application base path.
	 *
	 * Mirrors the resolution used by the scheduler so the admin view and the
	 * CLI agree. The configured system.base_path is authoritative; the Registry
	 * keys are fallbacks (the MVC application only populates the legacy key in a
	 * web request, so RegistryKeys::BASE_PATH is often unset).
	 *
	 * @return string|null
	 */
	private function resolveBasePath(): ?string
	{
		$configured = $this->_settings->get( 'system', 'base_path' );

		if( is_string( $configured ) && $configured !== '' )
		{
			return $configured;
		}

		$registry = Registry::getInstance();

		foreach( [ RegistryKeys::BASE_PATH, RegistryKeys::BASE_PATH_LEGACY ] as $key )
		{
			$value = $registry->get( $key );

			if( is_string( $value ) && $value !== '' )
			{
				return $value;
			}
		}

		$cwd = getcwd();

		return $cwd !== false ? $cwd : null;
	}
}
