<?php

namespace Neuron\Cms\Enums;

/**
 * User role types
 *
 * @package Neuron\Cms\Enums
 */
enum UserRole: string
{
	case ADMIN = 'admin';
	case EDITOR = 'editor';
	case AUTHOR = 'author';
	case SUBSCRIBER = 'subscriber';

	/**
	 * Get all role values as an array
	 *
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	/**
	 * Get role label for display
	 *
	 * @return string
	 */
	public function label(): string
	{
		return match( $this )
		{
			self::ADMIN => 'Administrator',
			self::EDITOR => 'Editor',
			self::AUTHOR => 'Author',
			self::SUBSCRIBER => 'Subscriber',
		};
	}
}
