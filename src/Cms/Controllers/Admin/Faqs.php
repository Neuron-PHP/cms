<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Models\Faq;
use Neuron\Cms\Models\FaqItem;
use Neuron\Cms\Repositories\IFaqRepository;
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
 * Admin named-FAQ management.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Faqs extends Content
{
	private IFaqRepository $_repository;
	private SlugGenerator $_slugs;

	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IFaqRepository $repository,
		?SlugGenerator $slugs = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_slugs      = $slugs ?? new SlugGenerator();
	}

	#[Get('/faqs', name: 'admin_faqs')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$session = $this->getSessionManager();
		$faqs = $this->_repository->all();
		$counts = [];

		foreach( $faqs as $faq )
		{
			$counts[ $faq->getId() ] = $this->_repository->countItems( $faq->getId() );
		}

		return $this->view()
			->title( 'FAQs | Admin' )
			->description( 'Manage FAQ groups' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'faqs' => $faqs,
				'counts' => $counts,
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'index', 'admin' );
	}

	#[Get('/faqs/create', name: 'admin_faqs_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add FAQ | Admin' )
			->description( 'Add an FAQ group' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [ 'faq' => null ] )
			->render( 'create', 'admin' );
	}

	#[Post('/faqs', name: 'admin_faqs_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_faqs_create', [], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$faq = new Faq();
		$faq->setName( $name );
		$faq->setSlug( $this->uniqueSlug( $name ) );
		$faq->setDescription( $this->optional( $request, 'description' ) );

		try
		{
			$this->_repository->create( $faq );
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::SUCCESS->value, 'FAQ group created. Add questions below.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_faqs_create', [], [ FlashMessageType::ERROR->value, 'Failed to create FAQ group: ' . $e->getMessage() ] );
		}
	}

	#[Get('/faqs/:id/edit', name: 'admin_faqs_edit')]
	public function edit( Request $request ): string
	{
		$faq = $this->requireFaq( $request );

		$this->initializeCsrfToken();

		$session = $this->getSessionManager();

		return $this->view()
			->title( 'Edit FAQ | Admin' )
			->description( 'Edit an FAQ group' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'faq' => $faq,
				'items' => $this->_repository->getItems( $faq->getId() ),
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'edit', 'admin' );
	}

	#[Put('/faqs/:id', name: 'admin_faqs_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$faq = $this->requireFaq( $request );
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$originalName = $faq->getName();
		$slugInput    = trim( (string) ( $request->post( 'slug', '' ) ?? '' ) );

		$faq->setName( $name );
		$faq->setDescription( $this->optional( $request, 'description' ) );

		if( $slugInput !== '' && $slugInput !== $faq->getSlug() )
		{
			$faq->setSlug( $this->uniqueSlug( $slugInput, $faq->getId() ) );
		}
		elseif( $slugInput === '' && $name !== $originalName )
		{
			$faq->setSlug( $this->uniqueSlug( $name, $faq->getId() ) );
		}

		try
		{
			$this->_repository->update( $faq );
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::SUCCESS->value, 'FAQ group updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::ERROR->value, 'Failed to update FAQ group: ' . $e->getMessage() ] );
		}
	}

	#[Delete('/faqs/:id', name: 'admin_faqs_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$faq = $this->requireFaq( $request );

		try
		{
			$this->_repository->delete( $faq );
			$this->redirect( 'admin_faqs', [], [ FlashMessageType::SUCCESS->value, 'FAQ group deleted.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_faqs', [], [ FlashMessageType::ERROR->value, 'Failed to delete FAQ group: ' . $e->getMessage() ] );
		}
	}

	#[Get('/faqs/:id/items/create', name: 'admin_faqs_items_create')]
	public function createItem( Request $request ): string
	{
		$faq = $this->requireFaq( $request );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Question | Admin' )
			->description( 'Add an FAQ question' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'faq' => $faq,
				'item' => null
			] )
			->render( 'item_create', 'admin' );
	}

	#[Post('/faqs/:id/items', name: 'admin_faqs_items_store', filters: ['csrf'])]
	public function storeItem( Request $request ): never
	{
		$faq = $this->requireFaq( $request );
		$question = trim( (string) ( $request->post( 'question', '' ) ?? '' ) );
		$answer = trim( (string) ( $request->post( 'answer', '' ) ?? '' ) );

		if( $question === '' || $answer === '' )
		{
			$this->redirect( 'admin_faqs_items_create', [ 'id' => $faq->getId() ], [ FlashMessageType::ERROR->value, 'Question and answer are required.' ] );
		}

		$item = $this->itemFromRequest( $request, $faq->getId() );

		if( $request->post( 'sort_order', null ) === null || trim( (string) $request->post( 'sort_order', '' ) ) === '' )
		{
			$item->setSortOrder( $this->_repository->nextItemSortOrder( $faq->getId() ) );
		}

		try
		{
			$this->_repository->createItem( $item );
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::SUCCESS->value, 'Question added.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_faqs_items_create', [ 'id' => $faq->getId() ], [ FlashMessageType::ERROR->value, 'Failed to add question: ' . $e->getMessage() ] );
		}
	}

	#[Get('/faqs/:id/items/:itemId/edit', name: 'admin_faqs_items_edit')]
	public function editItem( Request $request ): string
	{
		$faq = $this->requireFaq( $request );
		$item = $this->requireItem( $request, $faq );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Question | Admin' )
			->description( 'Edit an FAQ question' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'faq' => $faq,
				'item' => $item
			] )
			->render( 'item_edit', 'admin' );
	}

	#[Put('/faqs/:id/items/:itemId', name: 'admin_faqs_items_update', filters: ['csrf'])]
	public function updateItem( Request $request ): never
	{
		$faq = $this->requireFaq( $request );
		$item = $this->requireItem( $request, $faq );
		$question = trim( (string) ( $request->post( 'question', '' ) ?? '' ) );
		$answer = trim( (string) ( $request->post( 'answer', '' ) ?? '' ) );

		if( $question === '' || $answer === '' )
		{
			$this->redirect(
				'admin_faqs_items_edit',
				[ 'id' => $faq->getId(), 'itemId' => $item->getId() ],
				[ FlashMessageType::ERROR->value, 'Question and answer are required.' ]
			);
		}

		$updated = $this->itemFromRequest( $request, $faq->getId(), $item );

		try
		{
			$this->_repository->updateItem( $updated );
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::SUCCESS->value, 'Question updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect(
				'admin_faqs_items_edit',
				[ 'id' => $faq->getId(), 'itemId' => $item->getId() ],
				[ FlashMessageType::ERROR->value, 'Failed to update question: ' . $e->getMessage() ]
			);
		}
	}

	#[Delete('/faqs/:id/items/:itemId', name: 'admin_faqs_items_destroy', filters: ['csrf'])]
	public function destroyItem( Request $request ): never
	{
		$faq = $this->requireFaq( $request );
		$item = $this->requireItem( $request, $faq );

		try
		{
			$this->_repository->deleteItem( $item );
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::SUCCESS->value, 'Question removed.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::ERROR->value, 'Failed to remove question: ' . $e->getMessage() ] );
		}
	}

	private function requireFaq( Request $request ): Faq
	{
		$id = (int) $request->getRouteParameter( 'id' );
		$faq = $this->_repository->findById( $id );

		if( $faq === null )
		{
			$this->redirect( 'admin_faqs', [], [ FlashMessageType::ERROR->value, 'FAQ group not found.' ] );
		}

		return $faq;
	}

	private function requireItem( Request $request, Faq $faq ): FaqItem
	{
		$itemId = (int) $request->getRouteParameter( 'itemId' );
		$item = $this->_repository->findItemById( $itemId );

		if( $item === null || $item->getFaqId() !== $faq->getId() )
		{
			$this->redirect( 'admin_faqs_edit', [ 'id' => $faq->getId() ], [ FlashMessageType::ERROR->value, 'Question not found.' ] );
		}

		return $item;
	}

	private function uniqueSlug( string $text, ?int $excludeId = null ): string
	{
		return $this->_slugs->generateUnique(
			$text,
			fn( string $slug ): bool => $this->_repository->slugExists( $slug, $excludeId ),
			'faq'
		);
	}

	private function optional( Request $request, string $field ): ?string
	{
		$value = trim( (string) ( $request->post( $field, '' ) ?? '' ) );

		return $value === '' ? null : $value;
	}

	private function itemFromRequest( Request $request, int $faqId, ?FaqItem $existing = null ): FaqItem
	{
		$item = $existing ?? new FaqItem();
		$item->setFaqId( $faqId );
		$item->setQuestion( trim( (string) ( $request->post( 'question', '' ) ?? '' ) ) );
		$item->setAnswer( trim( (string) ( $request->post( 'answer', '' ) ?? '' ) ) );
		$item->setSortOrder( (int) ( $request->post( 'sort_order', 0 ) ?? 0 ) );

		return $item;
	}
}
