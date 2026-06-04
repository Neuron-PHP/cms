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

	public function testCopyNewViewsCopiesOnlyMissingFilesAndPreservesExisting(): void
	{
		$base   = sys_get_temp_dir() . '/neuron_cms_views_' . uniqid();
		$source = $base . '/source';
		$dest   = $base . '/dest';

		// Source (package) views: one brand-new file, one that also exists in dest.
		$this->writeFile( $source . '/admin/jobs/index.php', 'PACKAGE_JOBS' );
		$this->writeFile( $source . '/layouts/admin.php', 'PACKAGE_LAYOUT' );

		// Destination (installed) views: existing customized layout.
		$this->writeFile( $dest . '/layouts/admin.php', 'CUSTOM_LAYOUT' );

		try {
			$reflection = new \ReflectionClass( $this->command );
			$method = $reflection->getMethod( 'copyNewViews' );

			$copied = $method->invoke( $this->command, $source, $dest );

			$this->assertEquals( 1, $copied );

			// New view was copied.
			$this->assertFileExists( $dest . '/admin/jobs/index.php' );
			$this->assertEquals( 'PACKAGE_JOBS', file_get_contents( $dest . '/admin/jobs/index.php' ) );

			// Existing view was left untouched.
			$this->assertEquals( 'CUSTOM_LAYOUT', file_get_contents( $dest . '/layouts/admin.php' ) );
		} finally {
			$this->removeDirectory( $base );
		}
	}

	private function writeFile( string $path, string $contents ): void
	{
		$dir = dirname( $path );

		if( !is_dir( $dir ) )
		{
			mkdir( $dir, 0777, true );
		}

		file_put_contents( $path, $contents );
	}

	private function removeDirectory( string $path ): void
	{
		if( !is_dir( $path ) )
		{
			return;
		}

		$items = scandir( $path );

		foreach( $items as $item )
		{
			if( $item === '.' || $item === '..' )
			{
				continue;
			}

			$itemPath = $path . '/' . $item;

			if( is_dir( $itemPath ) )
			{
				$this->removeDirectory( $itemPath );
			}
			else
			{
				unlink( $itemPath );
			}
		}

		rmdir( $path );
	}
}
