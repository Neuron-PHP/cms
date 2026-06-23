<?php

namespace Neuron\Cms\Repositories;

/**
 * Contract for persisting and retrieving recurring subscriptions.
 *
 * A subscription owns the lifecycle of a recurring agreement ( a monthly
 * donation, a membership, ... ) and is linked to its individual charges in the
 * payments table by the gateway `subscription_id`.
 *
 * @package Neuron\Cms\Repositories
 */
interface ISubscriptionRepository
{
	/**
	 * Insert a subscription and return its new id.
	 *
	 * @param array<string, mixed> $data Row data (payload should already be json_encoded)
	 * @return int The inserted row id
	 */
	public function create( array $data ): int;

	/**
	 * Find a subscription by id.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array;

	/**
	 * Find a subscription by its gateway subscription id.
	 *
	 * @param string $subscriptionId
	 * @return array<string, mixed>|null
	 */
	public function findByGatewayId( string $subscriptionId ): ?array;

	/**
	 * Update mutable subscription state ( status, current_period_end,
	 * canceled_at ) by gateway subscription id.
	 *
	 * @param string $subscriptionId
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	public function updateState( string $subscriptionId, array $data ): bool;

	/**
	 * Delete a subscription by id.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Return a page of subscriptions, newest first, optionally filtered.
	 *
	 * @param int $page
	 * @param int $perPage
	 * @param string|null $status
	 * @param string|null $formKey
	 * @param string|null $purpose
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?string $status = null, ?string $formKey = null, ?string $purpose = null ): array;

	/**
	 * Distinct form keys present in stored subscriptions (for filter UI).
	 *
	 * @return array<int, string>
	 */
	public function formKeys(): array;
}
