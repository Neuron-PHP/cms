<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Member dashboard controller.
 *
 * @package Neuron\Cms\Controllers\Member
 */
#[RouteGroup(prefix: '/member', filters: ['member'])]
class Dashboard extends Content
{
	/**
	 * @param Application|null $app
	 * @param SettingManager|null $settings
	 * @param SessionManager|null $sessionManager
	 * @return void
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?SettingManager $settings = null,
		?SessionManager $sessionManager = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );
	}

	/**
	 * Show member dashboard
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/dashboard', name: 'member_dashboard')]
	#[Get('/', name: 'member')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Member Dashboard' )
			->description( 'Member Dashboard' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'index', 'member' );
	}
}
