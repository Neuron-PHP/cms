<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Member dashboard controller.
 *
 * @package Neuron\Cms\Controllers\Member
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
	 * Show member dashboard
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	public function index( Request $request ): string
	{
		// Get authenticated user from Registry
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found in Registry' );
		}

		// Generate CSRF token and store in Registry
		$csrfManager = new CsrfTokenManager( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$viewData = [
			'Title' => 'Member Dashboard | ' . $this->getName(),
			'Description' => 'Member Dashboard',
			'User' => $user
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'member/dashboard'
		);
	}
}
