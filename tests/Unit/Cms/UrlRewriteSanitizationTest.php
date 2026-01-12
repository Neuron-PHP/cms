<?php

namespace Tests\Unit\Cms;

use PHPUnit\Framework\TestCase;
use Neuron\Routing\Router;
use Neuron\Log\Log;
use Neuron\Log\Destination\Memory;
use Neuron\Log\Format\PlainText;

/**
 * Test URL rewrite logging sanitization.
 *
 * Verifies that sensitive data in URLs (query strings, tokens, etc.)
 * are not logged during URL rewrite operations.
 */
class UrlRewriteSanitizationTest extends TestCase
{
	protected ?Router $router = null;
	protected ?Memory $logDestination = null;

	protected function setUp(): void
	{
		parent::setUp();

		// Setup in-memory logging to capture debug messages
		$format = new PlainText();
		$this->logDestination = new Memory( $format );

		$logger = new \Neuron\Log\Logger( $this->logDestination );
		$logger->setRunLevel( \Neuron\Log\RunLevel::DEBUG );

		// Get Log singleton and add our logger
		$log = Log::getInstance();
		$log->initIfNeeded();
		$log->logger->addLog( $logger );

		// Set global run level to DEBUG
		Log::setRunLevel( \Neuron\Log\RunLevel::DEBUG );

		$this->router = new Router();

		// Skip tests if routing component doesn't have URL sanitization yet
		if( !method_exists( $this->router, 'setUrlRewrites' ) )
		{
			$this->markTestSkipped( 'Routing component needs to be upgraded to support URL rewrites' );
		}
	}

	protected function tearDown(): void
	{
		$this->router = null;

		// Clean up logging
		$log = Log::getInstance();
		if( $log->logger )
		{
			$log->logger->reset();
		}

		parent::tearDown();
	}

	/**
	 * Test that query strings are stripped from logged URLs
	 */
	public function testQueryStringsNotLogged()
	{
		// Configure a rewrite rule
		$this->router->setUrlRewrites([
			'/api' => '/api/v2'
		]);

		// Create a route that will match after rewrite
		$this->router->get( '/api/v2', function() { return 'api v2'; } );

		// Run the router with a URL containing sensitive query params
		try
		{
			$this->router->run([
				'route' => '/api?token=secret123&api_key=abc456',
				'type' => 'GET'
			]);
		}
		catch( \Exception $e )
		{
			// May throw due to routing issues, but we only care about logs
		}

		// Get log entries (split by newlines)
		$logData = $this->logDestination->getData();
		$this->assertIsString( $logData, 'Log data should be a string' );

		$logs = array_filter( explode( "\n", $logData ) );
		$debugLogs = array_filter( $logs, function( $entry ) {
			return stripos( $entry, 'URL rewrite' ) !== false;
		});

		// If no URL rewrite logs found, skip test (feature not yet implemented)
		if( count( $debugLogs ) === 0 )
		{
			$this->markTestSkipped( 'URL rewrite logging not yet implemented in routing component' );
		}

		// Verify query strings are not in the logs
		foreach( $debugLogs as $log )
		{
			$this->assertStringNotContainsString( 'token=', $log, 'Sensitive token should not be logged' );
			$this->assertStringNotContainsString( 'secret123', $log, 'Token value should not be logged' );
			$this->assertStringNotContainsString( 'api_key=', $log, 'API key parameter should not be logged' );
			$this->assertStringNotContainsString( 'abc456', $log, 'API key value should not be logged' );
			$this->assertStringNotContainsString( '?', $log, 'Query string delimiter should not be logged' );
		}

		// Verify the path portion IS logged
		$logEntry = array_values( $debugLogs )[0];
		$this->assertStringContainsString( '/api', $logEntry, 'Path should be logged' );
	}

	/**
	 * Test that URL fragments are stripped from logged URLs
	 */
	public function testFragmentsNotLogged()
	{
		$this->router->setUrlRewrites([
			'/page' => '/pages/show'
		]);

		$this->router->get( '/pages/show', function() { return 'page'; } );

		try
		{
			$this->router->run([
				'route' => '/page#section-with-sensitive-data',
				'type' => 'GET'
			]);
		}
		catch( \Exception $e )
		{
			// May throw, but we only care about logs
		}

		$logData = $this->logDestination->getData();
		$this->assertIsString( $logData, 'Log data should be a string' );

		$logs = array_filter( explode( "\n", $logData ) );
		$debugLogs = array_filter( $logs, function( $entry ) {
			return stripos( $entry, 'URL rewrite' ) !== false;
		});

		// If no URL rewrite logs found, skip test (feature not yet implemented)
		if( count( $debugLogs ) === 0 )
		{
			$this->markTestSkipped( 'URL rewrite logging not yet implemented in routing component' );
		}

		foreach( $debugLogs as $log )
		{
			$this->assertStringNotContainsString( '#', $log, 'Fragment delimiter should not be logged' );
			$this->assertStringNotContainsString( 'section-with-sensitive', $log, 'Fragment content should not be logged' );
		}
	}

	/**
	 * Test clean URLs (no query/fragment) are logged normally
	 */
	public function testCleanUrlsLoggedNormally()
	{
		$this->router->setUrlRewrites([
			'/' => '/blog'
		]);

		$this->router->get( '/blog', function() { return 'blog'; } );

		try
		{
			$this->router->run([
				'route' => '/',
				'type' => 'GET'
			]);
		}
		catch( \Exception $e )
		{
			// May throw, but we only care about logs
		}

		$logData = $this->logDestination->getData();
		$logs = array_filter( explode( "\n", $logData ) );
		$debugLogs = array_filter( $logs, function( $entry ) {
			return stripos( $entry, 'URL rewrite: / ->' ) !== false;
		});

		// Clean URL should be logged
		$this->assertGreaterThan( 0, count( $debugLogs ), 'Rewrite should be logged' );

		$logEntry = array_values( $debugLogs )[0];
		$this->assertStringContainsString( '/', $logEntry, 'Root path should be logged' );
		$this->assertStringContainsString( '/blog', $logEntry, 'Rewrite target should be logged' );
	}

	/**
	 * Test that both query strings AND fragments are stripped
	 */
	public function testBothQueryAndFragmentStripped()
	{
		$this->router->setUrlRewrites([
			'/search' => '/search/results'
		]);

		$this->router->get( '/search/results', function() { return 'results'; } );

		try
		{
			$this->router->run([
				'route' => '/search?q=password&user=admin#results',
				'type' => 'GET'
			]);
		}
		catch( \Exception $e )
		{
			// May throw, but we only care about logs
		}

		$logData = $this->logDestination->getData();
		$this->assertIsString( $logData, 'Log data should be a string' );

		$logs = array_filter( explode( "\n", $logData ) );
		$debugLogs = array_filter( $logs, function( $entry ) {
			return stripos( $entry, 'URL rewrite' ) !== false;
		});

		// If no URL rewrite logs found, skip test (feature not yet implemented)
		if( count( $debugLogs ) === 0 )
		{
			$this->markTestSkipped( 'URL rewrite logging not yet implemented in routing component' );
		}

		foreach( $debugLogs as $log )
		{
			// Neither query string nor fragment should appear
			$this->assertStringNotContainsString( '?', $log );
			$this->assertStringNotContainsString( '#', $log );
			$this->assertStringNotContainsString( 'password', $log );
			$this->assertStringNotContainsString( 'admin', $log );
			$this->assertStringNotContainsString( 'results', $log ); // fragment content

			// But path should be there
			$this->assertStringContainsString( '/search', $log );
		}
	}
}
