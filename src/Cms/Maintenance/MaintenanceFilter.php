<?php

namespace Neuron\Cms\Maintenance;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;

/**
 * Route filter that intercepts requests during maintenance mode.
 */
class MaintenanceFilter extends Filter
{
	private MaintenanceManager $_Manager;
	private ?string $_CustomView;

	/**
	 * @param MaintenanceManager $Manager
	 * @param string|null $CustomView Path to custom maintenance view
	 */
	public function __construct( MaintenanceManager $Manager, ?string $CustomView = null )
	{
		$this->_Manager = $Manager;
		$this->_CustomView = $CustomView;

		// Create filter with pre-execution check
		parent::__construct(
			function( RouteMap $Route ) {
				return $this->checkMaintenance( $Route );
			},
			null
		);
	}

	/**
	 * Check if site is in maintenance mode and handle accordingly
	 *
	 * @param RouteMap $Route
	 * @return mixed|null Returns maintenance page or null to continue
	 */
	private function checkMaintenance( RouteMap $Route )
	{
		// If maintenance mode is not enabled, continue normal execution
		if( !$this->_Manager->isEnabled() )
		{
			return null;
		}

		// Check if current IP is allowed
		$clientIp = $this->getClientIp();

		if( $this->_Manager->isIpAllowed( $clientIp ) )
		{
			return null;
		}

		// Return maintenance mode response
		return $this->renderMaintenancePage();
	}

	/**
	 * Render the maintenance mode page
	 *
	 * @return string
	 */
	private function renderMaintenancePage(): string
	{
		// Set HTTP status code (suppress errors in CLI/test environments)
		@http_response_code( 503 );

		// Set Retry-After header if configured
		$retryAfter = $this->_Manager->getRetryAfter();
		if( $retryAfter )
		{
			@header( "Retry-After: $retryAfter" );
		}

		// Set content type
		@header( 'Content-Type: text/html; charset=utf-8' );

		// Check for custom view
		if( $this->_CustomView && file_exists( $this->_CustomView ) )
		{
			ob_start();
			$message = $this->_Manager->getMessage();
			$retryAfter = $this->_Manager->getRetryAfter();
			include $this->_CustomView;
			return ob_get_clean();
		}

		// Return default maintenance page
		return $this->getDefaultMaintenancePage();
	}

	/**
	 * Get default maintenance page HTML
	 *
	 * @return string
	 */
	private function getDefaultMaintenancePage(): string
	{
		$message = htmlspecialchars( $this->_Manager->getMessage(), ENT_QUOTES, 'UTF-8' );
		$retryAfter = $this->_Manager->getRetryAfter();

		$estimatedTime = '';
		if( $retryAfter )
		{
			$hours = floor( $retryAfter / 3600 );
			$minutes = floor( ($retryAfter % 3600) / 60 );

			if( $hours > 0 )
			{
				$estimatedTime = "<p class=\"estimated-time\">Estimated time: {$hours} hour" .
					($hours > 1 ? 's' : '') .
					($minutes > 0 ? " and {$minutes} minute" . ($minutes > 1 ? 's' : '') : '') .
					"</p>";
			}
			elseif( $minutes > 0 )
			{
				$estimatedTime = "<p class=\"estimated-time\">Estimated time: {$minutes} minute" .
					($minutes > 1 ? 's' : '') .
					"</p>";
			}
		}

		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title>Site Maintenance</title>
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #333;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			padding: 20px;
		}

		.container {
			background: white;
			border-radius: 12px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
			max-width: 600px;
			width: 100%;
			padding: 60px 40px;
			text-align: center;
		}

		.icon {
			font-size: 80px;
			margin-bottom: 20px;
			opacity: 0.9;
		}

		h1 {
			font-size: 32px;
			font-weight: 600;
			color: #2d3748;
			margin-bottom: 20px;
		}

		.message {
			font-size: 18px;
			line-height: 1.6;
			color: #4a5568;
			margin-bottom: 30px;
		}

		.estimated-time {
			font-size: 16px;
			color: #718096;
			font-style: italic;
		}

		.footer {
			margin-top: 40px;
			padding-top: 30px;
			border-top: 1px solid #e2e8f0;
			font-size: 14px;
			color: #a0aec0;
		}

		@media (max-width: 600px) {
			.container {
				padding: 40px 30px;
			}

			h1 {
				font-size: 28px;
			}

			.message {
				font-size: 16px;
			}

			.icon {
				font-size: 60px;
			}
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="icon">🔧</div>
		<h1>Under Maintenance</h1>
		<p class="message">{$message}</p>
		{$estimatedTime}
		<div class="footer">
			Thank you for your patience
		</div>
	</div>
</body>
</html>
HTML;
	}

	/**
	 * Get the client's IP address
	 *
	 * @return string
	 */
	private function getClientIp(): string
	{
		// Check for forwarded IP addresses
		$headers = [
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR'
		];

		foreach( $headers as $header )
		{
			if( isset( $_SERVER[$header] ) )
			{
				$ip = $_SERVER[$header];

				// Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
				if( strpos( $ip, ',' ) !== false )
				{
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}

				// Validate IP address
				if( filter_var( $ip, FILTER_VALIDATE_IP ) )
				{
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
