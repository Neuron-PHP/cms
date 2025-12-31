<?php
namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
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
	 * @param Application|null $app
	 * @param IRegistrationService|null $registrationService
	 * @param SettingManager|null $settings
	 * @param SessionManager|null $sessionManager
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?IRegistrationService $registrationService = null,
		?SettingManager $settings = null,
		?SessionManager $sessionManager = null
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
