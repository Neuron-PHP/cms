<?php

namespace Neuron\Cms\Maintenance;

use Neuron\Data\Setting\Source\ISettingSource;

/**
 * Configuration handler for maintenance mode.
 */
class MaintenanceConfig
{
	private ?ISettingSource $_SettingSource;
	private array $_Config = [];

	/**
	 * @param ISettingSource|null $SettingSource
	 */
	public function __construct( ?ISettingSource $SettingSource = null )
	{
		$this->_SettingSource = $SettingSource;
		$this->loadConfig();
	}

	/**
	 * Load configuration from settings source
	 *
	 * @return void
	 */
	private function loadConfig(): void
	{
		if( !$this->_SettingSource )
		{
			$this->_Config = $this->getDefaults();
			return;
		}

		// Load maintenance configuration from settings
		$defaults = $this->getDefaults();
		$section = $this->_SettingSource->getSection( 'maintenance' ) ?? [];

		$this->_Config = [
			'enabled' => $section['enabled'] ?? $defaults['enabled'],
			'default_message' => $section['default_message'] ?? $defaults['default_message'],
			'allowed_ips' => $section['allowed_ips'] ?? $defaults['allowed_ips'],
			'retry_after' => $section['retry_after'] ?? $defaults['retry_after'],
			'custom_view' => $section['custom_view'] ?? $defaults['custom_view'],
			'show_countdown' => $section['show_countdown'] ?? $defaults['show_countdown'],
		];
	}

	/**
	 * Get default configuration
	 *
	 * @return array
	 */
	private function getDefaults(): array
	{
		return [
			'enabled' => false,
			'default_message' => 'Site is currently under maintenance. Please check back soon.',
			'allowed_ips' => ['127.0.0.1', '::1'],
			'retry_after' => 3600,
			'custom_view' => null,
			'show_countdown' => false,
		];
	}

	/**
	 * Check if maintenance mode is enabled in configuration
	 *
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return $this->_Config['enabled'] ?? false;
	}

	/**
	 * Get default maintenance message
	 *
	 * @return string
	 */
	public function getDefaultMessage(): string
	{
		return $this->_Config['default_message'] ?? 'Site is currently under maintenance.';
	}

	/**
	 * Get allowed IP addresses from configuration
	 *
	 * @return array
	 */
	public function getAllowedIps(): array
	{
		$ips = $this->_Config['allowed_ips'] ?? [];

		// Ensure it's an array
		if( is_string( $ips ) )
		{
			$ips = array_map( 'trim', explode( ',', $ips ) );
		}

		return $ips;
	}

	/**
	 * Get default retry-after value
	 *
	 * @return int
	 */
	public function getRetryAfter(): int
	{
		return (int)($this->_Config['retry_after'] ?? 3600);
	}

	/**
	 * Get custom maintenance view path
	 *
	 * @return string|null
	 */
	public function getCustomView(): ?string
	{
		return $this->_Config['custom_view'] ?? null;
	}

	/**
	 * Check if countdown should be shown
	 *
	 * @return bool
	 */
	public function shouldShowCountdown(): bool
	{
		return $this->_Config['show_countdown'] ?? false;
	}

	/**
	 * Create MaintenanceConfig from settings source
	 *
	 * @param ISettingSource $Source
	 * @return self
	 */
	public static function fromSettings( ISettingSource $Source ): self
	{
		return new self( $Source );
	}
}
