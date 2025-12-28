<?php
namespace Neuron\Cms\Controllers;

use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
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
		$registrationEnabled = false;
		$registrationService = Registry::getInstance()->get( 'RegistrationService' );

		if( $registrationService && method_exists( $registrationService, 'isRegistrationEnabled' ) )
		{
			$registrationEnabled = $registrationService->isRegistrationEnabled();
		}

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
