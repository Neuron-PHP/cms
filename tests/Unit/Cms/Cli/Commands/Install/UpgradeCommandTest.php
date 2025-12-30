<?php

namespace Tests\Unit\Cms\Cli\Commands\Install;

use Neuron\Cms\Cli\Commands\Install\UpgradeCommand;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\TestInputReader;
use PHPUnit\Framework\TestCase;

class UpgradeCommandTest extends TestCase
{
	private UpgradeCommand $command;
	private Output $output;
	private TestInputReader $inputReader;

	protected function setUp(): void
	{
		$this->command = new UpgradeCommand();
		$this->output = new Output(false);
		$this->inputReader = new TestInputReader();

		$this->command->setOutput($this->output);
		$this->command->setInputReader($this->inputReader);
	}

	public function testGetName(): void
	{
		$this->assertEquals('cms:upgrade', $this->command->getName());
	}

	public function testGetDescription(): void
	{
		$description = $this->command->getDescription();
		$this->assertIsString($description);
		$this->assertNotEmpty($description);
		$this->assertStringContainsString('upgrade', strtolower($description));
	}

	public function testConfigure(): void
	{
		$this->command->configure();

		$options = $this->command->getOptions();

		$this->assertArrayHasKey('check', $options);
		$this->assertEquals('c', $options['check']['shortcut']);

		$this->assertArrayHasKey('migrations-only', $options);
		$this->assertEquals('m', $options['migrations-only']['shortcut']);

		$this->assertArrayHasKey('skip-views', $options);
		$this->assertArrayHasKey('skip-migrations', $options);
		$this->assertArrayHasKey('run-migrations', $options);
		$this->assertEquals('r', $options['run-migrations']['shortcut']);
	}

	public function testExecuteWithMissingManifest(): void
	{
		// Create temp directory without manifest
		$tempDir = sys_get_temp_dir() . '/neuron_cms_test_' . uniqid();
		mkdir($tempDir);

		try {
			// Change to temp directory
			$originalCwd = getcwd();
			chdir($tempDir);

			$command = new UpgradeCommand();
			$command->setInput(new Input([]));
			$command->setOutput($this->output);

			$exitCode = $command->execute();

			$this->assertEquals(1, $exitCode);

			chdir($originalCwd);
		} finally {
			if (is_dir($tempDir)) {
				rmdir($tempDir);
			}
		}
	}

	public function testExecuteWhenCmsNotInstalled(): void
	{
		// This would require creating a full test environment with manifests
		// For now, test that the command structure is correct
		$this->markTestSkipped('Requires full CMS test environment setup');
	}

	public function testExecuteWithCheckFlag(): void
	{
		// This would require creating a full test environment with manifests
		$this->markTestSkipped('Requires full CMS test environment setup');
	}

	public function testExecuteUserCancelsUpgrade(): void
	{
		// This would require creating a full test environment with manifests
		$this->markTestSkipped('Requires full CMS test environment setup');
	}
}
