<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\CarouselDisplay;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Models\Carousel;
use Neuron\Cms\Models\CarouselSlide;
use Neuron\Cms\Repositories\ICarouselRepository;
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
 * Admin named-carousel management.
 *
 * CRUD for carousels and their slides. Collections are shown on pages via
 * the [carousel slug="hero"] shortcode.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Carousels extends Content
{
	private ICarouselRepository $_repository;
	private SlugGenerator $_slugs;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param ICarouselRepository $repository
	 * @param SlugGenerator|null $slugs
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		ICarouselRepository $repository,
		?SlugGenerator $slugs = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_slugs      = $slugs ?? new SlugGenerator();
	}

	/**
	 * List carousels.
	 */
	#[Get('/carousels', name: 'admin_carousels')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$session   = $this->getSessionManager();
		$carousels = $this->_repository->all();
		$counts    = [];

		foreach( $carousels as $carousel )
		{
			$counts[ $carousel->getId() ] = $this->_repository->countSlides( $carousel->getId() );
		}

		return $this->view()
			->title( 'Carousels | Admin' )
			->description( 'Manage carousels' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'carousels' => $carousels,
				'counts'    => $counts,
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show the create-carousel form.
	 */
	#[Get('/carousels/create', name: 'admin_carousels_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Carousel | Admin' )
			->description( 'Add a carousel' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'carousel' => null,
				'displays' => CarouselDisplay::cases()
			] )
			->render( 'create', 'admin' );
	}

	/**
	 * Persist a new carousel.
	 */
	#[Post('/carousels', name: 'admin_carousels_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_carousels_create', [], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$carousel = new Carousel();
		$carousel->setName( $name );
		$carousel->setSlug( $this->uniqueSlug( $name ) );
		$carousel->setDescription( $this->optional( $request, 'description' ) );
		$carousel->setDisplay( (string) ( $request->post( 'display', CarouselDisplay::SLIDER->value ) ?? CarouselDisplay::SLIDER->value ) );

		try
		{
			$this->_repository->create( $carousel );
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::SUCCESS->value, 'Carousel created. Add slides below.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_carousels_create', [], [ FlashMessageType::ERROR->value, 'Failed to create carousel: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Show the edit-carousel form and slide list.
	 */
	#[Get('/carousels/:id/edit', name: 'admin_carousels_edit')]
	public function edit( Request $request ): string
	{
		$carousel = $this->requireCarousel( $request );

		$this->initializeCsrfToken();

		$session = $this->getSessionManager();

		return $this->view()
			->title( 'Edit Carousel | Admin' )
			->description( 'Edit a carousel' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'carousel' => $carousel,
				'slides'   => $this->_repository->getSlides( $carousel->getId() ),
				'displays' => CarouselDisplay::cases(),
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update a carousel.
	 */
	#[Put('/carousels/:id', name: 'admin_carousels_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$carousel = $this->requireCarousel( $request );
		$name     = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$originalName = $carousel->getName();
		$slugInput    = trim( (string) ( $request->post( 'slug', '' ) ?? '' ) );

		$carousel->setName( $name );
		$carousel->setDescription( $this->optional( $request, 'description' ) );
		$carousel->setDisplay( (string) ( $request->post( 'display', $carousel->getDisplay() ) ?? $carousel->getDisplay() ) );

		if( $slugInput !== '' && $slugInput !== $carousel->getSlug() )
		{
			$carousel->setSlug( $this->uniqueSlug( $slugInput, $carousel->getId() ) );
		}
		elseif( $slugInput === '' && $name !== $originalName )
		{
			$carousel->setSlug( $this->uniqueSlug( $name, $carousel->getId() ) );
		}

		try
		{
			$this->_repository->update( $carousel );
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::SUCCESS->value, 'Carousel updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::ERROR->value, 'Failed to update carousel: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Delete a carousel and its slides.
	 */
	#[Delete('/carousels/:id', name: 'admin_carousels_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$carousel = $this->requireCarousel( $request );

		try
		{
			$this->_repository->delete( $carousel );
			$this->redirect( 'admin_carousels', [], [ FlashMessageType::SUCCESS->value, 'Carousel deleted.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_carousels', [], [ FlashMessageType::ERROR->value, 'Failed to delete carousel: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Show the add-slide form.
	 */
	#[Get('/carousels/:id/slides/create', name: 'admin_carousels_slides_create')]
	public function createSlide( Request $request ): string
	{
		$carousel = $this->requireCarousel( $request );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Slide | Admin' )
			->description( 'Add a carousel slide' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'carousel' => $carousel,
				'slide'    => null
			] )
			->render( 'slide_create', 'admin' );
	}

	/**
	 * Persist a new slide.
	 */
	#[Post('/carousels/:id/slides', name: 'admin_carousels_slides_store', filters: ['csrf'])]
	public function storeSlide( Request $request ): never
	{
		$carousel = $this->requireCarousel( $request );
		$imageUrl = trim( (string) ( $request->post( 'image_url', '' ) ?? '' ) );

		if( $imageUrl === '' )
		{
			$this->redirect( 'admin_carousels_slides_create', [ 'id' => $carousel->getId() ], [ FlashMessageType::ERROR->value, 'Image is required.' ] );
		}

		$slide = $this->slideFromRequest( $request, $carousel->getId() );

		if( $request->post( 'sort_order', null ) === null || trim( (string) $request->post( 'sort_order', '' ) ) === '' )
		{
			$slide->setSortOrder( $this->_repository->nextSlideSortOrder( $carousel->getId() ) );
		}

		try
		{
			$this->_repository->createSlide( $slide );
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::SUCCESS->value, 'Slide added.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_carousels_slides_create', [ 'id' => $carousel->getId() ], [ FlashMessageType::ERROR->value, 'Failed to add slide: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Show the edit-slide form.
	 */
	#[Get('/carousels/:id/slides/:slideId/edit', name: 'admin_carousels_slides_edit')]
	public function editSlide( Request $request ): string
	{
		$carousel = $this->requireCarousel( $request );
		$slide    = $this->requireSlide( $request, $carousel );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Slide | Admin' )
			->description( 'Edit a carousel slide' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'carousel' => $carousel,
				'slide'    => $slide
			] )
			->render( 'slide_edit', 'admin' );
	}

	/**
	 * Update a slide.
	 */
	#[Put('/carousels/:id/slides/:slideId', name: 'admin_carousels_slides_update', filters: ['csrf'])]
	public function updateSlide( Request $request ): never
	{
		$carousel = $this->requireCarousel( $request );
		$slide    = $this->requireSlide( $request, $carousel );
		$imageUrl = trim( (string) ( $request->post( 'image_url', '' ) ?? '' ) );

		if( $imageUrl === '' )
		{
			$this->redirect(
				'admin_carousels_slides_edit',
				[ 'id' => $carousel->getId(), 'slideId' => $slide->getId() ],
				[ FlashMessageType::ERROR->value, 'Image is required.' ]
			);
		}

		$updated = $this->slideFromRequest( $request, $carousel->getId(), $slide );

		try
		{
			$this->_repository->updateSlide( $updated );
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::SUCCESS->value, 'Slide updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect(
				'admin_carousels_slides_edit',
				[ 'id' => $carousel->getId(), 'slideId' => $slide->getId() ],
				[ FlashMessageType::ERROR->value, 'Failed to update slide: ' . $e->getMessage() ]
			);
		}
	}

	/**
	 * Delete a slide.
	 */
	#[Delete('/carousels/:id/slides/:slideId', name: 'admin_carousels_slides_destroy', filters: ['csrf'])]
	public function destroySlide( Request $request ): never
	{
		$carousel = $this->requireCarousel( $request );
		$slide    = $this->requireSlide( $request, $carousel );

		try
		{
			$this->_repository->deleteSlide( $slide );
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::SUCCESS->value, 'Slide removed.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::ERROR->value, 'Failed to remove slide: ' . $e->getMessage() ] );
		}
	}

	private function requireCarousel( Request $request ): Carousel
	{
		$id       = (int) $request->getRouteParameter( 'id' );
		$carousel = $this->_repository->findById( $id );

		if( $carousel === null )
		{
			$this->redirect( 'admin_carousels', [], [ FlashMessageType::ERROR->value, 'Carousel not found.' ] );
		}

		return $carousel;
	}

	private function requireSlide( Request $request, Carousel $carousel ): CarouselSlide
	{
		$slideId = (int) $request->getRouteParameter( 'slideId' );
		$slide   = $this->_repository->findSlideById( $slideId );

		if( $slide === null || $slide->getCarouselId() !== $carousel->getId() )
		{
			$this->redirect( 'admin_carousels_edit', [ 'id' => $carousel->getId() ], [ FlashMessageType::ERROR->value, 'Slide not found.' ] );
		}

		return $slide;
	}

	private function uniqueSlug( string $text, ?int $excludeId = null ): string
	{
		return $this->_slugs->generateUnique(
			$text,
			fn( string $slug ): bool => $this->_repository->slugExists( $slug, $excludeId ),
			'carousel'
		);
	}

	private function optional( Request $request, string $field ): ?string
	{
		$value = trim( (string) ( $request->post( $field, '' ) ?? '' ) );

		return $value === '' ? null : $value;
	}

	private function slideFromRequest( Request $request, int $carouselId, ?CarouselSlide $existing = null ): CarouselSlide
	{
		$slide = $existing ?? new CarouselSlide();
		$slide->setCarouselId( $carouselId );
		$slide->setImageUrl( trim( (string) ( $request->post( 'image_url', '' ) ?? '' ) ) );
		$slide->setAltText( $this->optional( $request, 'alt_text' ) );
		$slide->setHeading( $this->optional( $request, 'heading' ) );
		$slide->setCaption( $this->optional( $request, 'caption' ) );
		$slide->setLinkUrl( $this->optional( $request, 'link_url' ) );
		$slide->setLinkLabel( $this->optional( $request, 'link_label' ) );
		$slide->setSortOrder( (int) ( $request->post( 'sort_order', 0 ) ?? 0 ) );

		return $slide;
	}
}
