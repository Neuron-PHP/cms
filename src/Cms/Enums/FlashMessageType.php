<?php

namespace Neuron\Cms\Enums;

/**
 * Flash message types used throughout the CMS
 *
 * @package Neuron\Cms\Enums
 */
enum FlashMessageType: string
{
	case SUCCESS = 'success';
	case ERROR = 'error';
	case WARNING = 'warning';
	case INFO = 'info';
}
