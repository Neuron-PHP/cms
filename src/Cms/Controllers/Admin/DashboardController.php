<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
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

		$ViewData = [
			'Title' => 'Dashboard | ' . $this->getName(),
			'Description' => 'Admin Dashboard',
			'User' => $User,
			'WelcomeMessage' => 'Welcome back, ' . $User->getUsername() . '!'
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'index',
			'dashboard'
		);
	}
}
