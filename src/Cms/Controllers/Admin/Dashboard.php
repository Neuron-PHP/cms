<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin dashboard controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Dashboard extends Content
{
	/**
	 * @param Application|null $app
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );
	}


	/**
	 * Show admin dashboard
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	public function index( Request $request ): string
	{
		// Generate CSRF token and store in Registry for helper functions
		$sessionManager = new SessionManager();
		$sessionManager->start();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Dashboard' )
			->description( 'Admin Dashboard' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'index', 'admin' );
	}
}
