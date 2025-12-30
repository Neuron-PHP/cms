<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Exceptions\CsrfValidationException;
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
	private CsrfToken $_csrfToken;

	/** @var list<string> */
	private array $_exemptMethods = ['GET', 'HEAD', 'OPTIONS'];

	public function __construct( CsrfToken $csrfToken )
	{
		$this->_csrfToken = $csrfToken;

		parent::__construct(
			function( RouteMap $route ) { $this->validateCsrfToken( $route ); },
			null
		);
	}

	/**
	 * Validate CSRF token
	 *
	 * @throws CsrfValidationException When token is missing or invalid
	 */
	protected function validateCsrfToken( RouteMap $route ): void
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		// Skip validation for safe methods
		if( in_array( strtoupper( $method ), $this->_exemptMethods ) )
		{
			return;
		}

		// Get token from request
		$token = $this->getTokenFromRequest();

		if( !$token )
		{
			Log::warning( 'CSRF token missing from request' );
			throw new CsrfValidationException(
				'CSRF token missing from request',
				'CSRF token missing'
			);
		}

		// Validate token
		if( !$this->_csrfToken->validate( $token ) )
		{
			Log::warning( 'Invalid CSRF token' );
			throw new CsrfValidationException(
				'Invalid CSRF token provided',
				'Invalid CSRF token'
			);
		}
	}

	/**
	 * Get CSRF token from request
	 */
	private function getTokenFromRequest(): ?string
	{
		// Check POST data (filtered)
		$token = \Neuron\Data\Filters\Post::filterScalar( 'csrf_token' );
		if( $token )
		{
			return $token;
		}

		// Check headers
		if( isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) )
		{
			return $_SERVER['HTTP_X_CSRF_TOKEN'];
		}

		return null;
	}
}
