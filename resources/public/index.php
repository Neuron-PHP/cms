<?php
/**
 * Neuron CMS Public Index File
 * This is the main entry point for all web requests to the Neuron CMS application.
 */

use function Neuron\Cms\boot;
use function Neuron\Mvc\dispatch;

require '../vendor/autoload.php';

error_reporting( E_ALL );

$App = boot( '../config' );

dispatch( $App );
