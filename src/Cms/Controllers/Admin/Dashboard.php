<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin dashboard controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Dashboard extends Content
{
	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @return void
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager
	)
	{
		parent::__construct( $app, $settings, $sessionManager );
	}


	/**
	 * Show admin dashboard
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/dashboard', name: 'admin_dashboard')]
	#[Get('/', name: 'admin')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Dashboard' )
			->description( 'Admin Dashboard' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'Error', $this->getSessionManager()->getFlash( FlashMessageType::ERROR->value ) )
			->with( 'Success', $this->getSessionManager()->getFlash( FlashMessageType::SUCCESS->value ) )
			->render( 'index', 'admin' );
	}
}
