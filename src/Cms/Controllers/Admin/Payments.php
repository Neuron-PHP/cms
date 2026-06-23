<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Services\Payment\PaymentService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin screen for browsing recorded payments.
 *
 * Read-only review (plus delete) of payments captured through the [payment] /
 * [donation] shortcodes, including one-time payments and recurring renewals.
 * Depends only on the CMS payment repository, so it is available whether or not
 * the optional neuron-php/payments package is installed.
 *
 * Routes are exposed under /admin/payments with /admin/donations kept as
 * aliases for backward compatibility.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Payments extends Content
{
	private const PER_PAGE = 25;

	private IPaymentRepository $_repository;
	private PaymentService $_paymentService;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPaymentRepository $repository
	 * @param PaymentService|null $paymentService
	 */
	public function __construct(
		IMvcApplication    $app,
		SettingManager     $settings,
		SessionManager     $sessionManager,
		IPaymentRepository $repository,
		?PaymentService    $paymentService = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository     = $repository;
		$this->_paymentService = $paymentService ?? new PaymentService( $settings );
	}

	/**
	 * List payments (paginated, optional ?status=, ?form= and ?purpose= filters).
	 */
	#[Get('/payments', name: 'admin_payments')]
	#[Get('/donations', name: 'admin_donations')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$page = max( 1, (int) ( $request->get( 'page', 1 ) ?? 1 ) );

		$status  = $request->get( 'status', '' );
		$status  = is_string( $status ) && $status !== '' ? $status : null;

		$formKey = $request->get( 'form', '' );
		$formKey = is_string( $formKey ) && $formKey !== '' ? $formKey : null;

		$purpose = $request->get( 'purpose', '' );
		$purpose = is_string( $purpose ) && $purpose !== '' ? $purpose : null;

		$result = $this->_repository->paginate( $page, self::PER_PAGE, $status, $formKey, $purpose );

		return $this->view()
			->title( 'Payments | Admin' )
			->description( 'Review payments' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'payments'      => $result['items'],
				'total'         => $result['total'],
				'page'          => $result['page'],
				'pages'         => $result['pages'],
				'perPage'       => $result['per_page'],
				'formKeys'      => $this->_repository->formKeys(),
				'activeFormKey' => $formKey,
				'activeStatus'  => $status,
				'activePurpose' => $purpose
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show a single payment with its decoded payer field values.
	 */
	#[Get('/payments/:id', name: 'admin_payment_show')]
	#[Get('/donations/:id', name: 'admin_donation_show')]
	public function show( Request $request ): string
	{
		$this->initializeCsrfToken();

		$id      = (int) $request->getRouteParameter( 'id' );
		$payment = $this->_repository->findById( $id );

		if( $payment === null )
		{
			$this->redirect( 'admin_payments', [], [ FlashMessageType::ERROR->value, 'Payment not found' ] );
		}

		$payload = json_decode( (string) ( $payment['payload'] ?? '{}' ), true );

		if( !is_array( $payload ) )
		{
			$payload = [];
		}

		$fields = $this->_paymentService->getFields( (string) ( $payment['form_key'] ?? '' ) );

		return $this->view()
			->title( 'Payment | Admin' )
			->description( 'Payment detail' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'payment' => $payment,
				'payload' => $payload,
				'fields'  => $fields
			] )
			->render( 'show', 'admin' );
	}

	/**
	 * Delete a payment record.
	 */
	#[Delete('/payments/:id', name: 'admin_payment_delete', filters: ['csrf'])]
	#[Delete('/donations/:id', name: 'admin_donation_delete', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$id = (int) $request->getRouteParameter( 'id' );

		try
		{
			$this->_repository->delete( $id );
			$this->redirect( 'admin_payments', [], [ FlashMessageType::SUCCESS->value, 'Payment deleted' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_payments', [], [ FlashMessageType::ERROR->value, 'Failed to delete payment: ' . $e->getMessage() ] );
		}
	}
}
