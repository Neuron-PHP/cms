<?php

namespace Neuron\Cms\Maintenance;

use DateTime;

/**
 * Manages maintenance mode state and operations.
 */
class MaintenanceManager
{
	private string $_MaintenanceFilePath;

	/**
	 * @param string $BasePath Application base path
	 */
	public function __construct( string $BasePath )
	{
		$this->_MaintenanceFilePath = $BasePath . '/.maintenance.json';
	}

	/**
	 * Check if maintenance mode is currently enabled
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		if( !file_exists( $this->_MaintenanceFilePath ) )
		{
			return false;
		}

		$data = $this->readMaintenanceFile();

		return $data['enabled'] ?? false;
	}

	/**
	 * Enable maintenance mode
	 *
	 * @param string $Message Custom maintenance message
	 * @param array|null $AllowedIps List of allowed IP addresses (null = use defaults)
	 * @param int|null $RetryAfter Retry-After header value in seconds
	 * @param string|null $EnabledBy User who enabled maintenance mode
	 * @return bool Success status
	 */
	public function enable(
		string $Message = 'Site is currently under maintenance. Please check back soon.',
		?array $AllowedIps = null,
		?int $RetryAfter = null,
		?string $EnabledBy = null
	): bool
	{
		// Add localhost to allowed IPs by default only if null (not provided)
		if( $AllowedIps === null )
		{
			$AllowedIps = ['127.0.0.1', '::1'];
		}

		$data = [
			'enabled' => true,
			'message' => $Message,
			'allowed_ips' => $AllowedIps,
			'retry_after' => $RetryAfter,
			'enabled_at' => (new DateTime())->format( DateTime::ATOM ),
			'enabled_by' => $EnabledBy ?? get_current_user()
		];

		return $this->writeMaintenanceFile( $data );
	}

	/**
	 * Disable maintenance mode
	 *
	 * @return bool Success status
	 */
	public function disable(): bool
	{
		if( file_exists( $this->_MaintenanceFilePath ) )
		{
			return unlink( $this->_MaintenanceFilePath );
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
		if( !file_exists( $this->_MaintenanceFilePath ) )
		{
			return null;
		}

		return $this->readMaintenanceFile();
	}

	/**
	 * Check if a specific IP address is allowed during maintenance
	 *
	 * @param string $IpAddress
	 * @return bool
	 */
	public function isIpAllowed( string $IpAddress ): bool
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
			if( $allowed === $IpAddress )
			{
				return true;
			}

			// Check for CIDR notation
			if( strpos( $allowed, '/' ) !== false )
			{
				if( $this->ipInCidr( $IpAddress, $allowed ) )
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
		$contents = file_get_contents( $this->_MaintenanceFilePath );

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

		$result = file_put_contents( $this->_MaintenanceFilePath, $json );

		return $result !== false;
	}

	/**
	 * Check if an IP address is within a CIDR range
	 *
	 * @param string $IpAddress IP address to check
	 * @param string $Cidr CIDR notation (e.g., "192.168.1.0/24")
	 * @return bool
	 */
	private function ipInCidr( string $IpAddress, string $Cidr ): bool
	{
		list( $subnet, $mask ) = explode( '/', $Cidr );

		// Convert IP addresses to long integers
		$ipLong = ip2long( $IpAddress );
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
