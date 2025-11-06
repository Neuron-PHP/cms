<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Log\Log;

/**
 * CSRF protection filter.
 *
 * Validates CSRF tokens on POST, PUT, DELETE requests
 * to prevent Cross-Site Request Forgery attacks.
 *
 * @package Neuron\Cms\Auth\Filters
 */
class CsrfFilter extends Filter
{
	private CsrfTokenManager $_CsrfManager;
	private array $_ExemptMethods = ['GET', 'HEAD', 'OPTIONS'];

	public function __construct( CsrfTokenManager $CsrfManager )
	{
		$this->_CsrfManager = $CsrfManager;

		parent::__construct(
			function( RouteMap $Route ) { $this->validateCsrfToken( $Route ); },
			null
		);
	}

	/**
	 * Validate CSRF token
	 */
	protected function validateCsrfToken( RouteMap $Route ): void
	{
		$Method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		// Skip validation for safe methods
		if( in_array( strtoupper( $Method ), $this->_ExemptMethods ) )
		{
			return;
		}

		// Get token from request
		$Token = $this->getTokenFromRequest();

		if( !$Token )
		{
			Log::warning( 'CSRF token missing from request' );
			$this->respondForbidden( 'CSRF token missing' );
		}

		// Validate token
		if( !$this->_CsrfManager->validate( $Token ) )
		{
			Log::warning( 'Invalid CSRF token' );
			$this->respondForbidden( 'Invalid CSRF token' );
		}
	}

	/**
	 * Get CSRF token from request
	 */
	private function getTokenFromRequest(): ?string
	{
		// Check POST data
		if( isset( $_POST['csrf_token'] ) )
		{
			return $_POST['csrf_token'];
		}

		// Check headers
		if( isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) )
		{
			return $_SERVER['HTTP_X_CSRF_TOKEN'];
		}

		return null;
	}

	/**
	 * Respond with 403 Forbidden
	 */
	private function respondForbidden( string $Message ): void
	{
		http_response_code( 403 );
		echo '<h1>403 Forbidden</h1>';
		echo '<p>' . htmlspecialchars( $Message ) . '</p>';
		exit;
	}
}
