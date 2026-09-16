<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Testimonial;
use Neuron\Cms\Models\TestimonialQuote;

/**
 * Repository interface for named testimonials and their quotes.
 *
 * @package Neuron\Cms\Repositories
 */
interface ITestimonialRepository
{
	/**
	 * @return Testimonial[]
	 */
	public function all(): array;

	public function findById( int $id ): ?Testimonial;

	public function findBySlug( string $slug ): ?Testimonial;

	public function create( Testimonial $testimonial ): Testimonial;

	public function update( Testimonial $testimonial ): Testimonial;

	public function delete( Testimonial $testimonial ): bool;

	public function slugExists( string $slug, ?int $excludeId = null ): bool;

	/**
	 * @return TestimonialQuote[]
	 */
	public function getQuotes( int $testimonialId ): array;

	public function findQuoteById( int $id ): ?TestimonialQuote;

	public function createQuote( TestimonialQuote $quote ): TestimonialQuote;

	public function updateQuote( TestimonialQuote $quote ): TestimonialQuote;

	public function deleteQuote( TestimonialQuote $quote ): bool;

	public function countQuotes( int $testimonialId ): int;

	public function nextQuoteSortOrder( int $testimonialId ): int;
}
