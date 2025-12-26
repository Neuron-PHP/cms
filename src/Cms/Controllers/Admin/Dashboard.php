<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;

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
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Dashboard' )
			->description( 'Admin Dashboard' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'index', 'admin' );
	}
}
