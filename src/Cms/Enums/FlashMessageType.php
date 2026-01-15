<?php

namespace Neuron\Cms\Enums;

/**
 * Flash message types used throughout the CMS
 *
 * Provides consistent keys for both session storage and view variables.
 *
 * @package Neuron\Cms\Enums
 */
enum FlashMessageType: string
{
	case SUCCESS = 'success';
	case ERROR = 'error';
	case WARNING = 'warning';
	case INFO = 'info';

	/**
	 * Get the view variable name for this flash message type
	 *
	 * Views expect capitalized variable names (e.g., $Success, $Error)
	 * while session storage uses lowercase keys (e.g., 'success', 'error')
	 *
	 * @return string The capitalized view variable name
	 */
	public function viewKey(): string
	{
		return ucfirst( $this->value );
	}
}
