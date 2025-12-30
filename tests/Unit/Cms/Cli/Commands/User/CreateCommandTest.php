<?php

namespace Tests\Cms\Cli\Commands\User;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Cli\Commands\User\CreateCommand;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\TestInputReader;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use org\bovigo\vfs\vfsStream;

class CreateCommandTest extends TestCase
{
	private $root;
	private TestInputReader $inputReader;
	private Output $output;
	private Input $input;

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

		// Set up test dependencies
		$this->inputReader = new TestInputReader();
		$this->output = new Output(false); // No colors in tests
		$this->input = new Input([]);
	}

	protected function tearDown(): void
	{
		// Clean up Registry
		Registry::getInstance()->reset();
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
		Registry::getInstance()->reset();
	}

	public function testExecuteCreatesUserSuccessfully(): void
	{
		// Set up test input responses
		$this->inputReader->addResponses([
			'testuser',           // username
			'test@example.com',   // email
			'SecurePass123!',     // password
			'1'                   // role (Admin)
		]);

		// Mock repository
		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->with('testuser')
			->willReturn(null); // User doesn't exist

		$repository->expects($this->once())
			->method('findByEmail')
			->with('test@example.com')
			->willReturn(null); // Email doesn't exist

		$repository->expects($this->once())
			->method('create')
			->willReturnCallback(function($user) {
				$user->setId(1); // Simulate database auto-increment
				return $user;
			});

		// Set up Registry with settings
		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		// Create command with mocked repository
		$command = $this->getMockBuilder(CreateCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();

		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($this->input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		// Execute command
		$exitCode = $command->execute();

		// Verify success
		$this->assertEquals(0, $exitCode);

		// Verify all prompts were shown
		$prompts = $this->inputReader->getPromptHistory();
		$this->assertCount(4, $prompts);
		$this->assertStringContainsString('username', $prompts[0]);
		$this->assertStringContainsString('email', $prompts[1]);
		$this->assertStringContainsString('password', $prompts[2]);
		$this->assertStringContainsString('role', $prompts[3]);
	}

	public function testExecuteFailsWhenUsernameIsEmpty(): void
	{
		$this->inputReader->addResponse(''); // Empty username

		$command = new CreateCommand();
		$command->setInput($this->input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		// Mock repository to avoid database
		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$repository = $this->createMock(DatabaseUserRepository::class);

		$mockCommand = $this->getMockBuilder(CreateCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$mockCommand->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$mockCommand->setInput($this->input);
		$mockCommand->setOutput($this->output);
		$mockCommand->setInputReader($this->inputReader);

		$exitCode = $mockCommand->execute();

		$this->assertEquals(1, $exitCode);
	}

	public function testExecuteFailsWhenUsernameAlreadyExists(): void
	{
		$this->inputReader->addResponse('existinguser');

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->with('existinguser')
			->willReturn($this->createMock(\Neuron\Cms\Models\User::class)); // User exists

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(CreateCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($this->input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		$exitCode = $command->execute();

		$this->assertEquals(1, $exitCode);
	}

	public function testExecuteFailsWhenEmailIsInvalid(): void
	{
		$this->inputReader->addResponses([
			'testuser',
			'invalid-email' // Invalid email
		]);

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->willReturn(null);

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(CreateCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($this->input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		$exitCode = $command->execute();

		$this->assertEquals(1, $exitCode);
	}

	public function testExecuteFailsWhenEmailAlreadyExists(): void
	{
		$this->inputReader->addResponses([
			'testuser',
			'existing@example.com'
		]);

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->willReturn(null);
		$repository->expects($this->once())
			->method('findByEmail')
			->with('existing@example.com')
			->willReturn($this->createMock(\Neuron\Cms\Models\User::class)); // Email exists

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(CreateCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($this->input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		$exitCode = $command->execute();

		$this->assertEquals(1, $exitCode);
	}

	public function testExecuteFailsWhenPasswordIsTooShort(): void
	{
		$this->inputReader->addResponses([
			'testuser',
			'test@example.com',
			'short' // Password too short (< 8 chars)
		]);

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->willReturn(null);
		$repository->expects($this->once())
			->method('findByEmail')
			->willReturn(null);

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(CreateCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($this->input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		$exitCode = $command->execute();

		$this->assertEquals(1, $exitCode);
	}
}
