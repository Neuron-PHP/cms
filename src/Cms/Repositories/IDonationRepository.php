<?php

namespace Neuron\Cms\Repositories;

/**
 * Contract for persisting and retrieving donations.
 *
 * @package Neuron\Cms\Repositories
 */
interface IDonationRepository
{
	/**
	 * Insert a donation and return its new id.
	 *
	 * @param array<string, mixed> $data Row data (payload should already be json_encoded)
	 * @return int The inserted row id
	 */
	public function create( array $data ): int;

	/**
	 * Find a donation by id.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array;

	/**
	 * Find a donation by its gateway checkout session id.
	 *
	 * @param string $sessionId
	 * @return array<string, mixed>|null
	 */
	public function findBySessionId( string $sessionId ): ?array;

	/**
	 * Mark a donation completed and store the resulting gateway identifiers.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data Optional payment_intent_id / subscription_id / amount_cents
	 * @return bool
	 */
	public function markCompleted( int $id, array $data = [] ): bool;

	/**
	 * Update only the status of a donation.
	 *
	 * @param int $id
	 * @param string $status
	 * @return bool
	 */
	public function updateStatus( int $id, string $status ): bool;

	/**
	 * Store the gateway checkout session id on a donation.
	 *
	 * @param int $id
	 * @param string $sessionId
	 * @return bool
	 */
	public function updateSession( int $id, string $sessionId ): bool;

	/**
	 * Delete a donation by id.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Return a page of donations, newest first, optionally filtered.
	 *
	 * @param int $page Page number (1-based)
	 * @param int $perPage Items per page
	 * @param string|null $status Optional status filter
	 * @param string|null $formKey Optional form key filter
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?string $status = null, ?string $formKey = null ): array;

	/**
	 * Distinct form keys present in stored donations (for filter UI).
	 *
	 * @return array<int, string>
	 */
	public function formKeys(): array;
}
