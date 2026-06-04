<?php

namespace Neuron\Cms\Controllers\Admin;

use Cron\CronExpression;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Scheduled jobs controller.
 *
 * Displays the scheduled jobs defined in the application's
 * config/schedule.yaml file. Read-only.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
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

		$schedulePath = $this->getSchedulePath();
		$fileExists   = $schedulePath !== null && file_exists( $schedulePath );
		$jobs         = $fileExists ? $this->loadJobs( $schedulePath ) : [];

		return $this->view()
			->title( 'Scheduled Jobs' )
			->description( 'Scheduled Jobs' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'jobs' => $jobs,
				'scheduleFileExists' => $fileExists,
				FlashMessageType::SUCCESS->viewKey() => $this->getSessionManager()->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey() => $this->getSessionManager()->getFlash( FlashMessageType::ERROR->value )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Resolve the absolute path to config/schedule.yaml.
	 *
	 * @return string|null
	 */
	private function getSchedulePath(): ?string
	{
		$basePath = Registry::getInstance()->get( RegistryKeys::BASE_PATH );

		if( !$basePath )
		{
			return null;
		}

		return rtrim( (string)$basePath, '/' ) . '/config/schedule.yaml';
	}

	/**
	 * Parse schedule.yaml into a normalized list of jobs for display.
	 *
	 * Each entry contains: name, class, cron, args, queue, nextRun, valid.
	 *
	 * @param string $path
	 * @return array
	 */
	private function loadJobs( string $path ): array
	{
		try
		{
			$data = Yaml::parseFile( $path );
		}
		catch( ParseException $exception )
		{
			return [];
		}

		$schedule = $data['schedule'] ?? [];

		if( !is_array( $schedule ) )
		{
			return [];
		}

		$jobs = [];

		foreach( $schedule as $name => $job )
		{
			if( !is_array( $job ) )
			{
				continue;
			}

			$cron    = (string)( $job['cron'] ?? '' );
			$nextRun = null;
			$valid   = false;

			if( $cron !== '' )
			{
				try
				{
					$nextRun = ( new CronExpression( $cron ) )->getNextRunDate()->format( 'Y-m-d H:i:s' );
					$valid   = true;
				}
				catch( \Exception $exception )
				{
					$nextRun = null;
					$valid   = false;
				}
			}

			$jobs[] = [
				'name'    => (string)$name,
				'class'   => (string)( $job['class'] ?? '' ),
				'cron'    => $cron,
				'args'    => $job['args'] ?? [],
				'queue'   => $job['queue'] ?? null,
				'nextRun' => $nextRun,
				'valid'   => $valid
			];
		}

		return $jobs;
	}
}
