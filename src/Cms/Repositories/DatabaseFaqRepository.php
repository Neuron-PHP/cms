<?php

namespace Neuron\Cms\Repositories;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Faq;
use Neuron\Cms\Models\FaqItem;
use Neuron\Data\Settings\SettingManager;
use PDO;

/**
 * Database-backed FAQ repository.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseFaqRepository implements IFaqRepository
{
	private PDO $_pdo;

	/**
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		Faq::setPdo( $this->_pdo );
		FaqItem::setPdo( $this->_pdo );
	}

	public function all(): array
	{
		$stmt = $this->_pdo->query( 'SELECT * FROM faqs ORDER BY name ASC' );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => Faq::fromArray( $row ), $rows );
	}

	public function findById( int $id ): ?Faq
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM faqs WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Faq::fromArray( $row ) : null;
	}

	public function findBySlug( string $slug ): ?Faq
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM faqs WHERE slug = ? LIMIT 1' );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Faq::fromArray( $row ) : null;
	}

	public function create( Faq $faq ): Faq
	{
		$now = new DateTimeImmutable();
		$faq->setCreatedAt( $now );
		$faq->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO faqs ( name, slug, description, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$faq->getName(),
			$faq->getSlug(),
			$faq->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$faq->setId( (int) $this->_pdo->lastInsertId() );

		return $faq;
	}

	public function update( Faq $faq ): Faq
	{
		$now = new DateTimeImmutable();
		$faq->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE faqs
			SET name = ?, slug = ?, description = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$faq->getName(),
			$faq->getSlug(),
			$faq->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$faq->getId()
		] );

		return $faq;
	}

	public function delete( Faq $faq ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM faq_items WHERE faq_id = ?' );
		$stmt->execute( [ $faq->getId() ] );

		$stmt = $this->_pdo->prepare( 'DELETE FROM faqs WHERE id = ?' );

		return $stmt->execute( [ $faq->getId() ] );
	}

	public function slugExists( string $slug, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM faqs WHERE slug = ? AND id != ?' );
			$stmt->execute( [ $slug, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM faqs WHERE slug = ?' );
			$stmt->execute( [ $slug ] );
		}

		return (int) $stmt->fetchColumn() > 0;
	}

	public function getItems( int $faqId ): array
	{
		$stmt = $this->_pdo->prepare(
			'SELECT * FROM faq_items WHERE faq_id = ? ORDER BY sort_order ASC, id ASC'
		);
		$stmt->execute( [ $faqId ] );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => FaqItem::fromArray( $row ), $rows );
	}

	public function findItemById( int $id ): ?FaqItem
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM faq_items WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? FaqItem::fromArray( $row ) : null;
	}

	public function createItem( FaqItem $item ): FaqItem
	{
		$now = new DateTimeImmutable();
		$item->setCreatedAt( $now );
		$item->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO faq_items
			( faq_id, question, answer, sort_order, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$item->getFaqId(),
			$item->getQuestion(),
			$item->getAnswer(),
			$item->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$item->setId( (int) $this->_pdo->lastInsertId() );

		return $item;
	}

	public function updateItem( FaqItem $item ): FaqItem
	{
		$now = new DateTimeImmutable();
		$item->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE faq_items
			SET question = ?, answer = ?, sort_order = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$item->getQuestion(),
			$item->getAnswer(),
			$item->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$item->getId()
		] );

		return $item;
	}

	public function deleteItem( FaqItem $item ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM faq_items WHERE id = ?' );

		return $stmt->execute( [ $item->getId() ] );
	}

	public function countItems( int $faqId ): int
	{
		$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM faq_items WHERE faq_id = ?' );
		$stmt->execute( [ $faqId ] );

		return (int) $stmt->fetchColumn();
	}

	public function nextItemSortOrder( int $faqId ): int
	{
		$stmt = $this->_pdo->prepare(
			'SELECT COALESCE( MAX( sort_order ), -1 ) + 1 FROM faq_items WHERE faq_id = ?'
		);
		$stmt->execute( [ $faqId ] );

		return (int) $stmt->fetchColumn();
	}
}
