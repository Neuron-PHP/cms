<?php

namespace Neuron\Cms\Listeners;

use Neuron\Events\IListener;
use Neuron\Cms\Events\PostPublishedEvent;
use Neuron\Cms\Events\PostDeletedEvent;
use Neuron\Cms\Events\CategoryUpdatedEvent;
use Neuron\Log\Log;
use Neuron\Mvc\Cache\ViewCache;

/**
 * Clears view cache when content changes.
 *
 * Automatically invalidates cached views when posts are published/deleted
 * or categories are updated to ensure users see fresh content.
 *
 * @package Neuron\Cms\Listeners
 */
class ClearCacheListener implements IListener
{
	private ?ViewCache $viewCache;

	/**
	 * Constructor
	 *
	 * @param ViewCache|null $viewCache Optional view cache instance
	 */
	public function __construct( ?ViewCache $viewCache = null )
	{
		$this->viewCache = $viewCache;
	}

	/**
	 * Handle content change events
	 *
	 * @param PostPublishedEvent|PostDeletedEvent|CategoryUpdatedEvent $event
	 * @return void
	 */
	public function event( $event ): void
	{
		if( $event instanceof PostPublishedEvent )
		{
			$this->clearCache( "Post published: {$event->post->getTitle()}" );
		}
		elseif( $event instanceof PostDeletedEvent )
		{
			$this->clearCache( "Post deleted: ID {$event->postId}" );
		}
		elseif( $event instanceof CategoryUpdatedEvent )
		{
			$this->clearCache( "Category updated: {$event->category->getName()}" );
		}
	}

	/**
	 * Clear all view cache
	 *
	 * @param string $reason Reason for cache clear (for logging)
	 * @return void
	 */
	private function clearCache( string $reason ): void
	{
		if( !$this->viewCache )
		{
			Log::debug( "ViewCache not available - cache clearing skipped: {$reason}" );
			return;
		}

		try
		{
			if( $this->viewCache->clear() )
			{
				Log::info( "Cache cleared successfully: {$reason}" );
			}
			else
			{
				Log::warning( "Cache clear returned false: {$reason}" );
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Error clearing cache for '{$reason}': " . $e->getMessage() );
		}
	}
}
