<?php

namespace Neuron\Cms\Services\Content;

use Neuron\Cms\Services\Widget\WidgetRenderer;

/**
 * Parses and renders shortcodes in content.
 *
 * Supports WordPress-style shortcodes: [shortcode attr="value"]
 * Allows registration of custom shortcode handlers.
 *
 * @package Neuron\Cms\Services\Content
 */
class ShortcodeParser
{
	private ?WidgetRenderer $_widgetRenderer = null;
	private array $_customHandlers = [];

	public function __construct( ?WidgetRenderer $widgetRenderer = null )
	{
		$this->_widgetRenderer = $widgetRenderer;
	}

	/**
	 * Register a custom shortcode handler
	 *
	 * @param string $shortcode The shortcode name
	 * @param callable $handler Function that receives attributes and returns HTML
	 */
	public function register( string $shortcode, callable $handler ): void
	{
		$this->_customHandlers[$shortcode] = $handler;
	}

	/**
	 * Unregister a shortcode
	 *
	 * @param string $shortcode The shortcode name
	 */
	public function unregister( string $shortcode ): void
	{
		unset( $this->_customHandlers[$shortcode] );
	}

	/**
	 * Check if shortcode is registered
	 *
	 * @param string $shortcode The shortcode name
	 * @return bool
	 */
	public function hasShortcode( string $shortcode ): bool
	{
		return isset( $this->_customHandlers[$shortcode] )
			|| $this->hasBuiltInShortcode( $shortcode );
	}

	/**
	 * Parse shortcodes in content
	 *
	 * @param string $content Content with shortcodes like [calendar id="main" max="10"]
	 * @return string Content with shortcodes replaced by rendered widgets
	 */
	public function parse( string $content ): string
	{
		// Match [shortcode attr="value" attr2="value2"]
		// Supports hyphens in shortcode names and attribute names
		$pattern = '/\[([\w-]+)((?:\s+[\w-]+=["\'][^"\']*["\'])*)\]/';

		return preg_replace_callback( $pattern, function( $matches )
		{
			$shortcode = $matches[1];
			$attrString = $matches[2] ?? '';

			// Parse attributes
			$attrs = $this->parseAttributes( $attrString );

			// Render
			return $this->renderShortcode( $shortcode, $attrs );

		}, $content );
	}

	/**
	 * Parse attribute string into array
	 *
	 * @param string $attrString String like: id="main" max="10"
	 * @return array Associative array of attributes
	 */
	private function parseAttributes( string $attrString ): array
	{
		$attrs = [];

		// Match attr="value" or attr='value'
		// Supports hyphens in attribute names (e.g., data-id="value")
		preg_match_all( '/([\w-]+)=["\']([^"\']*)["\']/', $attrString, $matches, PREG_SET_ORDER );

		foreach( $matches as $match )
		{
			$key = $match[1];
			$value = $match[2];

			// Convert string booleans
			if( $value === 'true' )
			{
				$value = true;
			}
			elseif( $value === 'false' )
			{
				$value = false;
			}
			// Convert numeric strings
			elseif( is_numeric( $value ) )
			{
				$value = strpos( $value, '.' ) !== false ? (float)$value : (int)$value;
			}

			$attrs[$key] = $value;
		}

		return $attrs;
	}

	/**
	 * Render a specific shortcode
	 *
	 * @param string $shortcode Shortcode name
	 * @param array $attrs Parsed attributes
	 * @return string Rendered HTML or error comment
	 */
	private function renderShortcode( string $shortcode, array $attrs ): string
	{
		// Check custom handlers first
		if( isset( $this->_customHandlers[$shortcode] ) )
		{
			try
			{
				return call_user_func( $this->_customHandlers[$shortcode], $attrs );
			}
			catch( \Exception $e )
			{
				error_log( "Custom shortcode error [{$shortcode}]: " . $e->getMessage() );
				return "<!-- Error in custom shortcode [{$shortcode}] -->";
			}
		}

		// Fall back to built-in shortcodes
		if( $this->_widgetRenderer )
		{
			try
			{
				return match( $shortcode )
				{
					'latest-posts' => $this->_widgetRenderer->render( 'latest-posts', $attrs ),
					default => "<!-- Unknown shortcode: [{$shortcode}] -->"
				};
			}
			catch( \Exception $e )
			{
				error_log( "Shortcode error [{$shortcode}]: " . $e->getMessage() );
				return "<!-- Error rendering shortcode [{$shortcode}] -->";
			}
		}

		return "<!-- Unknown shortcode: [{$shortcode}] -->";
	}

	/**
	 * Check if shortcode is a built-in widget
	 *
	 * @param string $shortcode Shortcode name
	 * @return bool
	 */
	private function hasBuiltInShortcode( string $shortcode ): bool
	{
		return $shortcode === 'latest-posts';
	}
}
