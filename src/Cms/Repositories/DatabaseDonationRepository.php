<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed donation repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL. A donation is persisted as
 * "pending" before the donor is redirected to the gateway and flipped to
 * "completed" by the verified webhook. Dynamic per-form donor field values
 * live in the JSON `payload` column.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseDonationRepository implements IDonationRepository
{
	protected PDO $_pdo;

	/**
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * @inheritDoc
	 */
	public function create( array $data ): int
	{
		$columns = [
			'form_key',
			'provider',
			'session_id',
			'payment_intent_id',
			'subscription_id',
			'amount_cents',
			'currency',
			'frequency',
			'status',
			'donor_name',
			'donor_email',
			'payload',
			'ip_address',
			'user_agent',
			'completed_at'
		];

		$values = [];
		foreach( $columns as $column )
		{
			$values[ ':' . $column ] = $data[ $column ] ?? null;
		}

		$sql = 'INSERT INTO donations ( ' . implode( ', ', $columns ) . ' ) '
			. 'VALUES ( ' . implode( ', ', array_keys( $values ) ) . ' )';

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $values );

		return (int) $this->_pdo->lastInsertId();
	}

	/**
	 * @inheritDoc
	 */
	public function findById( int $id ): ?array
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM donations WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : $row;
	}

	/**
	 * @inheritDoc
	 */
	public function findBySessionId( string $sessionId ): ?array
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM donations WHERE session_id = ? LIMIT 1' );
		$stmt->execute( [ $sessionId ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : $row;
	}

	/**
	 * @inheritDoc
	 */
	public function markCompleted( int $id, array $data = [] ): bool
	{
		$sets   = [ 'status = :status', 'completed_at = :completed_at' ];
		$params = [
			':status'       => 'completed',
			':completed_at' => date( 'Y-m-d H:i:s' ),
			':id'           => $id
		];

		foreach( [ 'payment_intent_id', 'subscription_id', 'amount_cents' ] as $optional )
		{
			if( array_key_exists( $optional, $data ) && $data[ $optional ] !== null )
			{
				$sets[]                   = "{$optional} = :{$optional}";
				$params[ ':' . $optional ] = $data[ $optional ];
			}
		}

		$sql  = 'UPDATE donations SET ' . implode( ', ', $sets ) . ' WHERE id = :id';
		$stmt = $this->_pdo->prepare( $sql );

		return $stmt->execute( $params );
	}

	/**
	 * @inheritDoc
	 */
	public function updateStatus( int $id, string $status ): bool
	{
		$stmt = $this->_pdo->prepare( 'UPDATE donations SET status = ? WHERE id = ?' );

		return $stmt->execute( [ $status, $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function updateSession( int $id, string $sessionId ): bool
	{
		$stmt = $this->_pdo->prepare( 'UPDATE donations SET session_id = ? WHERE id = ?' );

		return $stmt->execute( [ $sessionId, $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function delete( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM donations WHERE id = ?' );

		return $stmt->execute( [ $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?string $status = null, ?string $formKey = null ): array
	{
		$page    = max( 1, $page );
		$perPage = max( 1, $perPage );
		$offset  = ( $page - 1 ) * $perPage;

		$conditions = [];
		$params     = [];

		if( $status !== null && $status !== '' )
		{
			$conditions[]      = 'status = :status';
			$params[':status'] = $status;
		}

		if( $formKey !== null && $formKey !== '' )
		{
			$conditions[]        = 'form_key = :form_key';
			$params[':form_key'] = $formKey;
		}

		$where = $conditions === [] ? '' : ' WHERE ' . implode( ' AND ', $conditions );

		$countStmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM donations' . $where );
		$countStmt->execute( $params );
		$total = (int) $countStmt->fetchColumn();

		$sql = 'SELECT * FROM donations' . $where
			. ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';

		$stmt = $this->_pdo->prepare( $sql );

		foreach( $params as $key => $value )
		{
			$stmt->bindValue( $key, $value );
		}

		$stmt->bindValue( ':limit', $perPage, PDO::PARAM_INT );
		$stmt->bindValue( ':offset', $offset, PDO::PARAM_INT );
		$stmt->execute();

		$items = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return [
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $perPage,
			'pages'    => (int) ceil( $total / $perPage )
		];
	}

	/**
	 * @inheritDoc
	 */
	public function formKeys(): array
	{
		$stmt = $this->_pdo->query(
			'SELECT DISTINCT form_key FROM donations ORDER BY form_key'
		);

		return $stmt->fetchAll( PDO::FETCH_COLUMN ) ?: [];
	}
}
