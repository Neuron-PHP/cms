<?php

namespace Tests\Unit\Cms\Cli\Commands\Views;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\Views\PublishCommand;
use PHPUnit\Framework\TestCase;

class PublishCommandTest extends TestCase
{
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

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:views:publish', ( new PublishCommand() )->getName() );
	}

	public function testPublishesPackageViewAndRecordsChecksum(): void
	{
		$base = sys_get_temp_dir() . '/neuron_cms_publish_' . uniqid();
		$project = $base . '/project';
		$package = $base . '/package';

		$this->writeFile( $package . '/resources/views/layouts/default.php', 'PACKAGE_LAYOUT' );
		mkdir( $project, 0777, true );

		$command = new PublishCommand();
		$command->setOutput( new Output( false ) );
		$command->configure();

		$input = new Input( [ 'layouts/default', '--force' ] );
		$input->parse( $command );
		$command->setInput( $input );

		$reflection = new \ReflectionClass( $command );
		$reflection->getProperty( '_projectPath' )->setValue( $command, $project );
		$reflection->getProperty( '_componentPath' )->setValue( $command, $package );

		try
		{
			$this->assertEquals( 0, $command->execute() );
			$this->assertFileExists( $project . '/resources/views/layouts/default.php' );
			$this->assertEquals(
				'PACKAGE_LAYOUT',
				file_get_contents( $project . '/resources/views/layouts/default.php' )
			);

			$manifest = json_decode(
				(string) file_get_contents( $project . '/.cms-manifest.json' ),
				true
			);
			$this->assertEquals(
				hash( 'sha256', 'PACKAGE_LAYOUT' ),
				$manifest['published_views']['layouts/default.php']
			);
		}
		finally
		{
			$this->removeDirectory( $base );
		}
	}

	public function testRejectsMissingPackageView(): void
	{
		$command = new PublishCommand();
		$command->setOutput( new Output( false ) );
		$command->configure();

		$input = new Input( [ 'does-not-exist.php' ] );
		$input->parse( $command );
		$command->setInput( $input );

		$base = sys_get_temp_dir() . '/neuron_cms_publish_missing_' . uniqid();
		mkdir( $base . '/package/resources/views', 0777, true );
		mkdir( $base . '/project', 0777, true );

		$reflection = new \ReflectionClass( $command );
		$reflection->getProperty( '_projectPath' )->setValue( $command, $base . '/project' );
		$reflection->getProperty( '_componentPath' )->setValue( $command, $base . '/package' );

		try
		{
			$this->assertEquals( 1, $command->execute() );
		}
		finally
		{
			$this->removeDirectory( $base );
		}
	}
}
