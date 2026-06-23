<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\IOrderItemRepository;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin screen for browsing store orders.
 *
 * An order is a payment with purpose = "order"; this screen lists those rows
 * and shows each order's line items. Depends only on CMS repositories, so it is
 * available whether or not the optional neuron-php/payments package is
 * installed.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Orders extends Content
{
	private const PER_PAGE = 25;

	private IPaymentRepository $_payments;
	private IOrderItemRepository $_orderItems;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPaymentRepository $payments
	 * @param IOrderItemRepository $orderItems
	 */
	public function __construct(
		IMvcApplication      $app,
		SettingManager       $settings,
		SessionManager       $sessionManager,
		IPaymentRepository   $payments,
		IOrderItemRepository $orderItems
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_payments   = $payments;
		$this->_orderItems = $orderItems;
	}

	/**
	 * List orders ( paginated, optional ?status= filter ).
	 */
	#[Get('/orders', name: 'admin_orders')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$page   = max( 1, (int) ( $request->get( 'page', 1 ) ?? 1 ) );
		$status = $request->get( 'status', '' );
		$status = is_string( $status ) && $status !== '' ? $status : null;

		$result = $this->_payments->paginate( $page, self::PER_PAGE, $status, null, 'order' );

		return $this->view()
			->title( 'Orders | Admin' )
			->description( 'Review orders' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'orders'       => $result['items'],
				'total'        => $result['total'],
				'page'         => $result['page'],
				'pages'        => $result['pages'],
				'activeStatus' => $status
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show a single order with its line items.
	 */
	#[Get('/orders/:id', name: 'admin_order_show')]
	public function show( Request $request ): string
	{
		$this->initializeCsrfToken();

		$id    = (int) $request->getRouteParameter( 'id' );
		$order = $this->_payments->findById( $id );

		if( $order === null || ( $order['purpose'] ?? '' ) !== 'order' )
		{
			$this->redirect( 'admin_orders', [], [ FlashMessageType::ERROR->value, 'Order not found' ] );
		}

		return $this->view()
			->title( 'Order | Admin' )
			->description( 'Order detail' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'order' => $order,
				'items' => $this->_orderItems->findByPaymentId( $id )
			] )
			->render( 'show', 'admin' );
	}

	/**
	 * Delete an order record.
	 */
	#[Delete('/orders/:id', name: 'admin_order_delete', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$id = (int) $request->getRouteParameter( 'id' );

		try
		{
			$this->_payments->delete( $id );
			$this->redirect( 'admin_orders', [], [ FlashMessageType::SUCCESS->value, 'Order deleted' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_orders', [], [ FlashMessageType::ERROR->value, 'Failed to delete order: ' . $e->getMessage() ] );
		}
	}
}
