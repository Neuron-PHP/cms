<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Services\Content\ShortcodeParser;

/**
 * Registry for managing widgets.
 *
 * Allows registration of custom widgets and automatic integration
 * with the shortcode parser.
 *
 * @package Neuron\Cms\Services\Widget
 */
class WidgetRegistry
{
	/** @var array<string, IWidget> */
	private array $_widgets = [];
	private ShortcodeParser $_parser;

	public function __construct( ShortcodeParser $parser )
	{
		$this->_parser = $parser;
	}

	/**
	 * Register a widget
	 *
	 * @param IWidget $widget Widget to register
	 */
	public function register( IWidget $widget ): void
	{
		$name = $widget->getName();
		$this->_widgets[$name] = $widget;

		// Auto-register with shortcode parser
		$this->_parser->register( $name, function( $attrs ) use ( $widget )
		{
			return $widget->render( $attrs );
		} );
	}

	/**
	 * Unregister a widget
	 *
	 * @param string $name Widget name
	 */
	public function unregister( string $name ): void
	{
		if( isset( $this->_widgets[$name] ) )
		{
			unset( $this->_widgets[$name] );
			$this->_parser->unregister( $name );
		}
	}

	/**
	 * Get all registered widgets (for documentation)
	 *
	 * @return array<string, IWidget> Array of widgets
	 */
	public function getAll(): array
	{
		return $this->_widgets;
	}

	/**
	 * Get widget by name
	 *
	 * @param string $name Widget name
	 * @return IWidget|null
	 */
	public function get( string $name ): ?IWidget
	{
		return $this->_widgets[$name] ?? null;
	}

	/**
	 * Check if widget exists
	 *
	 * @param string $name Widget name
	 * @return bool
	 */
	public function has( string $name ): bool
	{
		return isset( $this->_widgets[$name] );
	}
}
