<?php
namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;

/**
 * Home controller for the main landing page
 *
 * Provides the homepage/landing page for the CMS, serving as the entry point
 * for visitors. The homepage typically includes welcome content, site navigation,
 * and links to key sections like the blog and admin dashboard.
 *
 * @package Neuron\Cms\Controllers
 */
class Home extends Content
{
	private IRegistrationService $_registrationService;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IRegistrationService|null $registrationService
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		?IRegistrationService $registrationService = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		// Pure dependency injection - no service locator fallback
		if( $registrationService === null )
		{
			throw new \InvalidArgumentException( 'IRegistrationService must be injected' );
		}

		$this->_registrationService = $registrationService;
	}

	/**
	 * Display the homepage
	 *
	 * @param Request $request The HTTP request
	 * @return string Rendered HTML response
	 * @throws NotFound
	 */
	#[Get('/', name: 'home')]
	public function index( Request $request ): string
	{
		// Check if registration is enabled
		$registrationEnabled = $this->_registrationService->isRegistrationEnabled();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title' => $this->getTitle(),
				'Name' => $this->getName(),
				'Description' => $this->getDescription(),
				'RegistrationEnabled' => $registrationEnabled,
			],
			'index'
		);
	}
}
