<?php

namespace Tests\Cms\Cli\Commands\User;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Cli\Commands\User\ResetPasswordCommand;
use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\TestInputReader;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use Neuron\Cms\Models\User;
use org\bovigo\vfs\vfsStream;

class ResetPasswordCommandTest extends TestCase
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
		$command = new ResetPasswordCommand();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass($command);
		$configureMethod = $reflection->getMethod('configure');
		$configureMethod->setAccessible(true);
		$configureMethod->invoke($command);

		// Verify command metadata is set
		$this->assertEquals('cms:user:reset-password', $command->getName());
		$this->assertNotEmpty($command->getDescription());
	}

	public function testGetUserRepositoryWithMissingConfig(): void
	{
		// Clear Registry to simulate missing Settings
		\Neuron\Patterns\Registry::getInstance()->reset();

		// Create command instance
		$command = new ResetPasswordCommand();

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

	public function testExecuteResetsPasswordSuccessfully(): void
	{
		// Set up test input responses
		$this->inputReader->addResponses([
			'testuser',           // username/email
			'yes',                // confirm reset
			'NewSecurePass123!',  // new password
			'NewSecurePass123!'   // confirm password
		]);

		// Create mock user
		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setRole('admin');
		$user->setPasswordHash('old_hash');

		// Mock repository
		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->with('testuser')
			->willReturn($user);

		$repository->expects($this->once())
			->method('update')
			->willReturnCallback(function($updatedUser) {
				$this->assertNotEquals('old_hash', $updatedUser->getPasswordHash());
				return true;
			});

		// Set up Registry with settings
		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		// Create command with mocked repository
		$command = $this->getMockBuilder(ResetPasswordCommand::class)
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
		$this->assertStringContainsString('username or email', $prompts[0]);
		$this->assertStringContainsString('Reset password', $prompts[1]);
		$this->assertStringContainsString('new password', $prompts[2]);
		$this->assertStringContainsString('Confirm', $prompts[3]);
	}

	public function testExecuteResetsPasswordWithEmailSuccessfully(): void
	{
		// Set up test input responses
		$this->inputReader->addResponses([
			'test@example.com',   // email
			'yes',                // confirm reset
			'NewSecurePass123!',  // new password
			'NewSecurePass123!'   // confirm password
		]);

		// Create mock user
		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setRole('admin');
		$user->setPasswordHash('old_hash');

		// Mock repository
		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByEmail')
			->with('test@example.com')
			->willReturn($user);

		$repository->expects($this->once())
			->method('update')
			->willReturn(true);

		// Set up Registry with settings
		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		// Create command with mocked repository
		$command = $this->getMockBuilder(ResetPasswordCommand::class)
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
	}

	public function testExecuteFailsWhenUserNotFound(): void
	{
		$this->inputReader->addResponse('nonexistent');

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->with('nonexistent')
			->willReturn(null);

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(ResetPasswordCommand::class)
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

	public function testExecuteCancelsWhenUserDeclines(): void
	{
		$this->inputReader->addResponses([
			'testuser',
			'no' // Decline reset
		]);

		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setRole('admin');

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->willReturn($user);

		// Ensure update is never called
		$repository->expects($this->never())
			->method('update');

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(ResetPasswordCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($this->input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		$exitCode = $command->execute();

		$this->assertEquals(0, $exitCode);
	}

	public function testExecuteFailsWhenPasswordTooShort(): void
	{
		$this->inputReader->addResponses([
			'testuser',
			'yes',
			'short' // Password too short
		]);

		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setRole('admin');

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->willReturn($user);

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(ResetPasswordCommand::class)
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

	public function testExecuteFailsWhenPasswordsDoNotMatch(): void
	{
		$this->inputReader->addResponses([
			'testuser',
			'yes',
			'NewSecurePass123!',
			'DifferentPassword123!' // Different password
		]);

		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setRole('admin');

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->willReturn($user);

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(ResetPasswordCommand::class)
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

	public function testExecuteWithUsernameOption(): void
	{
		// Create input with --username option
		$input = new Input(['--username=testuser']);

		$this->inputReader->addResponses([
			'yes',                // confirm reset
			'NewSecurePass123!',  // new password
			'NewSecurePass123!'   // confirm password
		]);

		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setRole('admin');

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByUsername')
			->with('testuser')
			->willReturn($user);

		$repository->expects($this->once())
			->method('update')
			->willReturn(true);

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(ResetPasswordCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		$exitCode = $command->execute();

		$this->assertEquals(0, $exitCode);

		// Verify username prompt was not shown (only 3 prompts)
		$prompts = $this->inputReader->getPromptHistory();
		$this->assertCount(3, $prompts);
	}

	public function testExecuteWithEmailOption(): void
	{
		// Create input with --email option
		$input = new Input(['--email=test@example.com']);

		$this->inputReader->addResponses([
			'yes',                // confirm reset
			'NewSecurePass123!',  // new password
			'NewSecurePass123!'   // confirm password
		]);

		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setRole('admin');

		$repository = $this->createMock(DatabaseUserRepository::class);
		$repository->expects($this->once())
			->method('findByEmail')
			->with('test@example.com')
			->willReturn($user);

		$repository->expects($this->once())
			->method('update')
			->willReturn(true);

		$settings = $this->createMock(SettingManager::class);
		Registry::getInstance()->set('Settings', $settings);

		$command = $this->getMockBuilder(ResetPasswordCommand::class)
			->onlyMethods(['getUserRepository'])
			->getMock();
		$command->expects($this->once())
			->method('getUserRepository')
			->willReturn($repository);

		$command->setInput($input);
		$command->setOutput($this->output);
		$command->setInputReader($this->inputReader);

		$exitCode = $command->execute();

		$this->assertEquals(0, $exitCode);

		// Verify email prompt was not shown (only 3 prompts)
		$prompts = $this->inputReader->getPromptHistory();
		$this->assertCount(3, $prompts);
	}
}
