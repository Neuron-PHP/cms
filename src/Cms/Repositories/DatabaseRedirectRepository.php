<?php

namespace Neuron\Cms\Repositories;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Redirect;
use Neuron\Data\Settings\SettingManager;
use PDO;

/**
 * Database-backed HTTP redirect repository.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseRedirectRepository implements IRedirectRepository
{
	private PDO $_pdo;

	/**
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		Redirect::setPdo( $this->_pdo );
	}

	public function all(): array
	{
		$stmt = $this->_pdo->query( 'SELECT * FROM redirects ORDER BY from_path ASC' );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => Redirect::fromArray( $row ), $rows );
	}

	public function findById( int $id ): ?Redirect
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM redirects WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Redirect::fromArray( $row ) : null;
	}

	public function findActiveByFromPath( string $fromPath ): ?Redirect
	{
		$stmt = $this->_pdo->prepare(
			'SELECT * FROM redirects WHERE from_path = ? AND is_active = 1 LIMIT 1'
		);
		$stmt->execute( [ $fromPath ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Redirect::fromArray( $row ) : null;
	}

	public function create( Redirect $redirect ): Redirect
	{
		$now = new DateTimeImmutable();
		$redirect->setCreatedAt( $now );
		$redirect->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO redirects
			( from_path, to_url, status_code, is_active, preserve_query, notes, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$redirect->getFromPath(),
			$redirect->getToUrl(),
			$redirect->getStatusCode(),
			$redirect->isActive() ? 1 : 0,
			$redirect->getPreserveQuery() ? 1 : 0,
			$redirect->getNotes(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$redirect->setId( (int) $this->_pdo->lastInsertId() );

		return $redirect;
	}

	public function update( Redirect $redirect ): Redirect
	{
		$now = new DateTimeImmutable();
		$redirect->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE redirects
			SET from_path = ?, to_url = ?, status_code = ?, is_active = ?,
				preserve_query = ?, notes = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$redirect->getFromPath(),
			$redirect->getToUrl(),
			$redirect->getStatusCode(),
			$redirect->isActive() ? 1 : 0,
			$redirect->getPreserveQuery() ? 1 : 0,
			$redirect->getNotes(),
			$now->format( 'Y-m-d H:i:s' ),
			$redirect->getId()
		] );

		return $redirect;
	}

	public function delete( Redirect $redirect ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM redirects WHERE id = ?' );

		return $stmt->execute( [ $redirect->getId() ] );
	}

	public function fromPathExists( string $fromPath, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM redirects WHERE from_path = ? AND id != ?' );
			$stmt->execute( [ $fromPath, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM redirects WHERE from_path = ?' );
			$stmt->execute( [ $fromPath ] );
		}

		return (int) $stmt->fetchColumn() > 0;
	}
}
