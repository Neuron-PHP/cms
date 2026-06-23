<?php

namespace Neuron\Cms\Repositories;

/**
 * Contract for persisting and retrieving payments.
 *
 * A payment is a single charge: a one-time payment or one invoice of a
 * recurring subscription ( the initial charge and every renewal each get their
 * own row ). The `purpose` column tags what the payment is for ( donation,
 * membership, ... ) so one store serves every payment type.
 *
 * @package Neuron\Cms\Repositories
 */
interface IPaymentRepository
{
	/**
	 * Insert a payment and return its new id.
	 *
	 * @param array<string, mixed> $data Row data (payload should already be json_encoded)
	 * @return int The inserted row id
	 */
	public function create( array $data ): int;

	/**
	 * Find a payment by id.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public function findById( int $id ): ?array;

	/**
	 * Find a payment by its gateway checkout session id.
	 *
	 * @param string $sessionId
	 * @return array<string, mixed>|null
	 */
	public function findBySessionId( string $sessionId ): ?array;

	/**
	 * Find the most recent payment linked to a gateway subscription id.
	 *
	 * @param string $subscriptionId
	 * @return array<string, mixed>|null
	 */
	public function findBySubscriptionId( string $subscriptionId ): ?array;

	/**
	 * Find a payment by its gateway invoice id.
	 *
	 * @param string $invoiceId
	 * @return array<string, mixed>|null
	 */
	public function findByInvoiceId( string $invoiceId ): ?array;

	/**
	 * Mark a payment completed and store the resulting gateway identifiers.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data Optional payment_intent_id / invoice_id / subscription_id / amount_cents / type
	 * @return bool
	 */
	public function markCompleted( int $id, array $data = [] ): bool;

	/**
	 * Update only the status of a payment.
	 *
	 * @param int $id
	 * @param string $status
	 * @return bool
	 */
	public function updateStatus( int $id, string $status ): bool;

	/**
	 * Store the gateway checkout session id on a payment.
	 *
	 * @param int $id
	 * @param string $sessionId
	 * @return bool
	 */
	public function updateSession( int $id, string $sessionId ): bool;

	/**
	 * Delete a payment by id.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Return a page of payments, newest first, optionally filtered.
	 *
	 * @param int $page Page number (1-based)
	 * @param int $perPage Items per page
	 * @param string|null $status Optional status filter
	 * @param string|null $formKey Optional form key filter
	 * @param string|null $purpose Optional purpose filter
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?string $status = null, ?string $formKey = null, ?string $purpose = null ): array;

	/**
	 * Distinct form keys present in stored payments (for filter UI).
	 *
	 * @return array<int, string>
	 */
	public function formKeys(): array;
}
