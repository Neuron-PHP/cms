<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\EventRegistration;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed event registration repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseEventRegistrationRepository implements IEventRegistrationRepository
{
	protected PDO $_pdo;

	/**
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		EventRegistration::setPdo( $this->_pdo );
	}

	/**
	 * @inheritDoc
	 */
	public function create( EventRegistration $registration ): EventRegistration
	{
		$stmt = $this->_pdo->prepare(
			"INSERT INTO event_registrations (
				event_id, occurrence_date, user_id, name, email, notes, status, ip_address, user_agent, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$now = new DateTimeImmutable();
		$registration->setCreatedAt( $now );
		$registration->setUpdatedAt( $now );

		$stmt->execute( [
			$registration->getEventId(),
			$registration->getOccurrenceDate()?->format( 'Y-m-d H:i:s' ),
			$registration->getUserId(),
			$registration->getName(),
			$registration->getEmail(),
			$registration->getNotes(),
			$registration->getStatus(),
			$registration->getIpAddress(),
			$registration->getUserAgent(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$registration->setId( (int)$this->_pdo->lastInsertId() );

		return $registration;
	}

	/**
	 * @inheritDoc
	 */
	public function findById( int $id ): ?EventRegistration
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM event_registrations WHERE id = ?" );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : EventRegistration::fromArray( $row );
	}

	/**
	 * @inheritDoc
	 */
	public function getByEvent( int $eventId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM event_registrations WHERE event_id = ? ORDER BY created_at DESC, id DESC"
		);
		$stmt->execute( [ $eventId ] );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( $row ) => EventRegistration::fromArray( $row ), $rows );
	}

	/**
	 * @inheritDoc
	 */
	public function countByEvent( int $eventId, ?DateTimeImmutable $occurrenceDate = null ): int
	{
		if( $occurrenceDate !== null )
		{
			$stmt = $this->_pdo->prepare(
				"SELECT COUNT(*) FROM event_registrations
				WHERE event_id = ? AND occurrence_date = ? AND status = ?"
			);
			$stmt->execute( [ $eventId, $occurrenceDate->format( 'Y-m-d H:i:s' ), EventRegistration::STATUS_REGISTERED ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare(
				"SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = ?"
			);
			$stmt->execute( [ $eventId, EventRegistration::STATUS_REGISTERED ] );
		}

		return (int)$stmt->fetchColumn();
	}

	/**
	 * @inheritDoc
	 */
	public function existsForEmail( int $eventId, string $email, ?DateTimeImmutable $occurrenceDate = null ): bool
	{
		if( $occurrenceDate !== null )
		{
			$stmt = $this->_pdo->prepare(
				"SELECT COUNT(*) FROM event_registrations
				WHERE event_id = ? AND email = ? AND occurrence_date = ? AND status = ?"
			);
			$stmt->execute( [ $eventId, $email, $occurrenceDate->format( 'Y-m-d H:i:s' ), EventRegistration::STATUS_REGISTERED ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare(
				"SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND email = ? AND status = ?"
			);
			$stmt->execute( [ $eventId, $email, EventRegistration::STATUS_REGISTERED ] );
		}

		return $stmt->fetchColumn() > 0;
	}

	/**
	 * @inheritDoc
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?int $eventId = null ): array
	{
		$page    = max( 1, $page );
		$perPage = max( 1, $perPage );
		$offset  = ( $page - 1 ) * $perPage;

		$where  = '';
		$params = [];

		if( $eventId !== null )
		{
			$where = ' WHERE event_id = :event_id';
			$params[':event_id'] = $eventId;
		}

		$countStmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM event_registrations' . $where );
		$countStmt->execute( $params );
		$total = (int)$countStmt->fetchColumn();

		$sql = 'SELECT * FROM event_registrations' . $where
			. ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';

		$stmt = $this->_pdo->prepare( $sql );

		foreach( $params as $key => $value )
		{
			$stmt->bindValue( $key, $value, PDO::PARAM_INT );
		}

		$stmt->bindValue( ':limit', $perPage, PDO::PARAM_INT );
		$stmt->bindValue( ':offset', $offset, PDO::PARAM_INT );
		$stmt->execute();

		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return [
			'items'    => array_map( fn( $row ) => EventRegistration::fromArray( $row ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $perPage,
			'pages'    => (int)ceil( $total / $perPage )
		];
	}

	/**
	 * @inheritDoc
	 */
	public function delete( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM event_registrations WHERE id = ?" );

		return $stmt->execute( [ $id ] );
	}
}
