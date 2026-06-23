<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\IPaymentRepository;
use Neuron\Cms\Repositories\ISubscriptionRepository;
use Neuron\Cms\Services\Payment\PaymentGatewayFactory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin screen for managing recurring subscriptions.
 *
 * Lists subscriptions created through recurring payments / donations, shows a
 * subscription with the charges it has produced, and can cancel an active
 * subscription via the gateway. The cancel action depends on the optional
 * neuron-php/payments package; listing and viewing do not.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Subscriptions extends Content
{
	private const PER_PAGE = 25;

	private ISubscriptionRepository $_repository;
	private IPaymentRepository $_payments;
	private PaymentGatewayFactory $_gatewayFactory;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param ISubscriptionRepository $repository
	 * @param IPaymentRepository $payments
	 * @param PaymentGatewayFactory $gatewayFactory
	 */
	public function __construct(
		IMvcApplication         $app,
		SettingManager          $settings,
		SessionManager          $sessionManager,
		ISubscriptionRepository $repository,
		IPaymentRepository      $payments,
		PaymentGatewayFactory   $gatewayFactory
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository     = $repository;
		$this->_payments       = $payments;
		$this->_gatewayFactory = $gatewayFactory;
	}

	/**
	 * List subscriptions (paginated, optional ?status=, ?form= filters).
	 */
	#[Get('/subscriptions', name: 'admin_subscriptions')]
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
			->title( 'Subscriptions | Admin' )
			->description( 'Review subscriptions' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'subscriptions' => $result['items'],
				'total'         => $result['total'],
				'page'          => $result['page'],
				'pages'         => $result['pages'],
				'perPage'       => $result['per_page'],
				'formKeys'      => $this->_repository->formKeys(),
				'activeFormKey' => $formKey,
				'activeStatus'  => $status,
				'canCancel'     => $this->_gatewayFactory->isEnabled()
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show a single subscription with the charges it has produced.
	 */
	#[Get('/subscriptions/:id', name: 'admin_subscription_show')]
	public function show( Request $request ): string
	{
		$this->initializeCsrfToken();

		$id           = (int) $request->getRouteParameter( 'id' );
		$subscription = $this->_repository->findById( $id );

		if( $subscription === null )
		{
			$this->redirect( 'admin_subscriptions', [], [ FlashMessageType::ERROR->value, 'Subscription not found' ] );
		}

		$gatewayId = (string) ( $subscription['subscription_id'] ?? '' );
		$charges   = [];

		if( $gatewayId !== '' )
		{
			$page = $this->_payments->paginate( 1, 100 );

			foreach( $page['items'] as $row )
			{
				if( ( $row['subscription_id'] ?? null ) === $gatewayId )
				{
					$charges[] = $row;
				}
			}
		}

		return $this->view()
			->title( 'Subscription | Admin' )
			->description( 'Subscription detail' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'subscription' => $subscription,
				'charges'      => $charges,
				'canCancel'    => $this->_gatewayFactory->isEnabled()
			] )
			->render( 'show', 'admin' );
	}

	/**
	 * Cancel an active subscription through the gateway.
	 */
	#[Post('/subscriptions/:id/cancel', name: 'admin_subscription_cancel', filters: ['csrf'])]
	public function cancel( Request $request ): never
	{
		$id           = (int) $request->getRouteParameter( 'id' );
		$subscription = $this->_repository->findById( $id );

		if( $subscription === null )
		{
			$this->redirect( 'admin_subscriptions', [], [ FlashMessageType::ERROR->value, 'Subscription not found' ] );
		}

		$gateway = $this->_gatewayFactory->create();

		if( $gateway === null )
		{
			$this->redirect( 'admin_subscriptions', [], [ FlashMessageType::ERROR->value, 'Payment gateway is not available.' ] );
		}

		$gatewayId = (string) ( $subscription['subscription_id'] ?? '' );

		try
		{
			$gateway->cancelSubscription( $gatewayId );

			$this->_repository->updateState( $gatewayId, [
				'status'      => 'canceled',
				'canceled_at' => date( 'Y-m-d H:i:s' )
			] );

			$this->redirect( 'admin_subscriptions', [], [ FlashMessageType::SUCCESS->value, 'Subscription canceled' ] );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Subscription cancel failed: ' . $e->getMessage() );
			$this->redirect( 'admin_subscriptions', [], [ FlashMessageType::ERROR->value, 'Failed to cancel subscription: ' . $e->getMessage() ] );
		}
	}
}
