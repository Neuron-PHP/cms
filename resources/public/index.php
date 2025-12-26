<?php
/**
 * Neuron CMS Public Index File
 * This is the main entry point for all web requests to the Neuron CMS application.
 */

use function Neuron\Cms\boot;
use function Neuron\Mvc\dispatch;
use Neuron\Cms\Exceptions\CsrfValidationException;
use Neuron\Cms\Exceptions\UnauthenticatedException;
use Neuron\Cms\Exceptions\EmailVerificationRequiredException;

require '../vendor/autoload.php';

error_reporting( E_ALL );

$app = boot( '../config' );

try
{
	dispatch( $app );
}
catch( UnauthenticatedException $e )
{
	// Handle authentication failures by redirecting to login
	header( 'Location: ' . $e->getRedirectUrl() );
	exit;
}
catch( EmailVerificationRequiredException $e )
{
	// Handle email verification requirement by redirecting to verification page
	header( 'Location: ' . $e->getVerificationUrl() );
	exit;
}
catch( CsrfValidationException $e )
{
	// Handle CSRF validation failures with 403 response
	http_response_code( 403 );
	echo '<h1>403 Forbidden</h1>';
	echo '<p>' . htmlspecialchars( $e->getUserMessage() ) . '</p>';
	exit;
}
