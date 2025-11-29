<?php

namespace Neuron\Cms\Maintenance;

use DateTime;

/**
 * Manages maintenance mode state and operations.
 */
class MaintenanceManager
{
	private string $_maintenanceFilePath;

	/**
	 * @param string $basePath Application base path
	 */
	public function __construct( string $basePath )
	{
		$this->_maintenanceFilePath = $basePath . '/.maintenance.json';
	}

	/**
	 * Check if maintenance mode is currently enabled
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		if( !file_exists( $this->_maintenanceFilePath ) )
		{
			return false;
		}

		$data = $this->readMaintenanceFile();

		return $data['enabled'] ?? false;
	}

	/**
	 * Enable maintenance mode
	 *
	 * @param string $message Custom maintenance message
	 * @param array|null $allowedIps List of allowed IP addresses (null = use defaults)
	 * @param int|null $retryAfter Retry-After header value in seconds
	 * @param string|null $enabledBy User who enabled maintenance mode
	 * @return bool Success status
	 */
	public function enable(
		string $message = 'Site is currently under maintenance. Please check back soon.',
		?array $allowedIps = null,
		?int $retryAfter = null,
		?string $enabledBy = null
	): bool
	{
		// Add localhost to allowed IPs by default only if null (not provided)
		if( $allowedIps === null )
		{
			$allowedIps = ['127.0.0.1', '::1'];
		}

		$data = [
			'enabled' => true,
			'message' => $message,
			'allowed_ips' => $allowedIps,
			'retry_after' => $retryAfter,
			'enabled_at' => (new DateTime())->format( DateTime::ATOM ),
			'enabled_by' => $enabledBy ?? get_current_user()
		];

		$result = $this->writeMaintenanceFile( $data );

		// Emit maintenance mode enabled event
		if( $result )
		{
			\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\MaintenanceModeEnabledEvent(
				$data['enabled_by'],
				$message
			) );
		}

		return $result;
	}

	/**
	 * Disable maintenance mode
	 *
	 * @param string|null $disabledBy User who disabled maintenance mode
	 * @return bool Success status
	 */
	public function disable( ?string $disabledBy = null ): bool
	{
		// Get who is disabling before we delete the file
		$disabledByUser = $disabledBy ?? get_current_user();

		if( file_exists( $this->_maintenanceFilePath ) )
		{
			$result = unlink( $this->_maintenanceFilePath );

			// Emit maintenance mode disabled event
			if( $result )
			{
				\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\MaintenanceModeDisabledEvent(
					$disabledByUser
				) );
			}

			return $result;
		}

		return true;
	}

	/**
	 * Get current maintenance status and configuration
	 *
	 * @return array|null
	 */
	public function getStatus(): ?array
	{
		if( !file_exists( $this->_maintenanceFilePath ) )
		{
			return null;
		}

		return $this->readMaintenanceFile();
	}

	/**
	 * Check if a specific IP address is allowed during maintenance
	 *
	 * @param string $ipAddress
	 * @return bool
	 */
	public function isIpAllowed( string $ipAddress ): bool
	{
		$status = $this->getStatus();

		if( !$status )
		{
			return true;
		}

		$allowedIps = $status['allowed_ips'] ?? [];

		foreach( $allowedIps as $allowed )
		{
			// Check for exact match
			if( $allowed === $ipAddress )
			{
				return true;
			}

			// Check for CIDR notation
			if( strpos( $allowed, '/' ) !== false )
			{
				if( $this->ipInCidr( $ipAddress, $allowed ) )
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the maintenance message
	 *
	 * @return string
	 */
	public function getMessage(): string
	{
		$status = $this->getStatus();

		return $status['message'] ?? 'Site is currently under maintenance.';
	}

	/**
	 * Get the retry-after value
	 *
	 * @return int|null
	 */
	public function getRetryAfter(): ?int
	{
		$status = $this->getStatus();

		return $status['retry_after'] ?? null;
	}

	/**
	 * Read maintenance file
	 *
	 * @return array
	 */
	private function readMaintenanceFile(): array
	{
		$contents = file_get_contents( $this->_maintenanceFilePath );

		if( $contents === false )
		{
			return [];
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Write maintenance file
	 *
	 * @param array $data
	 * @return bool
	 */
	private function writeMaintenanceFile( array $data ): bool
	{
		$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$result = file_put_contents( $this->_maintenanceFilePath, $json );

		return $result !== false;
	}

	/**
	 * Check if an IP address is within a CIDR range
	 *
	 * @param string $ipAddress IP address to check
	 * @param string $cidr CIDR notation (e.g., "192.168.1.0/24")
	 * @return bool
	 */
	private function ipInCidr( string $ipAddress, string $cidr ): bool
	{
		list( $subnet, $mask ) = explode( '/', $cidr );

		// Convert IP addresses to long integers
		$ipLong = ip2long( $ipAddress );
		$subnetLong = ip2long( $subnet );

		if( $ipLong === false || $subnetLong === false )
		{
			return false;
		}

		// Calculate the network mask
		$maskLong = -1 << (32 - (int)$mask);

		// Compare network portions
		return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
	}
}
