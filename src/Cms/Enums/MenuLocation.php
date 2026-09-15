<?php

namespace Neuron\Cms\Enums;

/**
 * Published locations for a CMS-managed menu.
 *
 * @package Neuron\Cms\Enums
 */
enum MenuLocation: string
{
	case HEADER = 'header';
	case FOOTER = 'footer';

	/**
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	public static function fromValue( ?string $value, self $fallback = self::HEADER ): self
	{
		return self::tryFrom( (string) $value ) ?? $fallback;
	}

	public function label(): string
	{
		return match( $this )
		{
			self::HEADER => 'Header',
			self::FOOTER => 'Footer',
		};
	}

	public function description(): string
	{
		return match( $this )
		{
			self::HEADER => 'Primary navbar',
			self::FOOTER => 'Footer links',
		};
	}
}
