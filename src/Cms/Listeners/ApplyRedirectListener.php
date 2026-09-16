<?php

namespace Neuron\Cms\Listeners;

use Neuron\Cms\Services\Redirect\RedirectService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Events\IListener;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Events\Http404;
use Neuron\Mvc\Events\RequestReceivedEvent;
use Neuron\Patterns\Registry;
use Throwable;

/**
 * Applies admin-managed HTTP redirects on incoming requests and 404s.
 *
 * Used when the installed routing package does not yet expose
 * Router::setRedirectResolver(). The listener reads the original path from
 * the application parameters, not the event payload.
 *
 * @package Neuron\Cms\Listeners
 */
class ApplyRedirectListener implements IListener
{
	public function event( $event ): void
	{
		if( !$event instanceof Http404 && !$event instanceof RequestReceivedEvent )
		{
			return;
		}

		$registry = Registry::getInstance();
		$app = $registry->get( RegistryKeys::APP );
		$uri = '/';

		if( $app instanceof Application )
		{
			$uri = $app->getParameters()['route'] ?? '/';
		}
		elseif( $event instanceof Http404 && $event->route !== '' )
		{
			$uri = $event->route;
		}

		if( $uri === '' )
		{
			$uri = '/';
		}

		$container = $registry->get( RegistryKeys::CONTAINER );

		if( !$container || !method_exists( $container, 'get' ) )
		{
			return;
		}

		try
		{
			$service = $container->get( RedirectService::class );
			$service->resolveAndApply( $uri );
		}
		catch( Throwable $e )
		{
			Log::debug( 'Redirect listener skipped: ' . $e->getMessage() );
		}
	}
}
