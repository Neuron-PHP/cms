<?php
namespace Neuron\Cms\Controllers;

use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;

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
	 * @param array $parameters Route parameters
	 * @param Request|null $request The HTTP request
	 * @return string Rendered HTML response
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function index( array $parameters, ?Request $request ): string
	{
		// Check if registration is enabled
		$registrationEnabled = false;
		$registrationService = \Neuron\Patterns\Registry::getInstance()->get( 'RegistrationService' );

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
