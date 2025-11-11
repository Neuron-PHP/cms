<?php

namespace Neuron\Cms\Listeners;

use Neuron\Events\IListener;
use Neuron\Cms\Events\PostPublishedEvent;
use Neuron\Cms\Events\PostDeletedEvent;
use Neuron\Cms\Events\CategoryUpdatedEvent;
use Neuron\Log\Log;
use Neuron\Mvc\Cache\ViewCache;
use Neuron\Patterns\Registry;

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
		// Try to get ViewCache from Registry
		$viewCache = Registry::getInstance()->get( 'ViewCache' );

		if( !$viewCache instanceof ViewCache )
		{
			Log::debug( "ViewCache not available in Registry - cache clearing skipped: {$reason}" );
			return;
		}

		try
		{
			if( $viewCache->clear() )
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
