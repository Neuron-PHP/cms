<?php

namespace Neuron\Cms\Repositories;

/**
 * Contract for persisting and retrieving order line items.
 *
 * An order is a row in the `payments` table ( purpose = "order" ); each line of
 * that order is stored here, snapshotting the product details at purchase time.
 *
 * @package Neuron\Cms\Repositories
 */
interface IOrderItemRepository
{
	/**
	 * Insert one line item and return its new id.
	 *
	 * @param array<string, mixed> $data
	 * @return int
	 */
	public function create( array $data ): int;

	/**
	 * Insert many line items for a single order ( payment ).
	 *
	 * @param int $paymentId
	 * @param array<int, array<string, mixed>> $items
	 * @return void
	 */
	public function createForOrder( int $paymentId, array $items ): void;

	/**
	 * All line items for an order ( payment ), in insertion order.
	 *
	 * @param int $paymentId
	 * @return array<int, array<string, mixed>>
	 */
	public function findByPaymentId( int $paymentId ): array;
}
