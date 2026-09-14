<?php

namespace Neuron\Cms\Repositories;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Team;
use Neuron\Cms\Models\TeamMember;
use Neuron\Data\Settings\SettingManager;
use PDO;

/**
 * Database-backed team repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseTeamRepository implements ITeamRepository
{
	private PDO $_pdo;

	/**
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		Team::setPdo( $this->_pdo );
		TeamMember::setPdo( $this->_pdo );
	}

	/**
	 * @inheritDoc
	 */
	public function all(): array
	{
		$stmt = $this->_pdo->query( 'SELECT * FROM teams ORDER BY name ASC' );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => Team::fromArray( $row ), $rows );
	}

	/**
	 * @inheritDoc
	 */
	public function findById( int $id ): ?Team
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM teams WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Team::fromArray( $row ) : null;
	}

	/**
	 * @inheritDoc
	 */
	public function findBySlug( string $slug ): ?Team
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM teams WHERE slug = ? LIMIT 1' );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Team::fromArray( $row ) : null;
	}

	/**
	 * @inheritDoc
	 */
	public function create( Team $team ): Team
	{
		$now = new DateTimeImmutable();
		$team->setCreatedAt( $now );
		$team->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO teams ( name, slug, description, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$team->getName(),
			$team->getSlug(),
			$team->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$team->setId( (int) $this->_pdo->lastInsertId() );

		return $team;
	}

	/**
	 * @inheritDoc
	 */
	public function update( Team $team ): Team
	{
		$now = new DateTimeImmutable();
		$team->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE teams
			SET name = ?, slug = ?, description = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$team->getName(),
			$team->getSlug(),
			$team->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$team->getId()
		] );

		return $team;
	}

	/**
	 * @inheritDoc
	 */
	public function delete( Team $team ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM team_members WHERE team_id = ?' );
		$stmt->execute( [ $team->getId() ] );

		$stmt = $this->_pdo->prepare( 'DELETE FROM teams WHERE id = ?' );

		return $stmt->execute( [ $team->getId() ] );
	}

	/**
	 * @inheritDoc
	 */
	public function slugExists( string $slug, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM teams WHERE slug = ? AND id != ?' );
			$stmt->execute( [ $slug, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM teams WHERE slug = ?' );
			$stmt->execute( [ $slug ] );
		}

		return (int) $stmt->fetchColumn() > 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getMembers( int $teamId ): array
	{
		$stmt = $this->_pdo->prepare(
			'SELECT * FROM team_members WHERE team_id = ? ORDER BY sort_order ASC, name ASC'
		);
		$stmt->execute( [ $teamId ] );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => TeamMember::fromArray( $row ), $rows );
	}

	/**
	 * @inheritDoc
	 */
	public function findMemberById( int $id ): ?TeamMember
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM team_members WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? TeamMember::fromArray( $row ) : null;
	}

	/**
	 * @inheritDoc
	 */
	public function createMember( TeamMember $member ): TeamMember
	{
		$now = new DateTimeImmutable();
		$member->setCreatedAt( $now );
		$member->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO team_members
			( team_id, name, title, bio, contact, image_url, sort_order, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$member->getTeamId(),
			$member->getName(),
			$member->getTitle(),
			$member->getBio(),
			$member->getContact(),
			$member->getImageUrl(),
			$member->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$member->setId( (int) $this->_pdo->lastInsertId() );

		return $member;
	}

	/**
	 * @inheritDoc
	 */
	public function updateMember( TeamMember $member ): TeamMember
	{
		$now = new DateTimeImmutable();
		$member->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE team_members
			SET name = ?, title = ?, bio = ?, contact = ?, image_url = ?, sort_order = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$member->getName(),
			$member->getTitle(),
			$member->getBio(),
			$member->getContact(),
			$member->getImageUrl(),
			$member->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$member->getId()
		] );

		return $member;
	}

	/**
	 * @inheritDoc
	 */
	public function deleteMember( TeamMember $member ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM team_members WHERE id = ?' );

		return $stmt->execute( [ $member->getId() ] );
	}

	/**
	 * @inheritDoc
	 */
	public function countMembers( int $teamId ): int
	{
		$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM team_members WHERE team_id = ?' );
		$stmt->execute( [ $teamId ] );

		return (int) $stmt->fetchColumn();
	}

	/**
	 * @inheritDoc
	 */
	public function nextMemberSortOrder( int $teamId ): int
	{
		$stmt = $this->_pdo->prepare(
			'SELECT COALESCE( MAX( sort_order ), -1 ) + 1 FROM team_members WHERE team_id = ?'
		);
		$stmt->execute( [ $teamId ] );

		return (int) $stmt->fetchColumn();
	}
}
