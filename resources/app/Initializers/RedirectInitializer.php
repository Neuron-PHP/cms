<?php

namespace App\Initializers;

use Neuron\Application\CrossCutting\Event;
use Neuron\Cms\Listeners\ApplyRedirectListener;
use Neuron\Cms\Services\Redirect\RedirectService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Events\RequestReceivedEvent;
use Neuron\Patterns\IRunnable;
use Neuron\Patterns\Registry;

/**
 * Register admin-managed HTTP redirects.
 *
 * Prefers Router::setRedirectResolver() when the routing package supports it.
 * Otherwise listens for RequestReceivedEvent (and relies on event-listeners.yaml).
 */
class RedirectInitializer implements IRunnable
{
	public function run( array $argv = [] ): mixed
	{
		$registry = Registry::getInstance();
		$app = $registry->get( RegistryKeys::APP );
		$container = $registry->get( RegistryKeys::CONTAINER );

		if( !$app instanceof Application || !$container )
		{
			Log::debug( 'Redirect initializer skipped: application or container unavailable' );
			return null;
		}

		try
		{
			$service = $container->get( RedirectService::class );
			$router = $app->getRouter();

			if( method_exists( $router, 'setRedirectResolver' ) )
			{
				$router->setRedirectResolver(
					static function( string $uri ) use ( $service ) {
						return $service->resolveAndApply( $uri );
					}
				);
			}
			else
			{
				Event::registerListener( RequestReceivedEvent::class, ApplyRedirectListener::class );
			}
		}
		catch( \Throwable $e )
		{
			Log::debug( 'Redirect initializer skipped: ' . $e->getMessage() );
		}

		return null;
	}
}
