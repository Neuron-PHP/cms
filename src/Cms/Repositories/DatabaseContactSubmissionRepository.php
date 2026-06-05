<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed contact submission repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL. Submissions are persisted before
 * the notification email is sent so nothing is lost if delivery fails. The
 * dynamic per-form field values live in the JSON `payload` column.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseContactSubmissionRepository implements IContactSubmissionRepository
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
			'recipient',
			'subject',
			'reply_to_email',
			'reply_to_name',
			'payload',
			'ip_address',
			'user_agent',
			'delivered',
			'delivered_at'
		];

		$values = [];
		foreach( $columns as $column )
		{
			$value = $data[ $column ] ?? null;

			if( $column === 'delivered' )
			{
				$value = !empty( $value ) ? 1 : 0;
			}

			$values[ ':' . $column ] = $value;
		}

		$sql = 'INSERT INTO contact_submissions ( ' . implode( ', ', $columns ) . ' ) '
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
		$stmt = $this->_pdo->prepare( 'SELECT * FROM contact_submissions WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row === false ? null : $row;
	}

	/**
	 * @inheritDoc
	 */
	public function markDelivered( int $id ): bool
	{
		$stmt = $this->_pdo->prepare(
			'UPDATE contact_submissions SET delivered = 1, delivered_at = ? WHERE id = ?'
		);

		return $stmt->execute( [ date( 'Y-m-d H:i:s' ), $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function delete( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM contact_submissions WHERE id = ?' );

		return $stmt->execute( [ $id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?string $formKey = null ): array
	{
		$page    = max( 1, $page );
		$perPage = max( 1, $perPage );
		$offset  = ( $page - 1 ) * $perPage;

		$where  = '';
		$params = [];

		if( $formKey !== null && $formKey !== '' )
		{
			$where = ' WHERE form_key = :form_key';
			$params[':form_key'] = $formKey;
		}

		$countStmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM contact_submissions' . $where );
		$countStmt->execute( $params );
		$total = (int) $countStmt->fetchColumn();

		$sql = 'SELECT * FROM contact_submissions' . $where
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
			'SELECT DISTINCT form_key FROM contact_submissions ORDER BY form_key'
		);

		return $stmt->fetchAll( PDO::FETCH_COLUMN ) ?: [];
	}
}
