<?php

// Register the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Manually require DTO component files for monorepo development
// Order matters: dependencies must be loaded first
$dtoBasePath = __DIR__ . '/../../dto/src/Dto/';
require_once $dtoBasePath . 'Validation.php';
require_once $dtoBasePath . 'Compound/ICompound.php';
require_once $dtoBasePath . 'Compound/Base.php';
require_once $dtoBasePath . 'Property.php';
require_once $dtoBasePath . 'Dto.php';
require_once $dtoBasePath . 'Collection.php';
require_once $dtoBasePath . 'Factory.php';
require_once $dtoBasePath . 'Mapper/IMapper.php';
require_once $dtoBasePath . 'Mapper/Dynamic.php';
require_once $dtoBasePath . 'Mapper/Factory.php';

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