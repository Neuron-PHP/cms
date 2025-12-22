<?php

namespace Neuron\Cms\Repositories\Traits;

use DateTimeImmutable;
use Neuron\Cms\Exceptions\RepositoryException;

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
	 * @throws RepositoryException If entity cannot be found after save
	 */
	protected function saveAndRefresh( object $entity, callable $finder, string $entityType ): object
	{
		// Save the entity
		$entity->save();

		// Get the ID
		$id = method_exists( $entity, 'getId' ) ? $entity->getId() : null;

		if( $id === null )
		{
			throw new RepositoryException(
				'save',
				$entityType,
				'Entity ID is null after save operation'
			);
		}

		// Re-fetch from database to get all DB-set values
		$refreshed = $finder( $id );

		if( $refreshed === null )
		{
			throw new RepositoryException(
				'retrieve',
				$entityType,
				"Entity with ID {$id} not found in database after save"
			);
		}

		return $refreshed;
	}

	/**
	 * Prepare entity for creation by setting timestamps, saving, and refreshing
	 *
	 * This combines the common pattern of:
	 * 1. Setting timestamps if not already set
	 * 2. Saving the entity
	 * 3. Re-fetching from database to get all DB-generated values
	 *
	 * Use this in create() methods after performing duplicate checks.
	 *
	 * @template T
	 * @param T $entity Entity to create
	 * @param callable $finder Callback to find entity by ID: function(int $id): ?T
	 * @param string $entityType Human-readable entity type for error messages
	 * @return T The refreshed entity from database
	 * @throws RepositoryException If entity cannot be saved or found after save
	 */
	protected function createEntity( object $entity, callable $finder, string $entityType ): object
	{
		$this->ensureTimestamps( $entity );
		return $this->saveAndRefresh( $entity, $finder, $entityType );
	}
}
