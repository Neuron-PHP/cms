<?php

namespace Tests\Cms\Cli\Commands\User;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Cli\Commands\User\CreateCommand;
use org\bovigo\vfs\vfsStream;

class CreateCommandTest extends TestCase
{
	private $root;

	protected function setUp(): void
	{
		// Create virtual filesystem
		$this->root = vfsStream::setup('test');

		// Create config directory
		vfsStream::newDirectory('config')->at($this->root);

		// Create test config
		$configContent = <<<YAML
database:
  adapter: sqlite
  name: :memory:
YAML;

		vfsStream::newFile('config/neuron.yaml')
			->at($this->root)
			->setContent($configContent);
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new CreateCommand();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass($command);
		$configureMethod = $reflection->getMethod('configure');
		$configureMethod->setAccessible(true);
		$configureMethod->invoke($command);

		// Verify command metadata is set
		$this->assertEquals('cms:user:create', $command->getName());
		$this->assertNotEmpty($command->getDescription());
	}

	public function testGetUserRepositoryWithMissingConfig(): void
	{
		// Clear Registry to simulate missing Settings
		\Neuron\Patterns\Registry::getInstance()->reset();

		// Create command instance
		$command = new CreateCommand();

		// Mock the output to capture error messages
		$output = $this->createMock(\Neuron\Cli\Console\Output::class);
		$output->expects($this->once())
			->method('error')
			->with('Application not initialized: Settings not found in Registry');
		$output->expects($this->once())
			->method('writeln')
			->with('This is a configuration error - the application should load settings into the Registry');

		$command->setOutput($output);

		// Use reflection to test private getUserRepository method
		$reflection = new \ReflectionClass($command);
		$method = $reflection->getMethod('getUserRepository');
		$method->setAccessible(true);

		// Call the method and verify it returns null
		$result = $method->invoke($command);
		$this->assertNull($result);
	}

	public function testGetUserRepositoryWithInvalidDatabase(): void
	{
		// Set up Registry with invalid database configuration that will cause an exception
		$settings = $this->createMock(\Neuron\Data\Settings\SettingManager::class);

		// Make the SettingManager throw an exception when DatabaseUserRepository tries to use it
		$settings->method('get')
			->willThrowException(new \Exception('Invalid database configuration'));

		\Neuron\Patterns\Registry::getInstance()->set('Settings', $settings);

		// Create command instance
		$command = new CreateCommand();

		// Mock the output to capture error messages
		$output = $this->createMock(\Neuron\Cli\Console\Output::class);
		$output->expects($this->once())
			->method('error')
			->with($this->stringContains('Database connection failed:'));

		$command->setOutput($output);

		// Use reflection to test private getUserRepository method
		$reflection = new \ReflectionClass($command);
		$method = $reflection->getMethod('getUserRepository');
		$method->setAccessible(true);

		// Call the method and verify it returns null
		$result = $method->invoke($command);
		$this->assertNull($result);

		// Clean up Registry
		\Neuron\Patterns\Registry::getInstance()->reset();
	}
}
