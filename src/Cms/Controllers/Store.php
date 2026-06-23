<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IOrderItemRepository;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Repositories\IProductRepository;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Payment\PaymentGatewayFactory;
use Neuron\Cms\Services\Store\CartService;
use Neuron\Cms\Services\Store\StoreService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Payments\Dto\CheckoutSessionRequest;
use Neuron\Payments\Dto\Frequency;
use Neuron\Payments\Dto\LineItem;
use Neuron\Payments\Dto\Money;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

/**
 * Public storefront controller.
 *
 * Drives the catalog ( [product] / [products] shortcodes and /store pages ), the
 * session cart, and multi-item hosted checkout. An order is recorded in the
 * shared `payments` table ( purpose = "order" ) with one `order_items` row per
 * line, so it reuses the payment + webhook lifecycle: completion, receipts, and
 * admin review all flow through the existing Payments machinery. The webhook
 * itself lives on the Payments controller.
 *
 * The optional neuron-php/payments package is referenced only inside method
 * bodies that run after the gateway is confirmed available, so the controller
 * loads and the CMS works even when payments are not enabled.
 *
 * @package Neuron\Cms\Controllers
 */
class Store extends Content
{
	private IProductRepository $_products;
	private IPaymentRepository $_payments;
	private IOrderItemRepository $_orderItems;
	private PaymentGatewayFactory $_gatewayFactory;
	private StoreService $_storeService;
	private CartService $_cart;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IProductRepository $products
	 * @param IPaymentRepository $payments
	 * @param IOrderItemRepository $orderItems
	 * @param PaymentGatewayFactory $gatewayFactory
	 * @param StoreService|null $storeService
	 * @param CartService|null $cart
	 */
	public function __construct(
		IMvcApplication       $app,
		SettingManager        $settings,
		SessionManager        $sessionManager,
		IProductRepository    $products,
		IPaymentRepository    $payments,
		IOrderItemRepository  $orderItems,
		PaymentGatewayFactory $gatewayFactory,
		?StoreService         $storeService = null,
		?CartService          $cart = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_products       = $products;
		$this->_payments       = $payments;
		$this->_orderItems     = $orderItems;
		$this->_gatewayFactory = $gatewayFactory;
		$this->_storeService   = $storeService ?? new StoreService( $settings );
		$this->_cart           = $cart ?? new CartService( $products, $sessionManager );
	}

	/**
	 * Issue a fresh CSRF token for store forms ( used by cached pages ).
	 */
	#[Get('/store/token', name: 'store_token')]
	public function token( Request $request ): string
	{
		$csrf = new CsrfToken( $this->getSessionManager() );

		return $this->renderJson( HttpResponseStatus::OK, [ 'token' => $csrf->getToken() ] );
	}

	/**
	 * Catalog listing of active products.
	 */
	#[Get('/store', name: 'store_index')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		[ $success, $error ] = $this->consumeFlash();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | ' . $this->_storeService->getStoreTitle(),
				'Description' => 'Shop',
				'Products'    => $this->_products->allActive(),
				'StoreTitle'  => $this->_storeService->getStoreTitle(),
				'Cart'        => $this->_cart->resolve(),
				'Success'     => $success,
				'Error'       => $error
			],
			'index',
			'default'
		);
	}

	/**
	 * Single product detail page.
	 */
	#[Get('/store/product/:slug', name: 'store_product')]
	public function product( Request $request ): string
	{
		$this->initializeCsrfToken();

		$slug    = (string) $request->getRouteParameter( 'slug' );
		$product = $this->_products->findBySlug( $slug );

		if( $product === null || (int) ( $product['active'] ?? 0 ) !== 1 )
		{
			return $this->renderHtml(
				HttpResponseStatus::NOT_FOUND,
				[
					'Title'       => $this->getName() . ' | Not Found',
					'Description' => 'Product not found',
					'Message'     => 'Sorry, that product is not available.'
				],
				'not_found',
				'default'
			);
		}

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | ' . ( $product['name'] ?? 'Product' ),
				'Description' => substr( strip_tags( (string) ( $product['description'] ?? '' ) ), 0, 160 ),
				'Product'     => $product,
				'Cart'        => $this->_cart->resolve()
			],
			'product',
			'default'
		);
	}

	/**
	 * Cart contents page.
	 */
	#[Get('/cart', name: 'cart_index')]
	public function cart( Request $request ): string
	{
		$this->initializeCsrfToken();

		[ $success, $error ] = $this->consumeFlash();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Cart',
				'Description' => 'Your cart',
				'Cart'        => $this->_cart->resolve(),
				'Success'     => $success,
				'Error'       => $error
			],
			'cart',
			'default'
		);
	}

	/**
	 * Add a product to the cart.
	 */
	#[Post('/cart/add', name: 'cart_add', filters: ['csrf'])]
	public function add( Request $request ): never
	{
		$productId = (int) ( $request->post( 'product_id', 0 ) ?? 0 );
		$quantity  = (int) ( $request->post( 'quantity', 1 ) ?? 1 );

		$product = $productId > 0 ? $this->_products->findById( $productId ) : null;

		if( $product === null || (int) ( $product['active'] ?? 0 ) !== 1 )
		{
			$this->redirectBack( '/store', [ 'error', 'That product is not available.' ] );
		}

		$this->_cart->add( $productId, $quantity > 0 ? $quantity : 1 );

		$this->redirectToUrl( '/cart', [ 'success', 'Added to your cart.' ] );
	}

	/**
	 * Update line quantities from the cart page.
	 */
	#[Post('/cart/update', name: 'cart_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$quantities = $request->post( 'quantities', [] );

		if( is_array( $quantities ) )
		{
			foreach( $quantities as $productId => $qty )
			{
				$this->_cart->setQuantity( (int) $productId, (int) $qty );
			}
		}

		$this->redirectToUrl( '/cart', [ 'success', 'Cart updated.' ] );
	}

	/**
	 * Remove a single line from the cart.
	 */
	#[Post('/cart/remove', name: 'cart_remove', filters: ['csrf'])]
	public function remove( Request $request ): never
	{
		$this->_cart->remove( (int) ( $request->post( 'product_id', 0 ) ?? 0 ) );

		$this->redirectToUrl( '/cart' );
	}

	/**
	 * Create the order and open hosted checkout for the current cart.
	 */
	#[Post('/store/checkout', name: 'store_checkout', filters: ['csrf'])]
	public function checkout( Request $request ): never
	{
		$cart = $this->_cart->resolve();

		if( $cart['items'] === [] )
		{
			$this->redirectToUrl( '/cart', [ 'error', 'Your cart is empty.' ] );
		}

		$gateway = $this->_gatewayFactory->create();

		if( $gateway === null )
		{
			Log::error( 'Store checkout attempted but no payment gateway is configured.' );
			$this->redirectToUrl( '/cart', [ 'error', 'Online payments are temporarily unavailable. Please try again later.' ] );
		}

		$name  = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );
		$email = trim( (string) ( $request->post( 'email', '' ) ?? '' ) );

		if( $name === '' || !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			$this->redirectToUrl( '/cart', [ 'error', 'Please enter your name and a valid email address.' ] );
		}

		$currency = $cart['currency'] ?? $this->_storeService->getCurrency();
		$total    = (int) $cart['total_cents'];

		$summary = [
			'name'  => $name,
			'email' => $email,
			'items' => array_map( static function( array $item ): array {
				return [
					'name'              => $item['name'],
					'sku'               => $item['sku'],
					'quantity'          => $item['quantity'],
					'unit_amount_cents' => $item['unit_amount_cents']
				];
			}, $cart['items'] )
		];

		$paymentId = $this->persistOrder( $request, [
			'amount_cents' => $total,
			'currency'     => $currency,
			'payer_name'   => $name,
			'payer_email'  => $email,
			'payload'      => json_encode( $summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		] );

		if( $paymentId === null )
		{
			$this->redirectToUrl( '/cart', [ 'error', 'We could not start your order. Please try again.' ] );
		}

		$this->persistOrderItems( $paymentId, $cart['items'] );

		try
		{
			$lineItems = [];

			foreach( $cart['items'] as $item )
			{
				$lineItems[] = new LineItem(
					name:       (string) $item['name'],
					unitAmount: new Money( (int) $item['unit_amount_cents'], $currency ),
					quantity:   (int) $item['quantity']
				);
			}

			$sessionRequest = new CheckoutSessionRequest(
				amount:        new Money( $total, $currency ),
				frequency:     Frequency::OneTime,
				successUrl:    $this->successUrl(),
				cancelUrl:     $this->absoluteUrl( $this->_storeService->getCancelUrl() ),
				productName:   'Order',
				customerEmail: $email,
				metadata:      [ 'payment_id' => $paymentId, 'form_key' => 'order' ],
				lineItems:     $lineItems
			);

			$session = $gateway->createCheckoutSession( $sessionRequest );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Store checkout session failed: ' . $e->getMessage() );
			$this->_payments->updateStatus( $paymentId, 'failed' );
			$this->redirectToUrl( '/cart', [ 'error', 'We could not reach the payment processor. Please try again.' ] );
		}

		try
		{
			$this->_payments->updateSession( $paymentId, $session->id );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Store updateSession failed: ' . $e->getMessage() );
		}

		$this->redirectToUrl( $session->url );
	}

	/**
	 * Order confirmation page ( presentational; webhook is source of truth ).
	 */
	#[Get('/store/success', name: 'store_success')]
	public function success( Request $request ): string
	{
		$sessionId = (string) ( $request->get( 'session_id', '' ) ?? '' );
		$order     = $sessionId !== '' ? $this->_payments->findBySessionId( $sessionId ) : null;
		$items     = $order !== null ? $this->_orderItems->findByPaymentId( (int) $order['id'] ) : [];

		// The buyer completed checkout; empty the cart on return.
		$this->_cart->clear();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Thank You',
				'Description' => 'Order received',
				'Message'     => 'Thank you for your order!',
				'Order'       => $order,
				'Items'       => $items
			],
			'success',
			'default'
		);
	}

	/**
	 * Page shown when the buyer cancels hosted checkout.
	 */
	#[Get('/store/cancel', name: 'store_cancel')]
	public function cancel( Request $request ): string
	{
		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Order Canceled',
				'Description' => 'Order canceled',
				'Message'     => 'Your order was canceled and you have not been charged. Your cart is still saved.'
			],
			'cancel',
			'default'
		);
	}

	/**
	 * Read and clear the generic success / error flash messages.
	 *
	 * @return array{0: ?string, 1: ?string} [success, error]
	 */
	private function consumeFlash(): array
	{
		$session = $this->getSessionManager();

		$success = $session->getFlash( 'success' );
		$error   = $session->getFlash( 'error' );

		return [
			$success !== null ? (string) $success : null,
			$error !== null ? (string) $error : null
		];
	}

	/**
	 * Persist a pending order ( a payment row, purpose = order ), returning its id.
	 *
	 * @param Request $request
	 * @param array<string, mixed> $data
	 * @return int|null
	 */
	private function persistOrder( Request $request, array $data ): ?int
	{
		try
		{
			return $this->_payments->create( array_merge( [
				'purpose'    => 'order',
				'form_key'   => 'order',
				'provider'   => (string) ( $this->_settings->get( 'payments', 'provider' ) ?? 'stripe' ),
				'type'       => 'one_time',
				'frequency'  => 'one_time',
				'status'     => 'pending',
				'ip_address' => $request->getClientIp(),
				'user_agent' => substr( (string) $request->server( 'HTTP_USER_AGENT', '' ), 0, 500 )
			], $data ) );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Order persistence failed: ' . $e->getMessage() );

			return null;
		}
	}

	/**
	 * Snapshot the cart lines into order_items for the order.
	 *
	 * @param int $paymentId
	 * @param array<int, array<string, mixed>> $items
	 * @return void
	 */
	private function persistOrderItems( int $paymentId, array $items ): void
	{
		try
		{
			$rows = [];

			foreach( $items as $item )
			{
				$rows[] = [
					'product_id'        => $item['product_id'] ?? null,
					'name'              => $item['name'] ?? '',
					'sku'               => $item['sku'] ?? null,
					'unit_amount_cents' => (int) ( $item['unit_amount_cents'] ?? 0 ),
					'quantity'          => (int) ( $item['quantity'] ?? 1 ),
					'currency'          => $item['currency'] ?? 'usd'
				];
			}

			$this->_orderItems->createForOrder( $paymentId, $rows );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Order items persistence failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Build the absolute success URL with the gateway session placeholder.
	 *
	 * @return string
	 */
	private function successUrl(): string
	{
		$base = $this->absoluteUrl( $this->_storeService->getSuccessUrl() );
		$glue = str_contains( $base, '?' ) ? '&' : '?';

		return $base . $glue . 'session_id={CHECKOUT_SESSION_ID}';
	}

	/**
	 * Turn a path into an absolute URL using the current request host.
	 *
	 * @param string $path
	 * @return string
	 */
	private function absoluteUrl( string $path ): string
	{
		if( str_starts_with( $path, 'http://' ) || str_starts_with( $path, 'https://' ) )
		{
			return $path;
		}

		$https  = ( $_SERVER['HTTPS'] ?? '' ) === 'on' || ( $_SERVER['SERVER_PORT'] ?? '' ) === '443';
		$scheme = $https ? 'https' : 'http';
		$host   = (string) ( $_SERVER['HTTP_HOST'] ?? ( $this->_settings->get( 'site', 'url' ) ?? 'localhost' ) );

		return $scheme . '://' . $host . '/' . ltrim( $path, '/' );
	}
}
