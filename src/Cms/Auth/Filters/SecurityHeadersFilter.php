<?php

namespace Neuron\Cms\Auth\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;

/**
 * Security Headers Filter
 *
 * Adds security headers to all HTTP responses to protect against common attacks:
 * - X-Frame-Options: Prevents clickjacking
 * - X-Content-Type-Options: Prevents MIME sniffing
 * - X-XSS-Protection: Legacy XSS protection for older browsers
 * - Referrer-Policy: Controls referrer information
 * - Content-Security-Policy: Restricts resource loading
 * - Strict-Transport-Security: Enforces HTTPS
 *
 * @package Neuron\Cms\Auth\Filters
 */
class SecurityHeadersFilter extends Filter
{
	/** @var array<string, mixed> */
	private array $_config;

	/**
	 * @param array<string, mixed> $config Optional security header configuration
	 */
	public function __construct( array $config = [] )
	{
		// Default secure configuration
		$defaults = [
			// Prevent clickjacking - deny embedding in frames
			'X-Frame-Options' => 'DENY',

			// Prevent MIME type sniffing
			'X-Content-Type-Options' => 'nosniff',

			// Legacy XSS protection for older browsers (modern browsers use CSP)
			'X-XSS-Protection' => '1; mode=block',

			// Control referrer information leakage
			'Referrer-Policy' => 'strict-origin-when-cross-origin',

			// Content Security Policy - restrictive by default
			// This is a good starting point but may need customization per application
			'Content-Security-Policy' => implode( '; ', [
				"default-src 'self'",                    // Only load resources from same origin
				"script-src 'self' 'unsafe-inline'",     // Allow inline scripts (needed for many apps)
				"style-src 'self' 'unsafe-inline'",      // Allow inline styles
				"img-src 'self' data: https:",           // Allow images from self, data URIs, and HTTPS
				"font-src 'self' data:",                 // Allow fonts from self and data URIs
				"connect-src 'self'",                    // Allow AJAX to same origin only
				"frame-ancestors 'none'",                // Prevent embedding (redundant with X-Frame-Options)
				"base-uri 'self'",                       // Restrict base tag URLs
				"form-action 'self'",                    // Restrict form submissions
			] ),

			// Strict Transport Security - enforce HTTPS for 1 year
			// Note: Only sent over HTTPS connections
			'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',

			// Permissions Policy (formerly Feature-Policy) - restrict browser features
			'Permissions-Policy' => implode( ', ', [
				'geolocation=()',       // Disable geolocation
				'microphone=()',        // Disable microphone
				'camera=()',            // Disable camera
				'payment=()',           // Disable payment API
			] ),
		];

		$this->_config = array_merge( $defaults, $config );

		// Use post-processing to add headers after route execution
		parent::__construct(
			null,
			function( RouteMap $route ) { $this->addSecurityHeaders(); }
		);
	}

	/**
	 * Add security headers to the response
	 */
	protected function addSecurityHeaders(): void
	{
		// Only add HSTS if connection is secure
		$isSecure = !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off';

		foreach( $this->_config as $header => $value )
		{
			// Skip HSTS on non-HTTPS connections
			if( $header === 'Strict-Transport-Security' && !$isSecure )
			{
				continue;
			}

			// Don't overwrite existing headers
			if( !headers_sent() && !$this->headerExists( $header ) )
			{
				header( "$header: $value" );
			}
		}
	}

	/**
	 * Check if a header already exists
	 */
	private function headerExists( string $headerName ): bool
	{
		$headers = headers_list();
		$headerName = strtolower( $headerName );

		foreach( $headers as $header )
		{
			// Header format is "Name: Value"
			$parts = explode( ':', $header, 2 );
			if( strtolower( trim( $parts[0] ) ) === $headerName )
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Get current configuration
	 *
	 * @return array<string, mixed>
	 */
	public function getConfig(): array
	{
		return $this->_config;
	}

	/**
	 * Update a specific header configuration
	 */
	public function setHeader( string $header, string $value ): self
	{
		$this->_config[ $header ] = $value;
		return $this;
	}

	/**
	 * Remove a header from configuration
	 */
	public function removeHeader( string $header ): self
	{
		unset( $this->_config[ $header ] );
		return $this;
	}
}
