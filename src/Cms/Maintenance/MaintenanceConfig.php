<?php

namespace Neuron\Cms\Maintenance;

use Neuron\Data\Settings\Source\ISettingSource;

/**
 * Configuration handler for maintenance mode.
 */
class MaintenanceConfig
{
	private ?ISettingSource $_settingSource;
	private array $_config = [];

	/**
	 * @param ISettingSource|null $settingSource
	 */
	public function __construct( ?ISettingSource $settingSource = null )
	{
		$this->_settingSource = $settingSource;
		$this->loadConfig();
	}

	/**
	 * Load configuration from settings source
	 *
	 * @return void
	 */
	private function loadConfig(): void
	{
		if( !$this->_settingSource )
		{
			$this->_config = $this->getDefaults();
			return;
		}

		// Load maintenance configuration from settings
		$defaults = $this->getDefaults();
		$section = $this->_settingSource->getSection( 'maintenance' ) ?? [];

		$this->_config = [
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
		return $this->_config['enabled'] ?? false;
	}

	/**
	 * Get default maintenance message
	 *
	 * @return string
	 */
	public function getDefaultMessage(): string
	{
		return $this->_config['default_message'] ?? 'Site is currently under maintenance.';
	}

	/**
	 * Get allowed IP addresses from configuration
	 *
	 * @return array
	 */
	public function getAllowedIps(): array
	{
		$ips = $this->_config['allowed_ips'] ?? [];

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
		return (int)($this->_config['retry_after'] ?? 3600);
	}

	/**
	 * Get custom maintenance view path
	 *
	 * @return string|null
	 */
	public function getCustomView(): ?string
	{
		return $this->_config['custom_view'] ?? null;
	}

	/**
	 * Check if countdown should be shown
	 *
	 * @return bool
	 */
	public function shouldShowCountdown(): bool
	{
		return $this->_config['show_countdown'] ?? false;
	}

	/**
	 * Create MaintenanceConfig from settings source
	 *
	 * @param ISettingSource $source
	 * @return self
	 */
	public static function fromSettings( ISettingSource $source ): self
	{
		return new self( $source );
	}
}
