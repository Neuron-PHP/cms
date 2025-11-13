<?php

namespace App\Initializers;

use Neuron\Mvc\Views\ViewDataProvider;
use Neuron\Patterns\IRunnable;
use Neuron\Patterns\Registry;

/**
 * Initialize global view data that should be available in all views.
 *
 * This initializer demonstrates how to use ViewDataProvider to inject
 * commonly-needed data into all views without requiring controllers to
 * manually pass it or views to access the Registry directly.
 *
 * The ViewDataProvider eliminates the need for views to directly access
 * Registry::getInstance(), making views cleaner and more testable.
 *
 * Usage:
 * - Static values are shared once and reused
 * - Callables are executed on each request (lazy evaluation)
 * - All shared data is automatically injected into view templates
 *
 * @package App\Initializers
 */
class ViewDataInitializer implements IRunnable
{
	/**
	 * Run the initializer
	 *
	 * Register global view data that should be available in all views.
	 * Customize this method to share your application's global data.
	 *
	 * @param array $argv
	 * @return mixed
	 */
	public function run( array $argv = [] ): mixed
	{
		$registry = Registry::getInstance();
		$provider = ViewDataProvider::getInstance();

		// Site name - pulled from Registry 'name' key
		$provider->share( 'siteName', function() use ( $registry ) {
			return $registry->get( 'name' ) ?? 'Neuron CMS';
		});

		// Application version
		$provider->share( 'appVersion', function() use ( $registry ) {
			return $registry->get( 'version' ) ?? '';
		});

		// Current authenticated user
		$provider->share( 'currentUser', function() use ( $registry ) {
			return $registry->get( 'Auth.User' );
		});

		// Theme from settings with sensible defaults per context
		$provider->share( 'theme', function() use ( $registry ) {
			$settings = $registry->get( 'Settings' );
			return $settings?->get( 'theme', 'default' ) ?? 'sandstone';
		});

		// Current year (useful for copyright notices)
		$provider->share( 'currentYear', fn() => date( 'Y' ) );

		// Check if user is authenticated (convenience helper)
		$provider->share( 'isAuthenticated', function() use ( $registry ) {
			$authManager = $registry->get( 'AuthManager' );
			return $authManager && $authManager->check();
		});

		return null;
	}
}
