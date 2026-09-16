<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Carousel;
use Neuron\Cms\Models\CarouselSlide;

/**
 * Repository interface for named carousels and their slides.
 *
 * @package Neuron\Cms\Repositories
 */
interface ICarouselRepository
{
	/**
	 * @return Carousel[]
	 */
	public function all(): array;

	public function findById( int $id ): ?Carousel;

	public function findBySlug( string $slug ): ?Carousel;

	public function create( Carousel $carousel ): Carousel;

	public function update( Carousel $carousel ): Carousel;

	public function delete( Carousel $carousel ): bool;

	public function slugExists( string $slug, ?int $excludeId = null ): bool;

	/**
	 * Slides of a carousel, ordered for display.
	 *
	 * @return CarouselSlide[]
	 */
	public function getSlides( int $carouselId ): array;

	public function findSlideById( int $id ): ?CarouselSlide;

	public function createSlide( CarouselSlide $slide ): CarouselSlide;

	public function updateSlide( CarouselSlide $slide ): CarouselSlide;

	public function deleteSlide( CarouselSlide $slide ): bool;

	public function countSlides( int $carouselId ): int;

	public function nextSlideSortOrder( int $carouselId ): int;
}
