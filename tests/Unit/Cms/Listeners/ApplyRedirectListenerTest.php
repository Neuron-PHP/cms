<?php

namespace Tests\Unit\Cms\Listeners;

use Neuron\Cms\Listeners\ApplyRedirectListener;
use Neuron\Cms\Services\Redirect\RedirectService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Mvc\Application;
use Neuron\Mvc\Events\Http403;
use Neuron\Mvc\Events\Http404;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class ApplyRedirectListenerTest extends TestCase
{
	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::APP, null );
		Registry::getInstance()->set( RegistryKeys::CONTAINER, null );
		parent::tearDown();
	}

	public function testIgnoresUnrelatedEvents(): void
	{
		$listener = new ApplyRedirectListener();
		$container = $this->createMock( IContainer::class );
		$container->expects( $this->never() )->method( 'get' );
		Registry::getInstance()->set( RegistryKeys::CONTAINER, $container );

		$listener->event( new Http403( '/admin' ) );
	}

	public function testResolvesOriginalRouteFromApplication(): void
	{
		$app = $this->createMock( Application::class );
		$app->method( 'getParameters' )->willReturn( [ 'route' => '/old-about/' ] );

		$service = $this->createMock( RedirectService::class );
		$service->expects( $this->once() )
			->method( 'resolveAndApply' )
			->with( '/old-about/' )
			->willReturn( null );

		$container = $this->createMock( IContainer::class );
		$container->method( 'get' )->with( RedirectService::class )->willReturn( $service );

		Registry::getInstance()->set( RegistryKeys::APP, $app );
		Registry::getInstance()->set( RegistryKeys::CONTAINER, $container );

		$listener = new ApplyRedirectListener();
		$listener->event( new Http404( '/404' ) );
	}
}
