<?php

namespace Neuron\Cms\Cli\IO;

/**
 * Interface for reading user input in CLI commands.
 *
 * This abstraction allows for testable CLI commands by decoupling
 * commands from STDIN, making it easy to inject test doubles.
 */
interface IInputReader
{
	/**
	 * Prompt user for input and return their response.
	 *
	 * @param string $message The prompt message to display
	 * @return string The user's response
	 */
	public function prompt( string $message ): string;

	/**
	 * Ask user for confirmation (yes/no).
	 *
	 * @param string $message The confirmation message
	 * @param bool $default Default value if user just presses enter
	 * @return bool True if user confirms, false otherwise
	 */
	public function confirm( string $message, bool $default = false ): bool;

	/**
	 * Prompt for sensitive input (password, etc.) without echoing.
	 *
	 * @param string $message The prompt message
	 * @return string The user's input
	 */
	public function secret( string $message ): string;

	/**
	 * Prompt user to select from a list of options.
	 *
	 * @param string $message The prompt message
	 * @param array<string> $options Available options
	 * @param string|null $default Default option
	 * @return string The selected option
	 */
	public function choice( string $message, array $options, ?string $default = null ): string;
}
