<?php

namespace Neuron\Cms\Services\Store;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IProductRepository;

/**
 * Session-backed shopping cart.
 *
 * The cart stores only product ids and quantities in the session; prices and
 * product details are always re-resolved from the catalog at read time via
 * {@see resolve()}, so a cart never charges a stale price and silently drops
 * products that have been removed or deactivated.
 *
 * @package Neuron\Cms\Services\Store
 */
class CartService
{
	private const SESSION_KEY = 'store_cart';
	private const MAX_QTY     = 999;

	private IProductRepository $_products;
	private ?SessionManager $_session;

	/**
	 * @param IProductRepository $products
	 * @param SessionManager|null $session Injectable for testing
	 */
	public function __construct( IProductRepository $products, ?SessionManager $session = null )
	{
		$this->_products = $products;
		$this->_session  = $session;
	}

	/**
	 * Add quantity for a product, merging with any existing quantity.
	 *
	 * @param int $productId
	 * @param int $quantity
	 * @return void
	 */
	public function add( int $productId, int $quantity = 1 ): void
	{
		if( $productId <= 0 )
		{
			return;
		}

		$items = $this->rawItems();
		$next  = ( $items[ $productId ] ?? 0 ) + max( 1, $quantity );

		$items[ $productId ] = min( self::MAX_QTY, $next );

		$this->store( $items );
	}

	/**
	 * Set an exact quantity for a product ( zero or less removes it ).
	 *
	 * @param int $productId
	 * @param int $quantity
	 * @return void
	 */
	public function setQuantity( int $productId, int $quantity ): void
	{
		$items = $this->rawItems();

		if( $quantity <= 0 )
		{
			unset( $items[ $productId ] );
		}
		else
		{
			$items[ $productId ] = min( self::MAX_QTY, $quantity );
		}

		$this->store( $items );
	}

	/**
	 * Remove a product from the cart.
	 *
	 * @param int $productId
	 * @return void
	 */
	public function remove( int $productId ): void
	{
		$items = $this->rawItems();
		unset( $items[ $productId ] );
		$this->store( $items );
	}

	/**
	 * Empty the cart.
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$session = $this->session();

		if( $session !== null )
		{
			$session->remove( self::SESSION_KEY );
		}
	}

	/**
	 * Raw cart contents as productId => quantity.
	 *
	 * @return array<int, int>
	 */
	public function rawItems(): array
	{
		$session = $this->session();

		if( $session === null )
		{
			return [];
		}

		$items = $session->get( self::SESSION_KEY, [] );

		if( !is_array( $items ) )
		{
			return [];
		}

		$clean = [];

		foreach( $items as $id => $qty )
		{
			$id  = (int) $id;
			$qty = (int) $qty;

			if( $id > 0 && $qty > 0 )
			{
				$clean[ $id ] = min( self::MAX_QTY, $qty );
			}
		}

		return $clean;
	}

	/**
	 * Whether the cart has no items.
	 *
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return $this->rawItems() === [];
	}

	/**
	 * Total number of units across all lines.
	 *
	 * @return int
	 */
	public function totalQuantity(): int
	{
		return array_sum( $this->rawItems() );
	}

	/**
	 * Resolve the cart against the current catalog.
	 *
	 * Drops products that no longer exist or are inactive, recomputing line and
	 * grand totals from live prices.
	 *
	 * @return array{
	 *     items: array<int, array<string, mixed>>,
	 *     total_cents: int,
	 *     count: int,
	 *     currency: string
	 * }
	 */
	public function resolve(): array
	{
		$items    = [];
		$total    = 0;
		$count    = 0;
		$currency = 'usd';
		$prune    = false;
		$raw      = $this->rawItems();

		foreach( $raw as $productId => $quantity )
		{
			$product = $this->_products->findById( $productId );

			if( $product === null || (int) ( $product['active'] ?? 0 ) !== 1 )
			{
				$prune = true;
				continue;
			}

			$unit      = (int) ( $product['price_cents'] ?? 0 );
			$lineTotal = $unit * $quantity;
			$currency  = (string) ( $product['currency'] ?? $currency );

			$items[] = [
				'product'          => $product,
				'product_id'       => $productId,
				'name'             => (string) ( $product['name'] ?? '' ),
				'sku'              => $product['sku'] ?? null,
				'unit_amount_cents'=> $unit,
				'quantity'         => $quantity,
				'line_total_cents' => $lineTotal,
				'currency'         => $currency
			];

			$total += $lineTotal;
			$count += $quantity;
		}

		// Persist the pruned cart so removed products do not linger.
		if( $prune )
		{
			$kept = [];

			foreach( $items as $item )
			{
				$kept[ $item['product_id'] ] = $item['quantity'];
			}

			$this->store( $kept );
		}

		return [
			'items'       => $items,
			'total_cents' => $total,
			'count'       => $count,
			'currency'    => $currency
		];
	}

	/**
	 * Persist the raw cart map.
	 *
	 * @param array<int, int> $items
	 * @return void
	 */
	private function store( array $items ): void
	{
		$session = $this->session();

		if( $session !== null )
		{
			$session->set( self::SESSION_KEY, $items );
		}
	}

	/**
	 * Lazily resolve a started session manager.
	 *
	 * @return SessionManager|null
	 */
	private function session(): ?SessionManager
	{
		if( $this->_session === null )
		{
			$this->_session = new SessionManager();
		}

		try
		{
			if( !$this->_session->isStarted() )
			{
				$this->_session->start();
			}
		}
		catch( \Throwable $e )
		{
			return null;
		}

		return $this->_session;
	}
}
