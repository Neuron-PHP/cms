<?php

namespace Neuron\Cms\Cli\IO;

use Neuron\Cli\Console\Output;

/**
 * Standard input reader for CLI commands.
 *
 * Reads user input from STDIN for interactive CLI applications.
 */
class StdinInputReader implements IInputReader
{
	public function __construct(
		private Output $output
	) {}

	/**
	 * @inheritDoc
	 */
	public function prompt( string $message ): string
	{
		$this->output->write( $message, false );
		$input = fgets( STDIN );
		return $input !== false ? trim( $input ) : '';
	}

	/**
	 * @inheritDoc
	 */
	public function confirm( string $message, bool $default = false ): bool
	{
		$suffix = $default ? ' [Y/n]' : ' [y/N]';
		$response = $this->prompt( $message . $suffix );

		if( empty( $response ) ) {
			return $default;
		}

		return in_array( strtolower( $response ), ['y', 'yes', 'true', '1'] );
	}

	/**
	 * @inheritDoc
	 */
	public function secret( string $message ): string
	{
		$this->output->write( $message, false );

		// Disable echo on Unix-like systems
		if( strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' ) {
			system( 'stty -echo' );
			$input = fgets( STDIN );
			system( 'stty echo' );
			$this->output->writeln( '' ); // New line after hidden input
		} else {
			// For Windows, fall back to regular input
			// (proper implementation would use COM or other Windows-specific methods)
			$input = fgets( STDIN );
		}

		return $input !== false ? trim( $input ) : '';
	}

	/**
	 * @inheritDoc
	 */
	public function choice( string $message, array $options, ?string $default = null ): string
	{
		$this->output->writeln( $message );

		foreach( $options as $index => $option ) {
			$marker = ($default === $option) ? '*' : ' ';
			$this->output->writeln( "  [{$marker}] {$index}. {$option}" );
		}

		$prompt = $default ? "Choice [{$default}]: " : "Choice: ";
		$response = $this->prompt( $prompt );

		if( empty( $response ) && $default !== null ) {
			return $default;
		}

		// Check if response is numeric index
		if( is_numeric( $response ) && isset( $options[(int)$response] ) ) {
			return $options[(int)$response];
		}

		// Check if response matches an option
		if( in_array( $response, $options ) ) {
			return $response;
		}

		$this->output->error( "Invalid choice. Please try again." );
		return $this->choice( $message, $options, $default );
	}
}
