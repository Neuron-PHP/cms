<?php

// Register the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set error reporting
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

// Set timezone
date_default_timezone_set( 'UTC' );

// Initialize ViewDataProvider for tests
$provider = \Neuron\Mvc\Views\ViewDataProvider::getInstance();
$provider->share( 'siteName', 'Test Site' );
$provider->share( 'appVersion', '1.0.0-test' );
$provider->share( 'currentUser', null );
$provider->share( 'theme', 'sandstone' );
$provider->share( 'currentYear', fn() => date('Y') );
$provider->share( 'isAuthenticated', false );