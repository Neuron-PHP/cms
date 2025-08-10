<?php
namespace Neuron\Cms;

use Neuron\Data\Object\Version;
use Neuron\Data\Setting\Source\Yaml;
use Neuron\Mvc\Application;

/**
 * Initialize the application.
 *
 * @param string $ConfigPath
 * @return Application
 * @throws \Exception
 */

function Boot( string $ConfigPath ) : Application
{
	return \Neuron\Mvc\Boot( $ConfigPath );
}
