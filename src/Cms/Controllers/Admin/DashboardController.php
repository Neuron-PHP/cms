<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\Html;
use Neuron\Patterns\Registry;

/**
 * Admin dashboard controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class DashboardController extends Content
{
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );
	}

	/**
	 * Show admin dashboard
	 */
	public function index( array $Parameters ): string
	{
		// Get authenticated user from Registry
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found in Registry' );
		}

		// Generate CSRF token and store in Registry for helper functions
		$SessionManager = new SessionManager();
		$SessionManager->start();
		$CsrfManager = new CsrfTokenManager( $SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		$ViewData = [
			'Title' => 'Dashboard | ' . $this->getName(),
			'Description' => 'Admin Dashboard',
			'User' => $User,
			'WelcomeMessage' => 'Welcome back, ' . $User->getUsername() . '!'
		];

		// Manually render with custom controller path
		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Dashboard' )
			 ->setLayout( 'admin' )
			 ->setPage( 'index' );

		return $View->render( $ViewData );
	}
}
