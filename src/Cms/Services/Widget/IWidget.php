<?php

namespace Neuron\Cms\Services\Widget;

/**
 * Widget interface for extensible shortcode/widget system.
 *
 * Implement this interface to create custom widgets that can be used
 * as shortcodes in page content.
 *
 * @package Neuron\Cms\Services\Widget
 */
interface IWidget
{
	/**
	 * Get the widget shortcode name
	 *
	 * @return string Shortcode name (e.g., 'property-listings')
	 */
	public function getName(): string;

	/**
	 * Render the widget
	 *
	 * @param array $attrs Shortcode attributes
	 * @return string Rendered HTML
	 */
	public function render( array $attrs ): string;

	/**
	 * Get widget description (for documentation)
	 *
	 * @return string Widget description
	 */
	public function getDescription(): string;

	/**
	 * Get supported attributes
	 *
	 * @return array Array of [attribute => description]
	 */
	public function getAttributes(): array;
}
