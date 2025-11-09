<?php

namespace Tests\Cms\Maintenance;

use Neuron\Cms\Maintenance\MaintenanceConfig;
use Neuron\Data\Setting\Source\Yaml;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceConfig functionality
 */
class MaintenanceConfigTest extends TestCase
{
	private $Root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->Root = vfsStream::setup( 'test' );
	}

	/**
	 * Test config without settings source uses defaults
	 */
	public function testConfigWithoutSettingsSource(): void
	{
		$config = new MaintenanceConfig();

		$this->assertFalse( $config->isEnabled() );
		$this->assertIsString( $config->getDefaultMessage() );
		$this->assertIsArray( $config->getAllowedIps() );
		$this->assertContains( '127.0.0.1', $config->getAllowedIps() );
		$this->assertIsInt( $config->getRetryAfter() );
		$this->assertEquals( 3600, $config->getRetryAfter() );
		$this->assertNull( $config->getCustomView() );
		$this->assertFalse( $config->shouldShowCountdown() );
	}

	/**
	 * Test config loads from settings source
	 */
	public function testConfigFromSettingsSource(): void
	{
		// Create config file
		$configContent = <<<YAML
maintenance:
  enabled: true
  default_message: "Custom message"
  allowed_ips:
    - 192.168.1.1
    - 10.0.0.0/8
  retry_after: 7200
  custom_view: themes/custom/maintenance.php
  show_countdown: true
YAML;

		vfsStream::newFile( 'config.yaml' )
			->at( $this->Root )
			->setContent( $configContent );

		$settings = new Yaml( vfsStream::url( 'test/config.yaml' ) );
		$config = new MaintenanceConfig( $settings );

		$this->assertTrue( $config->isEnabled() );
		$this->assertEquals( 'Custom message', $config->getDefaultMessage() );
		$this->assertContains( '192.168.1.1', $config->getAllowedIps() );
		$this->assertContains( '10.0.0.0/8', $config->getAllowedIps() );
		$this->assertEquals( 7200, $config->getRetryAfter() );
		$this->assertEquals( 'themes/custom/maintenance.php', $config->getCustomView() );
		$this->assertTrue( $config->shouldShowCountdown() );
	}

	/**
	 * Test fromSettings static factory method
	 */
	public function testFromSettingsFactory(): void
	{
		$configContent = <<<YAML
maintenance:
  enabled: false
  default_message: "Site maintenance"
YAML;

		vfsStream::newFile( 'config.yaml' )
			->at( $this->Root )
			->setContent( $configContent );

		$settings = new Yaml( vfsStream::url( 'test/config.yaml' ) );
		$config = MaintenanceConfig::fromSettings( $settings );

		$this->assertInstanceOf( MaintenanceConfig::class, $config );
		$this->assertFalse( $config->isEnabled() );
		$this->assertEquals( 'Site maintenance', $config->getDefaultMessage() );
	}

	/**
	 * Test allowed IPs as comma-separated string
	 */
	public function testAllowedIpsAsString(): void
	{
		$configContent = <<<YAML
maintenance:
  allowed_ips: "192.168.1.1, 10.0.0.1, 172.16.0.0/16"
YAML;

		vfsStream::newFile( 'config.yaml' )
			->at( $this->Root )
			->setContent( $configContent );

		$settings = new Yaml( vfsStream::url( 'test/config.yaml' ) );
		$config = new MaintenanceConfig( $settings );

		$ips = $config->getAllowedIps();
		$this->assertIsArray( $ips );
		$this->assertContains( '192.168.1.1', $ips );
		$this->assertContains( '10.0.0.1', $ips );
		$this->assertContains( '172.16.0.0/16', $ips );
	}

	/**
	 * Test partial config uses defaults for missing values
	 */
	public function testPartialConfigUsesDefaults(): void
	{
		$configContent = <<<YAML
maintenance:
  default_message: "Only message specified"
YAML;

		vfsStream::newFile( 'config.yaml' )
			->at( $this->Root )
			->setContent( $configContent );

		$settings = new Yaml( vfsStream::url( 'test/config.yaml' ) );
		$config = new MaintenanceConfig( $settings );

		$this->assertEquals( 'Only message specified', $config->getDefaultMessage() );
		// Should use defaults for other values
		$this->assertFalse( $config->isEnabled() );
		$this->assertIsArray( $config->getAllowedIps() );
		$this->assertEquals( 3600, $config->getRetryAfter() );
	}

	/**
	 * Test empty config section uses all defaults
	 */
	public function testEmptyConfigUsesDefaults(): void
	{
		$configContent = <<<YAML
site:
  name: Test Site
YAML;

		vfsStream::newFile( 'config.yaml' )
			->at( $this->Root )
			->setContent( $configContent );

		$settings = new Yaml( vfsStream::url( 'test/config.yaml' ) );
		$config = new MaintenanceConfig( $settings );

		// Should all be defaults
		$this->assertFalse( $config->isEnabled() );
		$this->assertStringContainsString( 'maintenance', strtolower( $config->getDefaultMessage() ) );
		$this->assertContains( '127.0.0.1', $config->getAllowedIps() );
		$this->assertEquals( 3600, $config->getRetryAfter() );
		$this->assertNull( $config->getCustomView() );
	}
}
