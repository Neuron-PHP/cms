<?php

namespace Neuron\Cms\Enums;

/**
 * User status types
 *
 * @package Neuron\Cms\Enums
 */
enum UserStatus: string
{
	case ACTIVE = 'active';
	case INACTIVE = 'inactive';
	case SUSPENDED = 'suspended';

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
			self::ACTIVE => 'Active',
			self::INACTIVE => 'Inactive',
			self::SUSPENDED => 'Suspended',
		};
	}
}
