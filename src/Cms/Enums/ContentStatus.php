<?php

namespace Neuron\Cms\Enums;

/**
 * Content status types (for posts, pages, events)
 *
 * @package Neuron\Cms\Enums
 */
enum ContentStatus: string
{
	case DRAFT = 'draft';
	case PUBLISHED = 'published';
	case SCHEDULED = 'scheduled';

	/**
	 * Get all status values as an array
	 *
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	/**
	 * Get status label for display
	 *
	 * @return string
	 */
	public function label(): string
	{
		return match( $this )
		{
			self::DRAFT => 'Draft',
			self::PUBLISHED => 'Published',
			self::SCHEDULED => 'Scheduled',
		};
	}

	/**
	 * Get status badge color for UI
	 *
	 * @return string
	 */
	public function badgeColor(): string
	{
		return match( $this )
		{
			self::DRAFT => 'secondary',
			self::PUBLISHED => 'success',
			self::SCHEDULED => 'warning',
		};
	}
}
