<?php

namespace Neuron\Cms\Config;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\Source\Yaml;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;

/**
 * Loads config/auth.yaml and applies its settings to the auth services.
 *
 * auth.yaml is a CMS file: the framework's SettingManagerFactory only reads
 * neuron.yaml, the environment file and the secrets. load() layers auth.yaml
 * underneath those, so any of its keys can still be overridden per
 * environment (for example auth.throttle in environments/production.yaml).
 *
 * @package Neuron\Cms\Config
 */
final class AuthConfig
{
	public const FILE = 'auth.yaml';

	private function __construct()
	{
		// Static helpers only
	}

	/**
	 * Merge <configPath>/auth.yaml into the settings, below the values that
	 * are already loaded. Safe to call more than once.
	 *
	 * @param SettingManager $settings
	 * @param string $configPath Directory holding neuron.yaml
	 * @return bool True if auth.yaml was found and merged
	 */
	public static function load( SettingManager $settings, string $configPath ): bool
	{
		$file = rtrim( $configPath, '/' ) . '/' . self::FILE;

		if( !is_file( $file ) )
		{
			return false;
		}

		try
		{
			$authData = self::extract( new Yaml( $file ) );
		}
		catch( \Exception $e )
		{
			Log::error( "Ignoring $file: " . $e->getMessage() );
			return false;
		}

		$source = $settings->getSource();
		$loaded = $source !== null ? self::extract( $source ) : [];

		$settings->setSource( new Memory( SettingManager::deepMerge( $authData, $loaded ) ) );

		return true;
	}

	/**
	 * Settings for CLI commands: the Registry's settings with auth.yaml from
	 * the project's config directory (the CLI runs from the project root).
	 *
	 * @param string|null $configPath Defaults to <cwd>/config
	 * @return SettingManager|null
	 */
	public static function cliSettings( ?string $configPath = null ): ?SettingManager
	{
		$settings = self::currentSettings();

		if( $settings === null )
		{
			return null;
		}

		self::load( $settings, $configPath ?? getcwd() . '/config' );

		return $settings;
	}

	/**
	 * The application's settings from the Registry (auth.yaml is already
	 * merged in once the CMS has booted).
	 */
	public static function currentSettings(): ?SettingManager
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		return $settings instanceof SettingManager ? $settings : null;
	}

	/**
	 * SessionManager configuration from auth.session, falling back to the
	 * top-level session.lifetime (seconds) for older configs.
	 *
	 * auth.session.lifetime is in minutes. cookie_secure accepts true, false
	 * or auto (secure on HTTPS requests).
	 *
	 * @param SettingManager|null $settings
	 * @return array<string, mixed> Keys understood by SessionManager
	 */
	public static function sessionConfig( ?SettingManager $settings ): array
	{
		$config = [];

		if( $settings === null )
		{
			return $config;
		}

		$legacyLifetime = $settings->get( 'session', 'lifetime' );

		if( is_numeric( $legacyLifetime ) && (int)$legacyLifetime > 0 )
		{
			$config['lifetime'] = (int)$legacyLifetime;
		}

		$session = $settings->get( 'auth', 'session' );

		if( !is_array( $session ) )
		{
			return $config;
		}

		if( isset( $session['lifetime'] ) && is_numeric( $session['lifetime'] ) && (int)$session['lifetime'] > 0 )
		{
			$config['lifetime'] = (int)$session['lifetime'] * 60;
		}

		foreach( [ 'expire_on_close', 'cookie_httponly' ] as $key )
		{
			if( array_key_exists( $key, $session ) )
			{
				$config[$key] = filter_var( $session[$key], FILTER_VALIDATE_BOOLEAN );
			}
		}

		if( array_key_exists( 'cookie_secure', $session ) )
		{
			$secure = $session['cookie_secure'];
			$config['cookie_secure'] = ( $secure === null || strtolower( trim( (string)$secure ) ) === 'auto' )
				? 'auto'
				: filter_var( $secure, FILTER_VALIDATE_BOOLEAN );
		}

		if( isset( $session['cookie_name'] ) )
		{
			$name = trim( (string)$session['cookie_name'] );

			if( preg_match( '/^(?=.*[A-Za-z])[A-Za-z0-9_]+$/', $name ) )
			{
				$config['cookie_name'] = $name;
			}
			else
			{
				Log::warning( "Ignoring auth.session.cookie_name '$name': use letters, digits and underscores." );
			}
		}

		if( isset( $session['cookie_samesite'] ) )
		{
			$sameSite = ucfirst( strtolower( trim( (string)$session['cookie_samesite'] ) ) );

			if( in_array( $sameSite, [ 'Lax', 'Strict', 'None' ], true ) )
			{
				$config['cookie_samesite'] = $sameSite;
			}
			else
			{
				Log::warning( "Ignoring auth.session.cookie_samesite '$sameSite': use Lax, Strict or None." );
			}
		}

		return $config;
	}

	/**
	 * Apply auth.throttle (enabled, max_attempts, lockout_duration).
	 *
	 * @param Authentication $authentication
	 * @param SettingManager|null $settings
	 * @return void
	 */
	public static function configureThrottle( Authentication $authentication, ?SettingManager $settings ): void
	{
		$throttle = $settings?->get( 'auth', 'throttle' );

		if( !is_array( $throttle ) )
		{
			return;
		}

		if( array_key_exists( 'enabled', $throttle ) )
		{
			$authentication->setThrottleEnabled( filter_var( $throttle['enabled'], FILTER_VALIDATE_BOOLEAN ) );
		}

		if( isset( $throttle['max_attempts'] ) && (int)$throttle['max_attempts'] > 0 )
		{
			$authentication->setMaxLoginAttempts( (int)$throttle['max_attempts'] );
		}

		if( isset( $throttle['lockout_duration'] ) && (int)$throttle['lockout_duration'] > 0 )
		{
			$authentication->setLockoutDuration( (int)$throttle['lockout_duration'] );
		}
	}

	/**
	 * Apply auth.remember (enabled, lifetime in minutes).
	 *
	 * @param Authentication $authentication
	 * @param SettingManager|null $settings
	 * @return void
	 */
	public static function configureRemember( Authentication $authentication, ?SettingManager $settings ): void
	{
		$remember = $settings?->get( 'auth', 'remember' );

		if( !is_array( $remember ) )
		{
			return;
		}

		if( isset( $remember['lifetime'] ) && (int)$remember['lifetime'] > 0 )
		{
			$authentication->setRememberLifetime( (int)$remember['lifetime'] );
		}

		if( array_key_exists( 'enabled', $remember ) )
		{
			$authentication->setRememberEnabled( filter_var( $remember['enabled'], FILTER_VALIDATE_BOOLEAN ) );
		}
	}

	/**
	 * Apply the auth.passwords policy (min_length, require_*, hash_algorithm).
	 *
	 * @param PasswordHasher $hasher
	 * @param SettingManager|null $settings
	 * @return void
	 */
	public static function configurePasswords( PasswordHasher $hasher, ?SettingManager $settings ): void
	{
		$passwords = $settings?->get( 'auth', 'passwords' );

		if( !is_array( $passwords ) )
		{
			return;
		}

		if( isset( $passwords['min_length'] ) && (int)$passwords['min_length'] > 0 )
		{
			$hasher->setMinLength( (int)$passwords['min_length'] );
		}

		$flags = [
			'require_uppercase'     => 'setRequireUppercase',
			'require_lowercase'     => 'setRequireLowercase',
			'require_numbers'       => 'setRequireNumbers',
			'require_special_chars' => 'setRequireSpecialChars',
		];

		foreach( $flags as $key => $setter )
		{
			if( array_key_exists( $key, $passwords ) )
			{
				$hasher->$setter( filter_var( $passwords[$key], FILTER_VALIDATE_BOOLEAN ) );
			}
		}

		if( isset( $passwords['hash_algorithm'] ) && $passwords['hash_algorithm'] !== '' )
		{
			try
			{
				$hasher->setAlgorithm( (string)$passwords['hash_algorithm'] );
			}
			catch( \InvalidArgumentException $e )
			{
				Log::warning( 'Ignoring auth.passwords.hash_algorithm: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * @param \Neuron\Data\Settings\Source\ISettingSource $source
	 * @return array<string, mixed>
	 */
	private static function extract( \Neuron\Data\Settings\Source\ISettingSource $source ): array
	{
		$data = [];

		foreach( $source->getSectionNames() as $section )
		{
			$sectionData = $source->getSection( $section );

			if( $sectionData !== null )
			{
				$data[$section] = $sectionData;
			}
		}

		return $data;
	}
}
