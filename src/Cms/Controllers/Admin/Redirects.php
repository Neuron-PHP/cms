<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Models\Redirect;
use Neuron\Cms\Repositories\IRedirectRepository;
use Neuron\Cms\Services\Redirect\RedirectService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin HTTP redirect management.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Redirects extends Content
{
	private IRedirectRepository $_repository;
	private RedirectService $_service;

	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IRedirectRepository $repository,
		RedirectService $service
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_service    = $service;
	}

	#[Get('/redirects', name: 'admin_redirects')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$session = $this->getSessionManager();

		return $this->view()
			->title( 'Redirects | Admin' )
			->description( 'Manage HTTP redirects' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'redirects' => $this->_repository->all(),
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'index', 'admin' );
	}

	#[Get('/redirects/create', name: 'admin_redirects_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Redirect | Admin' )
			->description( 'Create an HTTP redirect' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [ 'redirect' => null ] )
			->render( 'create', 'admin' );
	}

	#[Post('/redirects', name: 'admin_redirects_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		$redirect = $this->fromRequest( $request );
		$error = $this->_service->validate(
			$redirect->getFromPath(),
			$redirect->getToUrl(),
			$redirect->getStatusCode()
		);

		if( $error !== null )
		{
			$this->redirect( 'admin_redirects_create', [], [ FlashMessageType::ERROR->value, $error ] );
		}

		try
		{
			$this->_service->normalizeForSave( $redirect );
			$this->_repository->create( $redirect );
			$this->redirect( 'admin_redirects', [], [ FlashMessageType::SUCCESS->value, 'Redirect created.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_redirects_create', [], [ FlashMessageType::ERROR->value, 'Failed to create redirect: ' . $e->getMessage() ] );
		}
	}

	#[Get('/redirects/:id/edit', name: 'admin_redirects_edit')]
	public function edit( Request $request ): string
	{
		$redirect = $this->requireRedirect( $request );

		$this->initializeCsrfToken();

		$session = $this->getSessionManager();

		return $this->view()
			->title( 'Edit Redirect | Admin' )
			->description( 'Edit an HTTP redirect' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'redirect' => $redirect,
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'edit', 'admin' );
	}

	#[Put('/redirects/:id', name: 'admin_redirects_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$redirect = $this->fromRequest( $request, $this->requireRedirect( $request ) );
		$error = $this->_service->validate(
			$redirect->getFromPath(),
			$redirect->getToUrl(),
			$redirect->getStatusCode(),
			$redirect->getId()
		);

		if( $error !== null )
		{
			$this->redirect( 'admin_redirects_edit', [ 'id' => $redirect->getId() ], [ FlashMessageType::ERROR->value, $error ] );
		}

		try
		{
			$this->_service->normalizeForSave( $redirect );
			$this->_repository->update( $redirect );
			$this->redirect( 'admin_redirects', [], [ FlashMessageType::SUCCESS->value, 'Redirect updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect(
				'admin_redirects_edit',
				[ 'id' => $redirect->getId() ],
				[ FlashMessageType::ERROR->value, 'Failed to update redirect: ' . $e->getMessage() ]
			);
		}
	}

	#[Delete('/redirects/:id', name: 'admin_redirects_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$redirect = $this->requireRedirect( $request );

		try
		{
			$this->_repository->delete( $redirect );
			$this->redirect( 'admin_redirects', [], [ FlashMessageType::SUCCESS->value, 'Redirect deleted.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_redirects', [], [ FlashMessageType::ERROR->value, 'Failed to delete redirect: ' . $e->getMessage() ] );
		}
	}

	private function requireRedirect( Request $request ): Redirect
	{
		$id = (int) $request->getRouteParameter( 'id' );
		$redirect = $this->_repository->findById( $id );

		if( $redirect === null )
		{
			$this->redirect( 'admin_redirects', [], [ FlashMessageType::ERROR->value, 'Redirect not found.' ] );
		}

		return $redirect;
	}

	private function fromRequest( Request $request, ?Redirect $existing = null ): Redirect
	{
		$redirect = $existing ?? new Redirect();
		$status = (int) ( $request->post( 'status_code', (string) Redirect::STATUS_PERMANENT ) ?? Redirect::STATUS_PERMANENT );

		$redirect->setFromPath( trim( (string) ( $request->post( 'from_path', '' ) ?? '' ) ) );
		$redirect->setToUrl( trim( (string) ( $request->post( 'to_url', '' ) ?? '' ) ) );
		$redirect->setStatusCode( $status );
		$redirect->setIsActive( (string) ( $request->post( 'is_active', '0' ) ?? '0' ) === '1' );
		$redirect->setPreserveQuery( (string) ( $request->post( 'preserve_query', '0' ) ?? '0' ) === '1' );
		$redirect->setNotes( $this->optional( $request, 'notes' ) );

		return $redirect;
	}

	private function optional( Request $request, string $field ): ?string
	{
		$value = trim( (string) ( $request->post( $field, '' ) ?? '' ) );

		return $value === '' ? null : $value;
	}
}
