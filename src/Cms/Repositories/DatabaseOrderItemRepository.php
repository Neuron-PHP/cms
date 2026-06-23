<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed order line-item repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseOrderItemRepository implements IOrderItemRepository
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
			'payment_id',
			'product_id',
			'name',
			'sku',
			'unit_amount_cents',
			'quantity',
			'currency'
		];

		$values = [];
		foreach( $columns as $column )
		{
			$values[ ':' . $column ] = $data[ $column ] ?? null;
		}

		$sql = 'INSERT INTO order_items ( ' . implode( ', ', $columns ) . ' ) '
			. 'VALUES ( ' . implode( ', ', array_keys( $values ) ) . ' )';

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $values );

		return (int) $this->_pdo->lastInsertId();
	}

	/**
	 * @inheritDoc
	 */
	public function createForOrder( int $paymentId, array $items ): void
	{
		foreach( $items as $item )
		{
			if( !is_array( $item ) )
			{
				continue;
			}

			$item['payment_id'] = $paymentId;
			$this->create( $item );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function findByPaymentId( int $paymentId ): array
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM order_items WHERE payment_id = ? ORDER BY id ASC' );
		$stmt->execute( [ $paymentId ] );

		return $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];
	}
}
