<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Revision;

/**
 * Contract for content revision persistence.
 *
 * @package Neuron\Cms\Repositories
 */
interface IRevisionRepository
{
	/**
	 * Persist a new revision.
	 *
	 * @param Revision $revision
	 * @return Revision
	 */
	public function create( Revision $revision ): Revision;

	/**
	 * Find a revision by ID.
	 *
	 * @param int $id
	 * @return Revision|null
	 */
	public function findById( int $id ): ?Revision;

	/**
	 * Get all revisions for a given content item, newest first.
	 *
	 * @param string $contentType
	 * @param int $contentId
	 * @return Revision[]
	 */
	public function getForContent( string $contentType, int $contentId ): array;

	/**
	 * Count revisions for a given content item.
	 *
	 * @param string $contentType
	 * @param int $contentId
	 * @return int
	 */
	public function countForContent( string $contentType, int $contentId ): int;
}
