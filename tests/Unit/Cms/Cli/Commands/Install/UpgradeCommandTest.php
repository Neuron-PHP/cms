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

	public function testCopyNewViewsForceOverwritesExistingFiles(): void
	{
		$base   = sys_get_temp_dir() . '/neuron_cms_views_force_' . uniqid();
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

			// Force overwrite: both files copied (new + existing).
			$copied = $method->invoke( $this->command, $source, $dest, true );

			$this->assertEquals( 2, $copied );
			$this->assertEquals( 'PACKAGE_JOBS', file_get_contents( $dest . '/admin/jobs/index.php' ) );
			$this->assertEquals( 'PACKAGE_LAYOUT', file_get_contents( $dest . '/layouts/admin.php' ) );
		} finally {
			$this->removeDirectory( $base );
		}
	}

	public function testCopyViewsInteractiveOnlyPromptsForNewerChangedViews(): void
	{
		$base   = sys_get_temp_dir() . '/neuron_cms_views_prompt_' . uniqid();
		$source = $base . '/source';
		$dest   = $base . '/dest';

		$old = time() - 1000;
		$mid = time() - 500;
		$new = time();

		// a_new: only in the package -> added automatically (no prompt).
		$this->writeFile( $source . '/admin/a_new.php', 'PKG_A' );

		// b_changed: differs and package is newer -> prompt (answered yes).
		$this->writeFile( $dest . '/admin/b_changed.php', 'LOCAL_B' );
		$this->writeFile( $source . '/admin/b_changed.php', 'PKG_B' );

		// c_changed: differs and package is newer -> prompt (answered no).
		$this->writeFile( $dest . '/admin/c_changed.php', 'LOCAL_C' );
		$this->writeFile( $source . '/admin/c_changed.php', 'PKG_C' );

		// d_same: identical contents though package is newer -> no prompt.
		$this->writeFile( $dest . '/admin/d_same.php', 'SAME' );
		$this->writeFile( $source . '/admin/d_same.php', 'SAME' );

		// e_older: differs but package is older -> no prompt.
		$this->writeFile( $dest . '/admin/e_older.php', 'LOCAL_E' );
		$this->writeFile( $source . '/admin/e_older.php', 'PKG_E' );

		// Local copies share a baseline mtime; package copies are newer except e.
		touch( $dest . '/admin/b_changed.php', $old );
		touch( $dest . '/admin/c_changed.php', $old );
		touch( $dest . '/admin/d_same.php', $old );
		touch( $dest . '/admin/e_older.php', $mid );

		touch( $source . '/admin/b_changed.php', $new );
		touch( $source . '/admin/c_changed.php', $new );
		touch( $source . '/admin/d_same.php', $new );
		touch( $source . '/admin/e_older.php', $old );

		// Prompts occur in scandir (alphabetical) order: b then c.
		$this->inputReader->addResponse( 'y' );
		$this->inputReader->addResponse( 'n' );

		try {
			$reflection = new \ReflectionClass( $this->command );
			$method = $reflection->getMethod( 'copyViewsInteractive' );

			$copied = $method->invoke( $this->command, $source, $dest );

			// Only the added view and the accepted overwrite were copied.
			$this->assertEquals( 2, $copied );

			// New view added.
			$this->assertEquals( 'PKG_A', file_get_contents( $dest . '/admin/a_new.php' ) );

			// Accepted overwrite applied; declined one preserved.
			$this->assertEquals( 'PKG_B', file_get_contents( $dest . '/admin/b_changed.php' ) );
			$this->assertEquals( 'LOCAL_C', file_get_contents( $dest . '/admin/c_changed.php' ) );

			// Identical and older-package views untouched and never prompted.
			$this->assertEquals( 'SAME', file_get_contents( $dest . '/admin/d_same.php' ) );
			$this->assertEquals( 'LOCAL_E', file_get_contents( $dest . '/admin/e_older.php' ) );

			// Exactly two prompts were shown (b and c).
			$this->assertCount( 2, $this->inputReader->getPromptHistory() );
		} finally {
			$this->removeDirectory( $base );
		}
	}

	public function testScaffoldScheduleConfigCreatesWhenMissingAndPreservesExisting(): void
	{
		$base    = sys_get_temp_dir() . '/neuron_cms_schedule_' . uniqid();
		$project = $base . '/project';
		$package = $base . '/package';

		$this->writeFile( $package . '/resources/config/schedule.yaml', "schedule:\n  demo:\n    class: X\n    cron: \"* * * * *\"\n" );
		mkdir( $project . '/config', 0777, true );

		try {
			$reflection = new \ReflectionClass( $this->command );

			$projectProp = $reflection->getProperty( '_projectPath' );
			$projectProp->setValue( $this->command, $project );

			$componentProp = $reflection->getProperty( '_componentPath' );
			$componentProp->setValue( $this->command, $package );

			$method = $reflection->getMethod( 'scaffoldScheduleConfig' );

			// First run: file missing -> created
			$this->assertTrue( $method->invoke( $this->command ) );
			$this->assertFileExists( $project . '/config/schedule.yaml' );

			// Customize, then run again -> not overwritten
			file_put_contents( $project . '/config/schedule.yaml', 'CUSTOM' );
			$this->assertTrue( $method->invoke( $this->command ) );
			$this->assertEquals( 'CUSTOM', file_get_contents( $project . '/config/schedule.yaml' ) );
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
