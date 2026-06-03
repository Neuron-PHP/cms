<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Revision;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed content revision repository using the Neuron ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseRevisionRepository implements IRevisionRepository
{
	private PDO $_pdo;

	/**
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
		Revision::setPdo( $this->_pdo );
	}

	/**
	 * Persist a new revision.
	 */
	public function create( Revision $revision ): Revision
	{
		$revision->save();
		return $revision;
	}

	/**
	 * Find a revision by ID.
	 */
	public function findById( int $id ): ?Revision
	{
		return Revision::query()->where( 'id', $id )->first();
	}

	/**
	 * Get all revisions for a given content item, newest first.
	 *
	 * @return Revision[]
	 */
	public function getForContent( string $contentType, int $contentId ): array
	{
		return Revision::query()
			->where( 'content_type', $contentType )
			->where( 'content_id', $contentId )
			->orderBy( 'created_at', 'DESC' )
			->orderBy( 'id', 'DESC' )
			->get();
	}

	/**
	 * Count revisions for a given content item.
	 */
	public function countForContent( string $contentType, int $contentId ): int
	{
		return Revision::query()
			->where( 'content_type', $contentType )
			->where( 'content_id', $contentId )
			->count();
	}
}
