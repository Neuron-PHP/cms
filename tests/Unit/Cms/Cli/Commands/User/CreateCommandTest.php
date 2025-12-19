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
		// Test that missing config is handled - skipped due to vfs limitations with chdir
		$this->markTestSkipped('Cannot test with vfsStream due to chdir() limitations');
	}

	public function testGetUserRepositoryWithInvalidDatabase(): void
	{
		// Test that invalid database config is handled - skipped due to vfs limitations with chdir
		$this->markTestSkipped('Cannot test with vfsStream due to chdir() limitations');
	}
}
