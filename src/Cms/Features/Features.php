<?php

namespace Neuron\Cms\Features;

use Neuron\Cms\Controllers;
use Neuron\Cms\Controllers\Admin;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use Neuron\Routing\RouteMap;

/**
 * Optional CMS areas a site can switch off from neuron.yaml.
 *
 *   features:
 *     blog: true
 *     store: false
 *
 * A missing key means the area is on, so sites that never mention
 * `features` keep everything. Turning an area off makes its public and
 * admin routes answer 404 (see FeatureFilter), drops it from the admin
 * navigation and dashboard, and silences its shortcodes. Its data is left
 * alone.
 *
 * @package Neuron\Cms\Features
 */
class Features
{
	public const BLOG = 'blog';
	public const CALENDAR = 'calendar';
	public const CONTACT = 'contact';
	public const PAYMENTS = 'payments';
	public const STORE = 'store';
	public const TEAMS = 'teams';
	public const CAROUSELS = 'carousels';
	public const TESTIMONIALS = 'testimonials';
	public const FAQS = 'faqs';

	/**
	 * Controllers each area owns. Every route on these controllers belongs
	 * to the area unless ROUTES says otherwise.
	 *
	 * @var array<string, list<class-string>>
	 */
	private const CONTROLLERS = [
		self::BLOG => [
			Controllers\Blog::class,
			Admin\Posts::class,
			Admin\Categories::class,
			Admin\Tags::class,
		],
		self::CALENDAR => [
			Controllers\Calendar::class,
			Controllers\EventRegistration::class,
			Admin\Events::class,
			Admin\EventCategories::class,
			Admin\EventRegistrations::class,
		],
		self::CONTACT => [
			Controllers\Contact::class,
			Admin\ContactSubmissions::class,
		],
		self::PAYMENTS => [
			Controllers\Payments::class,
			Admin\Payments::class,
			Admin\Subscriptions::class,
		],
		self::STORE => [
			Controllers\Store::class,
			Admin\Products::class,
			Admin\Orders::class,
		],
		self::TEAMS => [ Admin\Teams::class ],
		self::CAROUSELS => [ Admin\Carousels::class ],
		self::TESTIMONIALS => [ Admin\Testimonials::class ],
		self::FAQS => [ Admin\Faqs::class ],
	];

	/**
	 * Routes shared by several areas: reachable while any of them is on.
	 * Store checkouts complete through the payments webhook.
	 *
	 * @var array<string, list<string>>
	 */
	private const ROUTES = [
		'payments_webhook' => [ self::PAYMENTS, self::STORE ],
		'donations_webhook' => [ self::PAYMENTS, self::STORE ],
	];

	/**
	 * Shortcode name => area that renders it.
	 *
	 * @var array<string, string>
	 */
	private const SHORTCODES = [
		'latest-posts' => self::BLOG,
		'calendar' => self::CALENDAR,
		'featured-event' => self::CALENDAR,
		'event-registration' => self::CALENDAR,
		'contact' => self::CONTACT,
		'payment' => self::PAYMENTS,
		'donation' => self::PAYMENTS,
		'products' => self::STORE,
		'product' => self::STORE,
		'cart' => self::STORE,
		'team' => self::TEAMS,
		'carousel' => self::CAROUSELS,
		'testimonial' => self::TESTIMONIALS,
		'faq' => self::FAQS,
	];

	private const OFF = [ false, 0, '0', 'false', 'off', 'no' ];

	private ?SettingManager $_settings;

	/**
	 * @param SettingManager|null $settings No settings means every area is on
	 */
	public function __construct( ?SettingManager $settings = null )
	{
		$this->_settings = $settings;
	}

	/**
	 * Features for the running application, read from the settings in the
	 * Registry. With no settings (unit tests, CLI) every area is on.
	 *
	 * @return self
	 */
	public static function current(): self
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		return new self( $settings instanceof SettingManager ? $settings : null );
	}

	/**
	 * Every area name the CMS knows.
	 *
	 * @return list<string>
	 */
	public static function names(): array
	{
		return array_keys( self::CONTROLLERS );
	}

	/**
	 * @param string $feature
	 * @return bool False only when the area is explicitly switched off
	 */
	public function isEnabled( string $feature ): bool
	{
		if( !$this->_settings )
		{
			return true;
		}

		$value = $this->_settings->get( 'features', $feature );

		if( is_string( $value ) )
		{
			$value = strtolower( trim( $value ) );
		}

		return !in_array( $value, self::OFF, true );
	}

	/**
	 * Whether a matched route may run. Routes outside the CMS's optional
	 * areas (site controllers, pages, admin core) are always allowed.
	 *
	 * @param RouteMap $route
	 * @return bool
	 */
	public function allowsRoute( RouteMap $route ): bool
	{
		$name = $route->Name ?? '';

		if( $name !== '' && isset( self::ROUTES[ $name ] ) )
		{
			return $this->anyEnabled( self::ROUTES[ $name ] );
		}

		$feature = $this->featureForController( (string)( $route->Payload['Controller'] ?? '' ) );

		return $feature === null || $this->isEnabled( $feature );
	}

	/**
	 * @param string $shortcode
	 * @return bool
	 */
	public function allowsShortcode( string $shortcode ): bool
	{
		$feature = self::SHORTCODES[ $shortcode ] ?? null;

		return $feature === null || $this->isEnabled( $feature );
	}

	/**
	 * Keys under `features` that are not CMS areas, most likely typos.
	 *
	 * @return list<string>
	 */
	public function unknownKeys(): array
	{
		$section = $this->_settings?->getSection( 'features' );

		if( !is_array( $section ) )
		{
			return [];
		}

		return array_values( array_diff( array_map( 'strval', array_keys( $section ) ), self::names() ) );
	}

	/**
	 * @param string $controller "Class@method" or a bare class name
	 * @return string|null
	 */
	private function featureForController( string $controller ): ?string
	{
		$class = ltrim( explode( '@', $controller, 2 )[0], '\\' );

		if( $class === '' )
		{
			return null;
		}

		foreach( self::CONTROLLERS as $feature => $classes )
		{
			if( in_array( $class, $classes, true ) )
			{
				return $feature;
			}
		}

		return null;
	}

	/**
	 * @param list<string> $features
	 * @return bool
	 */
	private function anyEnabled( array $features ): bool
	{
		foreach( $features as $feature )
		{
			if( $this->isEnabled( $feature ) )
			{
				return true;
			}
		}

		return false;
	}
}
