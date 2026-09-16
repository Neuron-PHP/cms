<?php

namespace Neuron\Cms\Enums;

/**
 * Public display modes for a named testimonial collection.
 *
 * @package Neuron\Cms\Enums
 */
enum TestimonialDisplay: string
{
	case CARDS = 'cards';
	case LIST = 'list';
	case FEATURED = 'featured';

	/**
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	public static function fromValue( ?string $value, self $fallback = self::CARDS ): self
	{
		return self::tryFrom( (string) $value ) ?? $fallback;
	}

	public function label(): string
	{
		return match( $this )
		{
			self::CARDS => 'Cards',
			self::LIST => 'List',
			self::FEATURED => 'Featured',
		};
	}

	public function description(): string
	{
		return match( $this )
		{
			self::CARDS => 'Quote cards in a responsive grid',
			self::LIST => 'Stacked blockquotes',
			self::FEATURED => 'Large rotating pull-quotes',
		};
	}
}
