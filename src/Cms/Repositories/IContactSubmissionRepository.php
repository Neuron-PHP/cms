<?php

namespace Neuron\Cms\Repositories;

/**
 * Contract for persisting and retrieving contact form submissions.
 *
 * @package Neuron\Cms\Repositories
 */
interface IContactSubmissionRepository
{
	/**
	 * Insert a submission and return its new id.
	 *
	 * @param array<string, mixed> $data Row data (payload should already be json_encoded)
	 * @return int The inserted row id
	 */
	public function create( array $data ): int;

	/**
	 * Find a submission by id.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null Associative row, or null when not found
	 */
	public function findById( int $id ): ?array;

	/**
	 * Mark a submission as delivered (sets delivered + delivered_at).
	 *
	 * @param int $id
	 * @return bool
	 */
	public function markDelivered( int $id ): bool;

	/**
	 * Delete a submission by id.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Return a page of submissions, newest first, optionally filtered by form key.
	 *
	 * @param int $page Page number (1-based)
	 * @param int $perPage Items per page
	 * @param string|null $formKey Optional form key filter
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?string $formKey = null ): array;

	/**
	 * Distinct form keys present in stored submissions (for filter UI).
	 *
	 * @return array<int, string>
	 */
	public function formKeys(): array;
}
