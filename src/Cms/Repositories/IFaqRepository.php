<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Faq;
use Neuron\Cms\Models\FaqItem;

/**
 * Repository interface for named FAQ groups and their items.
 *
 * @package Neuron\Cms\Repositories
 */
interface IFaqRepository
{
	/**
	 * @return Faq[]
	 */
	public function all(): array;

	public function findById( int $id ): ?Faq;

	public function findBySlug( string $slug ): ?Faq;

	public function create( Faq $faq ): Faq;

	public function update( Faq $faq ): Faq;

	public function delete( Faq $faq ): bool;

	public function slugExists( string $slug, ?int $excludeId = null ): bool;

	/**
	 * @return FaqItem[]
	 */
	public function getItems( int $faqId ): array;

	public function findItemById( int $id ): ?FaqItem;

	public function createItem( FaqItem $item ): FaqItem;

	public function updateItem( FaqItem $item ): FaqItem;

	public function deleteItem( FaqItem $item ): bool;

	public function countItems( int $faqId ): int;

	public function nextItemSortOrder( int $faqId ): int;
}
