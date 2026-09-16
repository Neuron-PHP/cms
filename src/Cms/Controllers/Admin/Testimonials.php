<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Enums\TestimonialDisplay;
use Neuron\Cms\Models\Testimonial;
use Neuron\Cms\Models\TestimonialQuote;
use Neuron\Cms\Repositories\ITestimonialRepository;
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
 * Admin named-testimonial management.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Testimonials extends Content
{
	private ITestimonialRepository $_repository;
	private SlugGenerator $_slugs;

	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		ITestimonialRepository $repository,
		?SlugGenerator $slugs = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_slugs      = $slugs ?? new SlugGenerator();
	}

	#[Get('/testimonials', name: 'admin_testimonials')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$session = $this->getSessionManager();
		$testimonials = $this->_repository->all();
		$counts = [];

		foreach( $testimonials as $testimonial )
		{
			$counts[ $testimonial->getId() ] = $this->_repository->countQuotes( $testimonial->getId() );
		}

		return $this->view()
			->title( 'Testimonials | Admin' )
			->description( 'Manage testimonials' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'testimonials' => $testimonials,
				'counts' => $counts,
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'index', 'admin' );
	}

	#[Get('/testimonials/create', name: 'admin_testimonials_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Testimonial | Admin' )
			->description( 'Add a testimonial collection' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'testimonial' => null,
				'displays' => TestimonialDisplay::cases()
			] )
			->render( 'create', 'admin' );
	}

	#[Post('/testimonials', name: 'admin_testimonials_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_testimonials_create', [], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$testimonial = new Testimonial();
		$testimonial->setName( $name );
		$testimonial->setSlug( $this->uniqueSlug( $name ) );
		$testimonial->setDescription( $this->optional( $request, 'description' ) );
		$testimonial->setDisplay( (string) ( $request->post( 'display', TestimonialDisplay::CARDS->value ) ?? TestimonialDisplay::CARDS->value ) );

		try
		{
			$this->_repository->create( $testimonial );
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::SUCCESS->value, 'Collection created. Add quotes below.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_testimonials_create', [], [ FlashMessageType::ERROR->value, 'Failed to create collection: ' . $e->getMessage() ] );
		}
	}

	#[Get('/testimonials/:id/edit', name: 'admin_testimonials_edit')]
	public function edit( Request $request ): string
	{
		$testimonial = $this->requireTestimonial( $request );

		$this->initializeCsrfToken();

		$session = $this->getSessionManager();

		return $this->view()
			->title( 'Edit Testimonial | Admin' )
			->description( 'Edit a testimonial collection' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'testimonial' => $testimonial,
				'quotes' => $this->_repository->getQuotes( $testimonial->getId() ),
				'displays' => TestimonialDisplay::cases(),
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'edit', 'admin' );
	}

	#[Put('/testimonials/:id', name: 'admin_testimonials_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$testimonial = $this->requireTestimonial( $request );
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$originalName = $testimonial->getName();
		$slugInput    = trim( (string) ( $request->post( 'slug', '' ) ?? '' ) );

		$testimonial->setName( $name );
		$testimonial->setDescription( $this->optional( $request, 'description' ) );
		$testimonial->setDisplay( (string) ( $request->post( 'display', $testimonial->getDisplay() ) ?? $testimonial->getDisplay() ) );

		if( $slugInput !== '' && $slugInput !== $testimonial->getSlug() )
		{
			$testimonial->setSlug( $this->uniqueSlug( $slugInput, $testimonial->getId() ) );
		}
		elseif( $slugInput === '' && $name !== $originalName )
		{
			$testimonial->setSlug( $this->uniqueSlug( $name, $testimonial->getId() ) );
		}

		try
		{
			$this->_repository->update( $testimonial );
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::SUCCESS->value, 'Collection updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::ERROR->value, 'Failed to update collection: ' . $e->getMessage() ] );
		}
	}

	#[Delete('/testimonials/:id', name: 'admin_testimonials_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$testimonial = $this->requireTestimonial( $request );

		try
		{
			$this->_repository->delete( $testimonial );
			$this->redirect( 'admin_testimonials', [], [ FlashMessageType::SUCCESS->value, 'Collection deleted.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_testimonials', [], [ FlashMessageType::ERROR->value, 'Failed to delete collection: ' . $e->getMessage() ] );
		}
	}

	#[Get('/testimonials/:id/quotes/create', name: 'admin_testimonials_quotes_create')]
	public function createQuote( Request $request ): string
	{
		$testimonial = $this->requireTestimonial( $request );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Quote | Admin' )
			->description( 'Add a testimonial quote' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'testimonial' => $testimonial,
				'quote' => null
			] )
			->render( 'quote_create', 'admin' );
	}

	#[Post('/testimonials/:id/quotes', name: 'admin_testimonials_quotes_store', filters: ['csrf'])]
	public function storeQuote( Request $request ): never
	{
		$testimonial = $this->requireTestimonial( $request );
		$text = trim( (string) ( $request->post( 'quote', '' ) ?? '' ) );

		if( $text === '' )
		{
			$this->redirect( 'admin_testimonials_quotes_create', [ 'id' => $testimonial->getId() ], [ FlashMessageType::ERROR->value, 'Quote is required.' ] );
		}

		$quote = $this->quoteFromRequest( $request, $testimonial->getId() );

		if( $request->post( 'sort_order', null ) === null || trim( (string) $request->post( 'sort_order', '' ) ) === '' )
		{
			$quote->setSortOrder( $this->_repository->nextQuoteSortOrder( $testimonial->getId() ) );
		}

		try
		{
			$this->_repository->createQuote( $quote );
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::SUCCESS->value, 'Quote added.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_testimonials_quotes_create', [ 'id' => $testimonial->getId() ], [ FlashMessageType::ERROR->value, 'Failed to add quote: ' . $e->getMessage() ] );
		}
	}

	#[Get('/testimonials/:id/quotes/:quoteId/edit', name: 'admin_testimonials_quotes_edit')]
	public function editQuote( Request $request ): string
	{
		$testimonial = $this->requireTestimonial( $request );
		$quote = $this->requireQuote( $request, $testimonial );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Quote | Admin' )
			->description( 'Edit a testimonial quote' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'testimonial' => $testimonial,
				'quote' => $quote
			] )
			->render( 'quote_edit', 'admin' );
	}

	#[Put('/testimonials/:id/quotes/:quoteId', name: 'admin_testimonials_quotes_update', filters: ['csrf'])]
	public function updateQuote( Request $request ): never
	{
		$testimonial = $this->requireTestimonial( $request );
		$quote = $this->requireQuote( $request, $testimonial );
		$text = trim( (string) ( $request->post( 'quote', '' ) ?? '' ) );

		if( $text === '' )
		{
			$this->redirect(
				'admin_testimonials_quotes_edit',
				[ 'id' => $testimonial->getId(), 'quoteId' => $quote->getId() ],
				[ FlashMessageType::ERROR->value, 'Quote is required.' ]
			);
		}

		$updated = $this->quoteFromRequest( $request, $testimonial->getId(), $quote );

		try
		{
			$this->_repository->updateQuote( $updated );
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::SUCCESS->value, 'Quote updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect(
				'admin_testimonials_quotes_edit',
				[ 'id' => $testimonial->getId(), 'quoteId' => $quote->getId() ],
				[ FlashMessageType::ERROR->value, 'Failed to update quote: ' . $e->getMessage() ]
			);
		}
	}

	#[Delete('/testimonials/:id/quotes/:quoteId', name: 'admin_testimonials_quotes_destroy', filters: ['csrf'])]
	public function destroyQuote( Request $request ): never
	{
		$testimonial = $this->requireTestimonial( $request );
		$quote = $this->requireQuote( $request, $testimonial );

		try
		{
			$this->_repository->deleteQuote( $quote );
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::SUCCESS->value, 'Quote removed.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::ERROR->value, 'Failed to remove quote: ' . $e->getMessage() ] );
		}
	}

	private function requireTestimonial( Request $request ): Testimonial
	{
		$id = (int) $request->getRouteParameter( 'id' );
		$testimonial = $this->_repository->findById( $id );

		if( $testimonial === null )
		{
			$this->redirect( 'admin_testimonials', [], [ FlashMessageType::ERROR->value, 'Collection not found.' ] );
		}

		return $testimonial;
	}

	private function requireQuote( Request $request, Testimonial $testimonial ): TestimonialQuote
	{
		$quoteId = (int) $request->getRouteParameter( 'quoteId' );
		$quote = $this->_repository->findQuoteById( $quoteId );

		if( $quote === null || $quote->getTestimonialId() !== $testimonial->getId() )
		{
			$this->redirect( 'admin_testimonials_edit', [ 'id' => $testimonial->getId() ], [ FlashMessageType::ERROR->value, 'Quote not found.' ] );
		}

		return $quote;
	}

	private function uniqueSlug( string $text, ?int $excludeId = null ): string
	{
		return $this->_slugs->generateUnique(
			$text,
			fn( string $slug ): bool => $this->_repository->slugExists( $slug, $excludeId ),
			'testimonial'
		);
	}

	private function optional( Request $request, string $field ): ?string
	{
		$value = trim( (string) ( $request->post( $field, '' ) ?? '' ) );

		return $value === '' ? null : $value;
	}

	private function quoteFromRequest( Request $request, int $testimonialId, ?TestimonialQuote $existing = null ): TestimonialQuote
	{
		$quote = $existing ?? new TestimonialQuote();
		$quote->setTestimonialId( $testimonialId );
		$quote->setQuote( trim( (string) ( $request->post( 'quote', '' ) ?? '' ) ) );
		$quote->setAttribution( $this->optional( $request, 'attribution' ) );
		$quote->setRole( $this->optional( $request, 'role' ) );
		$quote->setOrganization( $this->optional( $request, 'organization' ) );
		$quote->setImageUrl( $this->optional( $request, 'image_url' ) );
		$quote->setSortOrder( (int) ( $request->post( 'sort_order', 0 ) ?? 0 ) );

		return $quote;
	}
}
