<?php

namespace Neuron\Cms\Repositories;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Testimonial;
use Neuron\Cms\Models\TestimonialQuote;
use Neuron\Data\Settings\SettingManager;
use PDO;

/**
 * Database-backed testimonial repository.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseTestimonialRepository implements ITestimonialRepository
{
	private PDO $_pdo;

	/**
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		Testimonial::setPdo( $this->_pdo );
		TestimonialQuote::setPdo( $this->_pdo );
	}

	public function all(): array
	{
		$stmt = $this->_pdo->query( 'SELECT * FROM testimonials ORDER BY name ASC' );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => Testimonial::fromArray( $row ), $rows );
	}

	public function findById( int $id ): ?Testimonial
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM testimonials WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Testimonial::fromArray( $row ) : null;
	}

	public function findBySlug( string $slug ): ?Testimonial
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM testimonials WHERE slug = ? LIMIT 1' );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Testimonial::fromArray( $row ) : null;
	}

	public function create( Testimonial $testimonial ): Testimonial
	{
		$now = new DateTimeImmutable();
		$testimonial->setCreatedAt( $now );
		$testimonial->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO testimonials ( name, slug, description, display, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$testimonial->getName(),
			$testimonial->getSlug(),
			$testimonial->getDescription(),
			$testimonial->getDisplay(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$testimonial->setId( (int) $this->_pdo->lastInsertId() );

		return $testimonial;
	}

	public function update( Testimonial $testimonial ): Testimonial
	{
		$now = new DateTimeImmutable();
		$testimonial->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE testimonials
			SET name = ?, slug = ?, description = ?, display = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$testimonial->getName(),
			$testimonial->getSlug(),
			$testimonial->getDescription(),
			$testimonial->getDisplay(),
			$now->format( 'Y-m-d H:i:s' ),
			$testimonial->getId()
		] );

		return $testimonial;
	}

	public function delete( Testimonial $testimonial ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM testimonial_quotes WHERE testimonial_id = ?' );
		$stmt->execute( [ $testimonial->getId() ] );

		$stmt = $this->_pdo->prepare( 'DELETE FROM testimonials WHERE id = ?' );

		return $stmt->execute( [ $testimonial->getId() ] );
	}

	public function slugExists( string $slug, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM testimonials WHERE slug = ? AND id != ?' );
			$stmt->execute( [ $slug, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM testimonials WHERE slug = ?' );
			$stmt->execute( [ $slug ] );
		}

		return (int) $stmt->fetchColumn() > 0;
	}

	public function getQuotes( int $testimonialId ): array
	{
		$stmt = $this->_pdo->prepare(
			'SELECT * FROM testimonial_quotes WHERE testimonial_id = ? ORDER BY sort_order ASC, id ASC'
		);
		$stmt->execute( [ $testimonialId ] );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => TestimonialQuote::fromArray( $row ), $rows );
	}

	public function findQuoteById( int $id ): ?TestimonialQuote
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM testimonial_quotes WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? TestimonialQuote::fromArray( $row ) : null;
	}

	public function createQuote( TestimonialQuote $quote ): TestimonialQuote
	{
		$now = new DateTimeImmutable();
		$quote->setCreatedAt( $now );
		$quote->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO testimonial_quotes
			( testimonial_id, quote, attribution, role, organization, image_url, sort_order, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$quote->getTestimonialId(),
			$quote->getQuote(),
			$quote->getAttribution(),
			$quote->getRole(),
			$quote->getOrganization(),
			$quote->getImageUrl(),
			$quote->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$quote->setId( (int) $this->_pdo->lastInsertId() );

		return $quote;
	}

	public function updateQuote( TestimonialQuote $quote ): TestimonialQuote
	{
		$now = new DateTimeImmutable();
		$quote->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE testimonial_quotes
			SET quote = ?, attribution = ?, role = ?, organization = ?, image_url = ?, sort_order = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$quote->getQuote(),
			$quote->getAttribution(),
			$quote->getRole(),
			$quote->getOrganization(),
			$quote->getImageUrl(),
			$quote->getSortOrder(),
			$now->format( 'Y-m-d H:i:s' ),
			$quote->getId()
		] );

		return $quote;
	}

	public function deleteQuote( TestimonialQuote $quote ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM testimonial_quotes WHERE id = ?' );

		return $stmt->execute( [ $quote->getId() ] );
	}

	public function countQuotes( int $testimonialId ): int
	{
		$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM testimonial_quotes WHERE testimonial_id = ?' );
		$stmt->execute( [ $testimonialId ] );

		return (int) $stmt->fetchColumn();
	}

	public function nextQuoteSortOrder( int $testimonialId ): int
	{
		$stmt = $this->_pdo->prepare(
			'SELECT COALESCE( MAX( sort_order ), -1 ) + 1 FROM testimonial_quotes WHERE testimonial_id = ?'
		);
		$stmt->execute( [ $testimonialId ] );

		return (int) $stmt->fetchColumn();
	}
}
