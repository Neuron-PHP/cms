<?php

namespace Neuron\Cms\Jobs;

use Neuron\Application\Base;
use Neuron\Jobs\Bootstrap\IWorkerBootstrap;

use function Neuron\Cms\boot;

/**
 * Boots the CMS for queue worker and scheduler processes.
 *
 * Configure in neuron.yaml so `jobs:work` and `jobs:schedule` run jobs with
 * the same DI container, ORM connection, event listeners and view paths a web
 * request gets:
 *
 *     queue:
 *       bootstrap: Neuron\Cms\Jobs\WorkerBootstrap
 *
 * The returned application is started with Base::start() by the framework,
 * which runs the CMS initializers (routes, auth filters, etc.) without
 * handling a request.
 *
 * @package Neuron\Cms\Jobs
 */
class WorkerBootstrap implements IWorkerBootstrap
{
	/**
	 * @inheritDoc
	 */
	public function boot( string $configPath ): ?Base
	{
		return boot( $configPath );
	}
}
