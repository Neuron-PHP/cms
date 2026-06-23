<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed subscription repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL. A subscription is created when the
 * checkout that started a recurring payment completes, kept current by
 * subscription / invoice webhooks, and marked canceled when the agreement ends.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseSubscriptionRepository implements ISubscriptionRepository
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
			'purpose',
			'form_key',
			'provider',
			'subscription_id',
			'status',
			'frequency',
			'amount_cents',
			'currency',
			'payer_name',
			'payer_email',
			'payload',
			'current_period_end',
			'canceled_at'
		];

		$values = [];
		foreach( $columns as $column )
		{
			$values[ ':' . $column ] = $data[ $column ] ?? null;
		}

		$sql = 'INSERT INTO subscriptions ( ' . implode( ', ', $columns ) . ' ) '
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
		$stmt = $this->_pdo->prepare( 'SELECT * FROM subscriptions WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : $row;
	}

	/**
	 * @inheritDoc
	 */
	public function findByGatewayId( string $subscriptionId ): ?array
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM subscriptions WHERE subscription_id = ? LIMIT 1' );
		$stmt->execute( [ $subscriptionId ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : $row;
	}

	/**
	 * @inheritDoc
	 */
	public function updateState( string $subscriptionId, array $data ): bool
	{
		$sets   = [ 'updated_at = :updated_at' ];
		$params = [
			':updated_at'      => date( 'Y-m-d H:i:s' ),
			':subscription_id' => $subscriptionId
		];

		foreach( [ 'status', 'current_period_end', 'canceled_at', 'amount_cents' ] as $optional )
		{
			if( array_key_exists( $optional, $data ) && $data[ $optional ] !== null )
			{
				$sets[]                    = "{$optional} = :{$optional}";
				$params[ ':' . $optional ] = $data[ $optional ];
			}
		}

		$sql  = 'UPDATE subscriptions SET ' . implode( ', ', $sets ) . ' WHERE subscription_id = :subscription_id';
		$stmt = $this->_pdo->prepare( $sql );

		return $stmt->execute( $params );
	}

	/**
	 * @inheritDoc
	 */
	public function delete( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM subscriptions WHERE id = ?' );

		return $stmt->execute( [ $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?string $status = null, ?string $formKey = null, ?string $purpose = null ): array
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

		if( $purpose !== null && $purpose !== '' )
		{
			$conditions[]       = 'purpose = :purpose';
			$params[':purpose'] = $purpose;
		}

		$where = $conditions === [] ? '' : ' WHERE ' . implode( ' AND ', $conditions );

		$countStmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM subscriptions' . $where );
		$countStmt->execute( $params );
		$total = (int) $countStmt->fetchColumn();

		$sql = 'SELECT * FROM subscriptions' . $where
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
			'SELECT DISTINCT form_key FROM subscriptions ORDER BY form_key'
		);

		return $stmt->fetchAll( PDO::FETCH_COLUMN ) ?: [];
	}
}
