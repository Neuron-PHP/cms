<?php

namespace Neuron\Cms\Enums;

/**
 * Public display modes for a named carousel.
 *
 * @package Neuron\Cms\Enums
 */
enum CarouselDisplay: string
{
	case SLIDER = 'slider';
	case GALLERY = 'gallery';
	case LOGOS = 'logos';

	/**
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	/**
	 * Resolve a raw value, falling back when unknown.
	 */
	public static function fromValue( ?string $value, self $fallback = self::SLIDER ): self
	{
		return self::tryFrom( (string) $value ) ?? $fallback;
	}

	public function label(): string
	{
		return match( $this )
		{
			self::SLIDER => 'Slider',
			self::GALLERY => 'Gallery',
			self::LOGOS => 'Logos',
		};
	}

	public function description(): string
	{
		return match( $this )
		{
			self::SLIDER => 'Rotating full-width slides with captions',
			self::GALLERY => 'Thumbnail grid with lightbox',
			self::LOGOS => 'Compact partner or sponsor logo strip',
		};
	}
}
