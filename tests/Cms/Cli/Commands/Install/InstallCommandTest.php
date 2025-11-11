<?php

namespace Tests\Cms\Cli\Commands\Install;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Cli\Commands\Install\InstallCommand;
use org\bovigo\vfs\vfsStream;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;

class InstallCommandTest extends TestCase
{
	private $root;

	protected function setUp(): void
	{
		// Create virtual filesystem
		$this->root = vfsStream::setup('test');
	}

	public function testConfigureSetupCommandMetadata(): void
	{
		$command = new InstallCommand();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass($command);
		$configureMethod = $reflection->getMethod('configure');
		$configureMethod->setAccessible(true);
		$configureMethod->invoke($command);

		$this->assertTrue(true); // Command configured without errors
	}

	public function testIsAlreadyInstalledChecksDirectory(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass($command);
		$method = $reflection->getMethod('isAlreadyInstalled');
		$method->setAccessible(true);

		$result = $method->invoke($command);

		// Result depends on whether resources/views/admin exists in the project
		$this->assertIsBool($result);
	}

	public function testConfigureSqliteCreatesValidConfig(): void
	{
		$this->markTestSkipped('Cannot test configureSqlite() as it requires interactive input via prompt()');

		// TODO: Refactor InstallCommand to accept an input interface for testing
		// For now, this test is skipped to prevent hanging in CI/IDE environments
	}

	public function testArrayToYamlConvertsArrayCorrectly(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass($command);
		$method = $reflection->getMethod('arrayToYaml');
		$method->setAccessible(true);

		$data = [
			'database' => [
				'adapter' => 'sqlite',
				'name' => 'test.db',
				'port' => 3306
			]
		];

		$result = $method->invoke($command, $data);

		$this->assertIsString($result);
		$this->assertStringContainsString('database:', $result);
		$this->assertStringContainsString('adapter: sqlite', $result);
		$this->assertStringContainsString('name: test.db', $result);
		$this->assertStringContainsString('port: 3306', $result);
	}

	public function testYamlValueFormatsStringsCorrectly(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass($command);
		$method = $reflection->getMethod('yamlValue');
		$method->setAccessible(true);

		// Test boolean
		$this->assertEquals('true', $method->invoke($command, true));
		$this->assertEquals('false', $method->invoke($command, false));

		// Test integer
		$this->assertEquals('123', $method->invoke($command, 123));

		// Test string with spaces (should be quoted)
		$this->assertEquals('"test value"', $method->invoke($command, 'test value'));

		// Test string with colon (should be quoted)
		$this->assertEquals('"test:value"', $method->invoke($command, 'test:value'));

		// Test simple string
		$this->assertEquals('simple', $method->invoke($command, 'simple'));
	}

	public function testCamelToSnakeConvertsCorrectly(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass($command);
		$method = $reflection->getMethod('camelToSnake');
		$method->setAccessible(true);

		$this->assertEquals('create_users_table', $method->invoke($command, 'CreateUsersTable'));
		$this->assertEquals('add_index', $method->invoke($command, 'AddIndex'));
		$this->assertEquals('simple', $method->invoke($command, 'simple'));
	}

	public function testGetMigrationTemplateGeneratesValidPhp(): void
	{
		$command = new InstallCommand();

		// Use reflection to call private method
		$reflection = new \ReflectionClass($command);
		$method = $reflection->getMethod('getMigrationTemplate');
		$method->setAccessible(true);

		$result = $method->invoke($command, 'CreateUsersTable');

		$this->assertIsString($result);
		$this->assertStringContainsString('<?php', $result);
		$this->assertStringContainsString('class CreateUsersTable extends AbstractMigration', $result);
		$this->assertStringContainsString('public function change()', $result);
		$this->assertStringContainsString('->addColumn( \'username\'', $result);
		$this->assertStringContainsString('->addColumn( \'email\'', $result);
		$this->assertStringContainsString('->addColumn( \'password_hash\'', $result);
		$this->assertStringContainsString('->addIndex( [ \'username\' ]', $result);
	}


	/**
	 * Helper to set stdin input for testing
	 */
	private function setInputStream(string $input): void
	{
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $input);
		rewind($stream);
		// Note: This doesn't actually work in tests, but shows intent
	}
}
