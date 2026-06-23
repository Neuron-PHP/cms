<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed product repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseProductRepository implements IProductRepository
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
			'name',
			'slug',
			'sku',
			'description',
			'price_cents',
			'currency',
			'image_url',
			'active',
			'sort_order'
		];

		$values = [];
		foreach( $columns as $column )
		{
			$values[ ':' . $column ] = $data[ $column ] ?? null;
		}

		$sql = 'INSERT INTO products ( ' . implode( ', ', $columns ) . ' ) '
			. 'VALUES ( ' . implode( ', ', array_keys( $values ) ) . ' )';

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $values );

		return (int) $this->_pdo->lastInsertId();
	}

	/**
	 * @inheritDoc
	 */
	public function update( int $id, array $data ): bool
	{
		$allowed = [ 'name', 'slug', 'sku', 'description', 'price_cents', 'currency', 'image_url', 'active', 'sort_order' ];

		$sets   = [];
		$params = [ ':id' => $id ];

		foreach( $allowed as $column )
		{
			if( array_key_exists( $column, $data ) )
			{
				$sets[]                  = "{$column} = :{$column}";
				$params[ ':' . $column ] = $data[ $column ];
			}
		}

		$sets[]                   = 'updated_at = :updated_at';
		$params[ ':updated_at' ]  = date( 'Y-m-d H:i:s' );

		$sql  = 'UPDATE products SET ' . implode( ', ', $sets ) . ' WHERE id = :id';
		$stmt = $this->_pdo->prepare( $sql );

		return $stmt->execute( $params );
	}

	/**
	 * @inheritDoc
	 */
	public function findById( int $id ): ?array
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM products WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : $row;
	}

	/**
	 * @inheritDoc
	 */
	public function findBySlug( string $slug ): ?array
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM products WHERE slug = ? LIMIT 1' );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : $row;
	}

	/**
	 * @inheritDoc
	 */
	public function allActive( ?int $limit = null ): array
	{
		$sql = 'SELECT * FROM products WHERE active = 1 ORDER BY sort_order ASC, name ASC';

		if( $limit !== null && $limit > 0 )
		{
			$stmt = $this->_pdo->prepare( $sql . ' LIMIT :limit' );
			$stmt->bindValue( ':limit', $limit, PDO::PARAM_INT );
			$stmt->execute();
		}
		else
		{
			$stmt = $this->_pdo->query( $sql );
		}

		return $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];
	}

	/**
	 * @inheritDoc
	 */
	public function delete( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM products WHERE id = ?' );

		return $stmt->execute( [ $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function paginate( int $page = 1, int $perPage = 25 ): array
	{
		$page    = max( 1, $page );
		$perPage = max( 1, $perPage );
		$offset  = ( $page - 1 ) * $perPage;

		$total = (int) $this->_pdo->query( 'SELECT COUNT(*) FROM products' )->fetchColumn();

		$stmt = $this->_pdo->prepare(
			'SELECT * FROM products ORDER BY sort_order ASC, id DESC LIMIT :limit OFFSET :offset'
		);
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
}
