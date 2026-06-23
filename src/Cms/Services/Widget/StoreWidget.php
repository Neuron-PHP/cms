<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Repositories\IProductRepository;
use Neuron\Cms\Services\Store\CartService;
use Neuron\Cms\Services\Store\StoreService;

/**
 * Storefront widgets / shortcodes.
 *
 * Renders the catalog and cart entry points. A single class backs three
 * shortcodes, dispatched by {@see WidgetRenderer}:
 *
 *   [products]                 -> grid of active products with add-to-cart
 *   [products limit="6"]       -> capped grid
 *   [product id="3"]           -> a single product card
 *   [product slug="t-shirt"]   -> a single product card by slug
 *   [cart]                     -> a cart summary / link button
 *
 * Add-to-cart posts to /cart/add. A CSRF token is fetched client-side from
 * /store/token so the markup stays valid even when the page is cached.
 *
 * @package Neuron\Cms\Services\Widget
 */
class StoreWidget
{
	private const CURRENCY_SYMBOLS = [
		'usd' => '$',
		'eur' => '€',
		'gbp' => '£',
		'cad' => '$',
		'aud' => '$'
	];

	private IProductRepository $_products;
	private StoreService $_store;
	private CartService $_cart;

	public function __construct( IProductRepository $products, StoreService $store, CartService $cart )
	{
		$this->_products = $products;
		$this->_store    = $store;
		$this->_cart     = $cart;
	}

	/**
	 * Render the product grid.
	 *
	 * @param array<string, mixed> $attrs
	 * @return string
	 */
	public function renderProducts( array $attrs ): string
	{
		$limit    = isset( $attrs['limit'] ) ? max( 1, (int) $attrs['limit'] ) : null;
		$products = $this->_products->allActive( $limit );
		$title    = (string) ( $attrs['title'] ?? $this->_store->getStoreTitle() );

		$html = '<div class="store-products mb-4">';

		if( $title !== '' )
		{
			$html .= '<h3 class="store-products-title mb-3">' . $this->esc( $title ) . '</h3>';
		}

		if( $products === [] )
		{
			$html .= '<p class="text-muted">No products are available right now.</p></div>';

			return $html;
		}

		$html .= '<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">';

		foreach( $products as $product )
		{
			$html .= '<div class="col">' . $this->card( $product ) . '</div>';
		}

		$html .= '</div></div>';
		$html .= $this->tokenScript();

		return $html;
	}

	/**
	 * Render a single product card.
	 *
	 * @param array<string, mixed> $attrs
	 * @return string
	 */
	public function renderProduct( array $attrs ): string
	{
		$product = null;

		if( !empty( $attrs['id'] ) )
		{
			$product = $this->_products->findById( (int) $attrs['id'] );
		}
		elseif( !empty( $attrs['slug'] ) )
		{
			$product = $this->_products->findBySlug( (string) $attrs['slug'] );
		}

		if( $product === null || (int) ( $product['active'] ?? 0 ) !== 1 )
		{
			return "<!-- store widget: unknown or inactive product -->";
		}

		$html  = '<div class="store-product mb-4" style="max-width:24rem;">' . $this->card( $product ) . '</div>';
		$html .= $this->tokenScript();

		return $html;
	}

	/**
	 * Render a cart summary / link.
	 *
	 * @param array<string, mixed> $attrs
	 * @return string
	 */
	public function renderCart( array $attrs ): string
	{
		$cart  = $this->_cart->resolve();
		$count = (int) $cart['count'];
		$label = (string) ( $attrs['label'] ?? 'View cart' );

		$total = $this->money( (int) $cart['total_cents'], (string) $cart['currency'] );

		return '<a href="/cart" class="btn btn-outline-primary store-cart-link">'
			. '<i class="bi bi-cart"></i> ' . $this->esc( $label )
			. ' <span class="badge bg-primary">' . $count . '</span>'
			. ( $count > 0 ? ' <span class="ms-1">' . $this->esc( $total ) . '</span>' : '' )
			. '</a>';
	}

	/**
	 * Render a product card with its add-to-cart form.
	 *
	 * @param array<string, mixed> $product
	 * @return string
	 */
	private function card( array $product ): string
	{
		$id    = (int) ( $product['id'] ?? 0 );
		$name  = (string) ( $product['name'] ?? '' );
		$slug  = (string) ( $product['slug'] ?? '' );
		$price = $this->money( (int) ( $product['price_cents'] ?? 0 ), (string) ( $product['currency'] ?? 'usd' ) );
		$image = (string) ( $product['image_url'] ?? '' );
		$desc  = (string) ( $product['description'] ?? '' );

		$html  = '<div class="card h-100 store-product-card">';

		if( $image !== '' )
		{
			$html .= '<a href="/store/product/' . $this->esc( $slug ) . '">'
				. '<img src="' . $this->esc( $image ) . '" class="card-img-top" alt="' . $this->esc( $name ) . '" style="object-fit:cover;height:12rem;">'
				. '</a>';
		}

		$html .= '<div class="card-body d-flex flex-column">';
		$html .= '<h5 class="card-title"><a href="/store/product/' . $this->esc( $slug ) . '" class="text-decoration-none">' . $this->esc( $name ) . '</a></h5>';

		if( $desc !== '' )
		{
			$html .= '<p class="card-text text-muted small">' . $this->esc( $this->truncate( strip_tags( $desc ), 120 ) ) . '</p>';
		}

		$html .= '<div class="mt-auto">';
		$html .= '<p class="fw-bold mb-2 store-product-price">' . $this->esc( $price ) . '</p>';
		$html .= '<form method="POST" action="/cart/add" data-store-form>';
		$html .= '<input type="hidden" name="csrf_token" value="">';
		$html .= '<input type="hidden" name="product_id" value="' . $id . '">';
		$html .= '<div class="input-group input-group-sm">';
		$html .= '<input type="number" name="quantity" value="1" min="1" class="form-control" style="max-width:5rem;" aria-label="Quantity">';
		$html .= '<button type="submit" class="btn btn-primary">Add to cart</button>';
		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Format minor units as a display amount.
	 *
	 * @param int $cents
	 * @param string $currency
	 * @return string
	 */
	private function money( int $cents, string $currency ): string
	{
		$currency = strtolower( $currency );
		$symbol   = self::CURRENCY_SYMBOLS[ $currency ] ?? '';

		return $symbol . number_format( $cents / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
	}

	/**
	 * Truncate text to a length on a word boundary.
	 *
	 * @param string $text
	 * @param int $length
	 * @return string
	 */
	private function truncate( string $text, int $length ): string
	{
		$text = trim( $text );

		if( strlen( $text ) <= $length )
		{
			return $text;
		}

		return rtrim( substr( $text, 0, $length ) ) . '…';
	}

	/**
	 * Inline script ( once per page ) that fetches a fresh CSRF token and injects
	 * it into every store form, keeping cached markup valid.
	 *
	 * @return string
	 */
	private function tokenScript(): string
	{
		return <<<'HTML'
<script>
(function() {
	if( window.__storeFormTokenInit ) { return; }
	window.__storeFormTokenInit = true;
	document.addEventListener('DOMContentLoaded', function() {
		fetch('/store/token', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if( !data || !data.token ) { return; }
				document.querySelectorAll('form[data-store-form] input[name="csrf_token"]').forEach(function(input) {
					input.value = data.token;
				});
			})
			.catch(function() {});
	});
})();
</script>
HTML;
	}

	/**
	 * HTML-escape helper.
	 *
	 * @param string $value
	 * @return string
	 */
	private function esc( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
