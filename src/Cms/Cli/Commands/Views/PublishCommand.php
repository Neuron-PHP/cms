<?php

namespace Neuron\Cms\Cli\Commands\Views;

use Neuron\Cli\Commands\Command;

/**
 * Copy one package view into the site so it can be customized.
 */
class PublishCommand extends Command
{
	private string $_projectPath;
	private string $_componentPath;

	public function __construct()
	{
		$this->_projectPath = getcwd();
		$this->_componentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:views:publish';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Copy a CMS package view into the site for customization';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addArgument( 'view', true, 'View path relative to resources/views (e.g. layouts/default.php)' );
		$this->addOption( 'force', 'f', false, 'Overwrite the site copy if it already exists' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( array $parameters = [] ): int
	{
		$view = ltrim( str_replace( '\\', '/', (string) $this->input->getArgument( 'view', '' ) ), '/' );

		if( $view === '' || str_contains( $view, '..' ) )
		{
			$this->output->error( 'Invalid view path.' );
			return 1;
		}

		if( !str_ends_with( $view, '.php' ) )
		{
			$view .= '.php';
		}

		$source = $this->_componentPath . '/resources/views/' . $view;
		$dest = $this->_projectPath . '/resources/views/' . $view;

		if( !file_exists( $source ) )
		{
			$this->output->error( "Package view not found: $view" );
			return 1;
		}

		if( file_exists( $dest ) )
		{
			$force = (bool) $this->input->getOption( 'force' );

			if( !$force && !$this->confirm( "Overwrite existing site view $view?", false ) )
			{
				$this->output->error( 'Publish cancelled.' );
				return 1;
			}
		}

		$destDir = dirname( $dest );

		if( !is_dir( $destDir ) && !mkdir( $destDir, 0755, true ) && !is_dir( $destDir ) )
		{
			$this->output->error( "Failed to create directory: $destDir" );
			return 1;
		}

		if( !copy( $source, $dest ) )
		{
			$this->output->error( "Failed to copy: $view" );
			return 1;
		}

		$this->recordPublishedView( $view, $dest );
		$this->output->success( "Published: resources/views/$view" );
		$this->output->info( 'Edit this copy to customize it. Unmodified copies are pruned on cms:upgrade.' );

		return 0;
	}

	/**
	 * Record the published checksum so a later upgrade can prune an unused copy.
	 */
	private function recordPublishedView( string $key, string $destPath ): void
	{
		$manifestPath = $this->_projectPath . '/.cms-manifest.json';
		$installed = [];

		if( file_exists( $manifestPath ) )
		{
			$decoded = json_decode( (string) file_get_contents( $manifestPath ), true );
			$installed = is_array( $decoded ) ? $decoded : [];
		}

		if( !isset( $installed['published_views'] ) || !is_array( $installed['published_views'] ) )
		{
			$installed['published_views'] = [];
		}

		$hash = hash_file( 'sha256', $destPath );

		if( is_string( $hash ) )
		{
			$installed['published_views'][$key] = $hash;
		}

		$installed['updated_at'] = date( 'Y-m-d H:i:s' );

		$json = json_encode( $installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if( $json !== false )
		{
			file_put_contents( $manifestPath, $json . "\n" );
		}
	}
}
