<?php

namespace Neuron\Cms\Enums;

/**
 * Link target types for a menu item.
 *
 * @package Neuron\Cms\Enums
 */
enum MenuItemType: string
{
	case PAGE = 'page';
	case ROUTE = 'route';
	case URL = 'url';

	/**
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	public static function fromValue( ?string $value, self $fallback = self::URL ): self
	{
		return self::tryFrom( (string) $value ) ?? $fallback;
	}

	public function label(): string
	{
		return match( $this )
		{
			self::PAGE => 'CMS page',
			self::ROUTE => 'Site section',
			self::URL => 'Custom URL',
		};
	}
}
