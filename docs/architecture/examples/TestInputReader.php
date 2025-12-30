<?php

namespace Neuron\Cms\Cli\IO;

/**
 * Test input reader for CLI command testing.
 *
 * Allows pre-programming responses for testing interactive CLI commands
 * without requiring actual user input.
 *
 * Usage:
 * ```php
 * $reader = new TestInputReader();
 * $reader->addResponse( 'yes' );
 * $reader->addResponse( 'test@example.com' );
 *
 * $command->setInputReader( $reader );
 * $command->execute();
 * ```
 */
class TestInputReader implements IInputReader
{
	/** @var array<string> */
	private array $responses = [];

	/** @var int */
	private int $currentIndex = 0;

	/** @var array<string> */
	private array $promptHistory = [];

	/**
	 * Add a response to the queue.
	 *
	 * @param string $response The response to return when prompted
	 * @return self For method chaining
	 */
	public function addResponse( string $response ): self
	{
		$this->responses[] = $response;
		return $this;
	}

	/**
	 * Add multiple responses at once.
	 *
	 * @param array<string> $responses Array of responses
	 * @return self For method chaining
	 */
	public function addResponses( array $responses ): self
	{
		$this->responses = array_merge( $this->responses, $responses );
		return $this;
	}

	/**
	 * Get the history of prompts that were displayed.
	 *
	 * Useful for asserting that correct prompts were shown.
	 *
	 * @return array<string>
	 */
	public function getPromptHistory(): array
	{
		return $this->promptHistory;
	}

	/**
	 * Reset the input reader to initial state.
	 *
	 * @return void
	 */
	public function reset(): void
	{
		$this->responses = [];
		$this->currentIndex = 0;
		$this->promptHistory = [];
	}

	/**
	 * @inheritDoc
	 */
	public function prompt( string $message ): string
	{
		$this->promptHistory[] = $message;

		if( !isset( $this->responses[$this->currentIndex] ) ) {
			throw new \RuntimeException(
				"No response configured for prompt #{$this->currentIndex}: {$message}"
			);
		}

		return $this->responses[$this->currentIndex++];
	}

	/**
	 * @inheritDoc
	 */
	public function confirm( string $message, bool $default = false ): bool
	{
		$response = $this->prompt( $message );

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
		// For testing, secrets work the same as regular prompts
		return $this->prompt( $message );
	}

	/**
	 * @inheritDoc
	 */
	public function choice( string $message, array $options, ?string $default = null ): string
	{
		$response = $this->prompt( $message );

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

		// In tests, invalid choice returns the response as-is
		// (real implementation would re-prompt)
		return $response;
	}
}
