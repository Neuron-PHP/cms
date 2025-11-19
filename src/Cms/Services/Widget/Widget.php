<?php

namespace Neuron\Cms\Services\Widget;

/**
 * Abstract base class for widgets.
 *
 * Provides helper methods for common widget operations.
 *
 * @package Neuron\Cms\Services\Widget
 */
abstract class Widget implements IWidget
{
	/**
	 * Get widget description (override if needed)
	 */
	public function getDescription(): string
	{
		return '';
	}

	/**
	 * Get supported attributes (override if needed)
	 */
	public function getAttributes(): array
	{
		return [];
	}

	/**
	 * Helper to get attribute with default value
	 *
	 * @param array $attrs Attributes array
	 * @param string $key Attribute key
	 * @param mixed $default Default value
	 * @return mixed
	 */
	protected function attr( array $attrs, string $key, mixed $default = null ): mixed
	{
		return $attrs[$key] ?? $default;
	}

	/**
	 * Helper to render a view template
	 *
	 * @param string $template Template file path
	 * @param array $data Data to extract into template scope
	 * @return string Rendered output
	 */
	protected function view( string $template, array $data = [] ): string
	{
		if( !file_exists( $template ) )
		{
			return "<!-- Template not found: {$template} -->";
		}

		extract( $data );
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Sanitize HTML to prevent XSS
	 *
	 * @param string $html
	 * @return string
	 */
	protected function sanitizeHtml( string $html ): string
	{
		$allowedTags = '<div><p><span><a><img><h1><h2><h3><h4><h5><h6><ul><ol><li><strong><em><br><hr>';
		return strip_tags( $html, $allowedTags );
	}
}
