<?php

namespace Neuron\Cms\Repositories;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Carousel;
use Neuron\Cms\Models\CarouselSlide;
use Neuron\Data\Settings\SettingManager;
use PDO;

/**
 * Database-backed carousel repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseCarouselRepository implements ICarouselRepository
{
	private PDO $_pdo;

	/**
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		Carousel::setPdo( $this->_pdo );
		CarouselSlide::setPdo( $this->_pdo );
	}

	/**
	 * @inheritDoc
	 */
	public function all(): array
	{
		$stmt = $this->_pdo->query( 'SELECT * FROM carousels ORDER BY name ASC' );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => Carousel::fromArray( $row ), $rows );
	}

	/**
	 * @inheritDoc
	 */
	public function findById( int $id ): ?Carousel
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM carousels WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Carousel::fromArray( $row ) : null;
	}

	/**
	 * @inheritDoc
	 */
	public function findBySlug( string $slug ): ?Carousel
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM carousels WHERE slug = ? LIMIT 1' );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Carousel::fromArray( $row ) : null;
	}

	/**
	 * @inheritDoc
	 */
	public function create( Carousel $carousel ): Carousel
	{
		$now = new DateTimeImmutable();
		$carousel->setCreatedAt( $now );
		$carousel->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO carousels ( name, slug, description, display, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$carousel->getName(),
			$carousel->getSlug(),
			$carousel->getDescription(),
			$carousel->getDisplay(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$carousel->setId( (int) $this->_pdo->lastInsertId() );

		return $carousel;
	}

	/**
	 * @inheritDoc
	 */
	public function update( Carousel $carousel ): Carousel
	{
		$now = new DateTimeImmutable();
		$carousel->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE carousels
			SET name = ?, slug = ?, description = ?, display = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$carousel->getName(),
			$carousel->getSlug(),
			$carousel->getDescription(),
			$carousel->getDisplay(),
			$now->format( 'Y-m-d H:i:s' ),
			$carousel->getId()
		] );

		return $carousel;
	}

	/**
	 * @inheritDoc
	 */
	public function delete( Carousel $carousel ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM carousel_slides WHERE carousel_id = ?' );
		$stmt->execute( [ $carousel->getId() ] );

		$stmt = $this->_pdo->prepare( 'DELETE FROM carousels WHERE id = ?' );

		return $stmt->execute( [ $carousel->getId() ] );
	}

	/**
	 * @inheritDoc
	 */
	public function slugExists( string $slug, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM carousels WHERE slug = ? AND id != ?' );
			$stmt->execute( [ $slug, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM carousels WHERE slug = ?' );
			$stmt->execute( [ $slug ] );
		}

		return (int) $stmt->fetchColumn() > 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getSlides( int $carouselId ): array
	{
		$stmt = $this->_pdo->prepare(
			'SELECT * FROM carousel_slides WHERE carousel_id = ? ORDER BY sort_order ASC, id ASC'
		);
		$stmt->execute( [ $carouselId ] );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => CarouselSlide::fromArray( $row ), $rows );
	}

	/**
	 * @inheritDoc
	 */
	public function findSlideById( int $id ): ?CarouselSlide
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM carousel_slides WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? CarouselSlide::fromArray( $row ) : null;
	}

	/**
	 * @inheritDoc
	 */
	public function createSlide( CarouselSlide $slide ): CarouselSlide
	{
		$now = new DateTimeImmutable();
		$slide->setCreatedAt( $now );
		$slide->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO carousel_slides
			( carousel_id, image_url, alt_text, heading, caption, link_url, link_label, sort_order, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$slide->getCarouselId(),
			$slide->getImageUrl(),
			$slide->getAltText(),
			$slide->getHeading(),
			$slide->getCaption(),
			$slide->getLinkUrl(),
			$slide->getLinkLabel(),
			$slide->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$slide->setId( (int) $this->_pdo->lastInsertId() );

		return $slide;
	}

	/**
	 * @inheritDoc
	 */
	public function updateSlide( CarouselSlide $slide ): CarouselSlide
	{
		$now = new DateTimeImmutable();
		$slide->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE carousel_slides
			SET image_url = ?, alt_text = ?, heading = ?, caption = ?, link_url = ?, link_label = ?, sort_order = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$slide->getImageUrl(),
			$slide->getAltText(),
			$slide->getHeading(),
			$slide->getCaption(),
			$slide->getLinkUrl(),
			$slide->getLinkLabel(),
			$slide->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$slide->getId()
		] );

		return $slide;
	}

	/**
	 * @inheritDoc
	 */
	public function deleteSlide( CarouselSlide $slide ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM carousel_slides WHERE id = ?' );

		return $stmt->execute( [ $slide->getId() ] );
	}

	/**
	 * @inheritDoc
	 */
	public function countSlides( int $carouselId ): int
	{
		$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM carousel_slides WHERE carousel_id = ?' );
		$stmt->execute( [ $carouselId ] );

		return (int) $stmt->fetchColumn();
	}

	/**
	 * @inheritDoc
	 */
	public function nextSlideSortOrder( int $carouselId ): int
	{
		$stmt = $this->_pdo->prepare(
			'SELECT COALESCE( MAX( sort_order ), -1 ) + 1 FROM carousel_slides WHERE carousel_id = ?'
		);
		$stmt->execute( [ $carouselId ] );

		return (int) $stmt->fetchColumn();
	}
}
