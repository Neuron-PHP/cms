<?php

namespace Tests\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Robots;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class RobotsTest extends TestCase
{
	public function testDocumentAllowsCrawlersAndPointsAtSitemap(): void
	{
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			$defaults = [
				'site' => [
					'name' => 'Test Site',
					'title' => 'Test Title',
					'description' => 'Test Description',
					'url' => 'https://example.test',
				]
			];

			return $defaults[ $section ][ $key ] ?? null;
		} );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$app = $this->createMock( IMvcApplication::class );
		$app->method( 'getRouter' )->willReturn( $this->createMock( Router::class ) );

		$robots = new Robots(
			$app,
			$settings,
			$this->createMock( SessionManager::class )
		);

		$body = $robots->document();

		$this->assertStringContainsString( "User-agent: *\n", $body );
		$this->assertStringContainsString( "Allow: /\n", $body );
		$this->assertStringContainsString( 'Sitemap: https://example.test/', $body );
	}
}
