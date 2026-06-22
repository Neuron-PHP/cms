<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\IDonationRepository;
use Neuron\Cms\Services\Donation\DonationService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin screen for browsing recorded donations.
 *
 * Read-only review (plus delete) of donations captured through the [donation]
 * shortcode. This controller depends only on the CMS donation repository, so
 * it is available whether or not the optional neuron-php/payments package is
 * installed.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Donations extends Content
{
	private const PER_PAGE = 25;

	private IDonationRepository $_repository;
	private DonationService $_donationService;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IDonationRepository $repository
	 * @param DonationService|null $donationService
	 */
	public function __construct(
		IMvcApplication     $app,
		SettingManager      $settings,
		SessionManager      $sessionManager,
		IDonationRepository $repository,
		?DonationService    $donationService = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository      = $repository;
		$this->_donationService = $donationService ?? new DonationService( $settings );
	}

	/**
	 * List donations (paginated, optional ?status= and ?form= filters).
	 */
	#[Get('/donations', name: 'admin_donations')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$page = max( 1, (int) ( $request->get( 'page', 1 ) ?? 1 ) );

		$status  = $request->get( 'status', '' );
		$status  = is_string( $status ) && $status !== '' ? $status : null;

		$formKey = $request->get( 'form', '' );
		$formKey = is_string( $formKey ) && $formKey !== '' ? $formKey : null;

		$result = $this->_repository->paginate( $page, self::PER_PAGE, $status, $formKey );

		return $this->view()
			->title( 'Donations | Admin' )
			->description( 'Review donations' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'donations'     => $result['items'],
				'total'         => $result['total'],
				'page'          => $result['page'],
				'pages'         => $result['pages'],
				'perPage'       => $result['per_page'],
				'formKeys'      => $this->_repository->formKeys(),
				'activeFormKey' => $formKey,
				'activeStatus'  => $status
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show a single donation with its decoded donor field values.
	 */
	#[Get('/donations/:id', name: 'admin_donation_show')]
	public function show( Request $request ): string
	{
		$this->initializeCsrfToken();

		$id       = (int) $request->getRouteParameter( 'id' );
		$donation = $this->_repository->findById( $id );

		if( $donation === null )
		{
			$this->redirect( 'admin_donations', [], [ FlashMessageType::ERROR->value, 'Donation not found' ] );
		}

		$payload = json_decode( (string) ( $donation['payload'] ?? '{}' ), true );

		if( !is_array( $payload ) )
		{
			$payload = [];
		}

		$fields = $this->_donationService->getFields( (string) ( $donation['form_key'] ?? '' ) );

		return $this->view()
			->title( 'Donation | Admin' )
			->description( 'Donation detail' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'donation' => $donation,
				'payload'  => $payload,
				'fields'   => $fields
			] )
			->render( 'show', 'admin' );
	}

	/**
	 * Delete a donation record.
	 */
	#[Delete('/donations/:id', name: 'admin_donation_delete', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$id = (int) $request->getRouteParameter( 'id' );

		try
		{
			$this->_repository->delete( $id );
			$this->redirect( 'admin_donations', [], [ FlashMessageType::SUCCESS->value, 'Donation deleted' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_donations', [], [ FlashMessageType::ERROR->value, 'Failed to delete donation: ' . $e->getMessage() ] );
		}
	}
}
