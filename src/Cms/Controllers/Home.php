<?php
namespace Neuron\Cms\Controllers;

use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Core\Exceptions\NotFound;
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
	private ?IRegistrationService $_registrationService;

	/**
	 * @param Application|null $app
	 * @param IRegistrationService|null $registrationService
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?IRegistrationService $registrationService = null
	)
	{
		parent::__construct( $app );

		// Use dependency injection when available (container provides dependencies)
		// Otherwise resolve from container (fallback for compatibility)
		$this->_registrationService = $registrationService ?? $app?->getContainer()?->get( IRegistrationService::class );
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
		$registrationEnabled = $this->_registrationService?->isRegistrationEnabled() ?? false;

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
