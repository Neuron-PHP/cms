<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin dashboard controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class DashboardController extends Content
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
	 * @param array $parameters
	 * @return string
	 */
	public function index( array $parameters ): string
	{
		// Get authenticated user from Registry
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found in Registry' );
		}

		// Generate CSRF token and store in Registry for helper functions
		$sessionManager = new SessionManager();
		$sessionManager->start();
		$csrfManager = new CsrfTokenManager( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$viewData = [
			'Title' => 'Dashboard | ' . $this->getName(),
			'Description' => 'Admin Dashboard',
			'User' => $user,
			'WelcomeMessage' => 'Welcome back, ' . $user->getUsername() . '!'
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'admin'
		);
	}
}
