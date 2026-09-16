<?php

namespace Neuron\Cms\Enums;

/**
 * What the stock homepage renders.
 *
 * @package Neuron\Cms\Enums
 */
enum HomepageMode: string
{
	case BLOG = 'blog';
	case PAGE = 'page';
	case LANDING = 'landing';

	/**
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	public static function fromValue( ?string $value, self $fallback = self::BLOG ): self
	{
		return self::tryFrom( (string) $value ) ?? $fallback;
	}
}
