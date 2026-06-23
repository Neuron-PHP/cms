<?php

namespace Neuron\Cms\Repositories;

/**
 * Contract for persisting and retrieving storefront products.
 *
 * Products are fixed-price, one-time catalog items sold through the cart and
 * hosted checkout. Rows are plain associative arrays, mirroring the other
 * payment-domain repositories.
 *
 * @package Neuron\Cms\Repositories
 */
interface IProductRepository
{
	/**
	 * Insert a product and return its new id.
	 *
	 * @param array<string, mixed> $data
	 * @return int
	 */
	public function create( array $data ): int;

	/**
	 * Update a product by id.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	public function update( int $id, array $data ): bool;

	/**
	 * Find a product by id.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array;

	/**
	 * Find a product by slug.
	 *
	 * @param string $slug
	 * @return array<string, mixed>|null
	 */
	public function findBySlug( string $slug ): ?array;

	/**
	 * All active products, ordered for display.
	 *
	 * @param int|null $limit Optional maximum number of products
	 * @return array<int, array<string, mixed>>
	 */
	public function allActive( ?int $limit = null ): array;

	/**
	 * Delete a product by id.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Return a page of products, newest/sorted first ( admin listing ).
	 *
	 * @param int $page
	 * @param int $perPage
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
	 */
	public function paginate( int $page = 1, int $perPage = 25 ): array;
}
