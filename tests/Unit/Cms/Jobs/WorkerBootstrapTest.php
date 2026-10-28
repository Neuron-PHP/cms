<?php

namespace Tests\Unit\Cms\Jobs;

use Neuron\Cms\Jobs\WorkerBootstrap;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Jobs\Bootstrap\IWorkerBootstrap;
use Neuron\Mvc\Application;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class WorkerBootstrapTest extends TestCase
{
	private mixed $previousSettings;

	protected function setUp(): void
	{
		parent::setUp();

		// Base::initSettings() reuses whatever SettingManager an earlier test
		// left in the Registry; boot from examples/config on a clean slate.
		$this->previousSettings = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $this->previousSettings );

		parent::tearDown();
	}

	public function testImplementsWorkerBootstrapContract(): void
	{
		$this->assertInstanceOf( IWorkerBootstrap::class, new WorkerBootstrap() );
	}

	public function testBootReturnsCmsApplicationWithContainer(): void
	{
		$app = ( new WorkerBootstrap() )->boot( 'examples/config' );

		$this->assertInstanceOf( Application::class, $app );

		// Jobs are resolved through the container QueueManager finds in the
		// Registry; the CMS boot must have put one there.
		$this->assertInstanceOf( IContainer::class, Registry::getInstance()->get( RegistryKeys::CONTAINER ) );
	}

	public function testBootedApplicationCanStartWithoutARequest(): void
	{
		$app = ( new WorkerBootstrap() )->boot( 'examples/config' );

		$this->assertTrue( $app->start() );

		$app->finish();
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testShippedInitializersRunInACliProcess(): void
	{
		// Point the runner at the initializers the CMS ships to sites, since
		// examples/config has none of its own. These must not start a session
		// or emit headers: the worker and scheduler run them without a request.
		$registry = Registry::getInstance();
		$previousPath = $registry->get( 'Initializers.Path' );
		$registry->set( 'Initializers.Path', dirname( __DIR__, 4 ) . '/resources/app/Initializers' );

		try
		{
			$app = ( new WorkerBootstrap() )->boot( 'examples/config' );

			$this->assertTrue( $app->start(), 'CMS initializers must run cleanly without a request' );
			$this->assertSame( PHP_SESSION_NONE, session_status(), 'Initializers must not start a session' );
			$this->assertTrue( class_exists( \App\Initializers\AuthInitializer::class, false ), 'Shipped initializers were not loaded' );

			$app->finish();
		}
		finally
		{
			$registry->set( 'Initializers.Path', $previousPath );
		}
	}
}
