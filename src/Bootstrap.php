<?php
namespace Neuron\Cms;

use Neuron\Data\Object\Version;
use Neuron\Data\Setting\Source\Yaml;
use Neuron\Mvc\Application;

// Load authentication helper functions
require_once __DIR__ . '/Cms/Auth/helpers.php';

/**
 * CMS Bootstrap Module for the Neuron Framework
 * 
 * This module provides bootstrap functionality for initializing Neuron CMS
 * applications. It serves as the entry point for CMS-specific configuration
 * and setup, extending the base MVC application with content management
 * capabilities.
 * 
 * @package Neuron\Cms
 */

/**
 * Bootstrap and initialize a Neuron CMS application.
 * 
 * This function initializes a complete CMS application with all necessary
 * components including routing, content management, blog functionality,
 * and site configuration. It delegates to the MVC boot function but
 * provides CMS-specific context and naming.
 *
 * @param string $ConfigPath Path to the CMS configuration directory
 * @return Application Fully configured CMS application instance
 * @throws \Exception If configuration loading or application initialization fails
 */

function boot( string $ConfigPath ) : Application
{
	return \Neuron\Mvc\boot( $ConfigPath );
}
