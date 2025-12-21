<?php

namespace Neuron\Cms\Repositories\Traits;

use DateTimeImmutable;

/**
 * Trait for managing entity timestamps in repositories
 *
 * @package Neuron\Cms\Repositories\Traits
 */
trait ManagesTimestamps
{
	/**
	 * Set created_at and updated_at timestamps if not already set
	 *
	 * @param object $entity Entity with getCreatedAt/setCreatedAt and getUpdatedAt/setUpdatedAt methods
	 * @return void
	 */
	protected function ensureTimestamps( object $entity ): void
	{
		$now = new DateTimeImmutable();

		if( method_exists( $entity, 'getCreatedAt' ) && method_exists( $entity, 'setCreatedAt' ) )
		{
			if( !$entity->getCreatedAt() )
			{
				$entity->setCreatedAt( $now );
			}
		}

		if( method_exists( $entity, 'getUpdatedAt' ) && method_exists( $entity, 'setUpdatedAt' ) )
		{
			if( !$entity->getUpdatedAt() )
			{
				$entity->setUpdatedAt( $now );
			}
		}
	}

	/**
	 * Save entity and return the refreshed version from database
	 *
	 * Ensures the returned entity has all database-generated values.
	 * Throws an exception if the entity cannot be found after save.
	 *
	 * @template T
	 * @param T $entity Entity to save
	 * @param callable $finder Callback to find entity by ID: function(int $id): ?T
	 * @param string $entityType Human-readable entity type for error messages
	 * @return T The refreshed entity from database
	 * @throws \RuntimeException If entity cannot be found after save
	 */
	protected function saveAndRefresh( object $entity, callable $finder, string $entityType ): object
	{
		// Save the entity
		$entity->save();

		// Get the ID
		$id = method_exists( $entity, 'getId' ) ? $entity->getId() : null;

		if( $id === null )
		{
			throw new \RuntimeException(
				"Failed to save {$entityType}: Entity ID is null after save operation"
			);
		}

		// Re-fetch from database to get all DB-set values
		$refreshed = $finder( $id );

		if( $refreshed === null )
		{
			throw new \RuntimeException(
				"Failed to retrieve {$entityType} after save: Entity with ID {$id} not found in database"
			);
		}

		return $refreshed;
	}
}
