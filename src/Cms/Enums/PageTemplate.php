<?php

namespace Neuron\Cms\Enums;

/**
 * Page template types
 *
 * @package Neuron\Cms\Enums
 */
enum PageTemplate: string
{
	case DEFAULT = 'default';
	case FULL_WIDTH = 'full-width';
	case SIDEBAR = 'sidebar';
	case LANDING = 'landing';

	/**
	 * Get all template values as an array
	 *
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	/**
	 * Get template label for display
	 *
	 * @return string
	 */
	public function label(): string
	{
		return match( $this )
		{
			self::DEFAULT => 'Default',
			self::FULL_WIDTH => 'Full Width',
			self::SIDEBAR => 'Sidebar',
			self::LANDING => 'Landing Page',
		};
	}

	/**
	 * Get template description
	 *
	 * @return string
	 */
	public function description(): string
	{
		return match( $this )
		{
			self::DEFAULT => 'Standard page layout with header and footer',
			self::FULL_WIDTH => 'Full width layout without sidebar',
			self::SIDEBAR => 'Layout with right sidebar',
			self::LANDING => 'Landing page with minimal navigation',
		};
	}
}
