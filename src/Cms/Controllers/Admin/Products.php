<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\IProductRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin storefront product management.
 *
 * CRUD for the catalog sold through the [product] / [products] shortcodes and
 * the /store pages. Prices are entered in major units and stored as integer
 * minor units ( cents ).
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Products extends Content
{
	private const PER_PAGE = 25;

	private IProductRepository $_repository;
	private SlugGenerator $_slugs;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IProductRepository $repository
	 * @param SlugGenerator|null $slugs
	 */
	public function __construct(
		IMvcApplication    $app,
		SettingManager     $settings,
		SessionManager     $sessionManager,
		IProductRepository $repository,
		?SlugGenerator     $slugs = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_slugs      = $slugs ?? new SlugGenerator();
	}

	/**
	 * List products.
	 */
	#[Get('/products', name: 'admin_products')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$session = $this->getSessionManager();
		$page    = max( 1, (int) ( $request->get( 'page', 1 ) ?? 1 ) );
		$result  = $this->_repository->paginate( $page, self::PER_PAGE );

		return $this->view()
			->title( 'Products | Admin' )
			->description( 'Manage products' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'products' => $result['items'],
				'total'    => $result['total'],
				'page'     => $result['page'],
				'pages'    => $result['pages'],
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show the create form.
	 */
	#[Get('/products/create', name: 'admin_products_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Product | Admin' )
			->description( 'Add a product' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [ 'product' => null ] )
			->render( 'create', 'admin' );
	}

	/**
	 * Persist a new product.
	 */
	#[Post('/products', name: 'admin_products_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		$data = $this->collect( $request );

		if( $data['name'] === '' )
		{
			$this->redirect( 'admin_products_create', [], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$data['slug'] = $this->_slugs->generateUnique(
			$data['name'],
			fn( string $slug ): bool => $this->_repository->findBySlug( $slug ) !== null,
			'product'
		);

		try
		{
			$this->_repository->create( $data );
			$this->redirect( 'admin_products', [], [ FlashMessageType::SUCCESS->value, 'Product created.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_products_create', [], [ FlashMessageType::ERROR->value, 'Failed to create product: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Show the edit form.
	 */
	#[Get('/products/:id/edit', name: 'admin_products_edit')]
	public function edit( Request $request ): string
	{
		$id      = (int) $request->getRouteParameter( 'id' );
		$product = $this->_repository->findById( $id );

		if( $product === null )
		{
			$this->redirect( 'admin_products', [], [ FlashMessageType::ERROR->value, 'Product not found.' ] );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Product | Admin' )
			->description( 'Edit a product' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [ 'product' => $product ] )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update a product.
	 */
	#[Put('/products/:id', name: 'admin_products_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$id      = (int) $request->getRouteParameter( 'id' );
		$product = $this->_repository->findById( $id );

		if( $product === null )
		{
			$this->redirect( 'admin_products', [], [ FlashMessageType::ERROR->value, 'Product not found.' ] );
		}

		$data = $this->collect( $request );

		if( $data['name'] === '' )
		{
			$this->redirect( 'admin_products_edit', [ 'id' => $id ], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		// Keep the existing slug; only regenerate when the name changes.
		if( (string) $product['name'] !== $data['name'] )
		{
			$data['slug'] = $this->_slugs->generateUnique(
				$data['name'],
				fn( string $slug ): bool => $slug !== ( $product['slug'] ?? '' ) && $this->_repository->findBySlug( $slug ) !== null,
				'product'
			);
		}

		try
		{
			$this->_repository->update( $id, $data );
			$this->redirect( 'admin_products', [], [ FlashMessageType::SUCCESS->value, 'Product updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_products_edit', [ 'id' => $id ], [ FlashMessageType::ERROR->value, 'Failed to update product: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Delete a product.
	 */
	#[Delete('/products/:id', name: 'admin_products_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$id = (int) $request->getRouteParameter( 'id' );

		try
		{
			$this->_repository->delete( $id );
			$this->redirect( 'admin_products', [], [ FlashMessageType::SUCCESS->value, 'Product deleted.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_products', [], [ FlashMessageType::ERROR->value, 'Failed to delete product: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Collect and normalize product fields from the request.
	 *
	 * @param Request $request
	 * @return array<string, mixed>
	 */
	private function collect( Request $request ): array
	{
		$price = $request->post( 'price', '0' );
		$price = is_numeric( $price ) ? (float) $price : 0.0;

		$currency = strtolower( trim( (string) ( $request->post( 'currency', '' ) ?? '' ) ) );

		if( $currency === '' )
		{
			$currency = strtolower( (string) ( $this->_settings->get( 'payments', 'currency' ) ?? 'usd' ) );
		}

		return [
			'name'        => trim( (string) ( $request->post( 'name', '' ) ?? '' ) ),
			'sku'         => trim( (string) ( $request->post( 'sku', '' ) ?? '' ) ) ?: null,
			'description' => trim( (string) ( $request->post( 'description', '' ) ?? '' ) ) ?: null,
			'price_cents' => (int) round( $price * 100 ),
			'currency'    => $currency,
			'image_url'   => trim( (string) ( $request->post( 'image_url', '' ) ?? '' ) ) ?: null,
			'active'      => $request->post( 'active', null ) !== null ? 1 : 0,
			'sort_order'  => (int) ( $request->post( 'sort_order', 0 ) ?? 0 )
		];
	}
}
