# CLI Component Refactoring Proposal

## Executive Summary

The CLI component should be updated to provide **first-class support for testability** by:
1. Adding `IInputReader` abstraction to the CLI component
2. Integrating it into the base `Command` class
3. Providing both production and test implementations
4. Making **all** components that use CLI commands testable

## Why Update CLI Component (Not CMS)

### Current Architecture
```
┌─────────────────────┐
│  CMS Component      │
│  - CLI commands     │
│  - Untestable       │
└──────────┬──────────┘
           │ extends
           ↓
┌─────────────────────┐
│  CLI Component      │
│  - Command base     │
│  - Output           │
│  - No input abstraction ❌
└─────────────────────┘
```

### Problem: Multiple Components Affected
```
Application Component → CLI Component (testability issues)
CMS Component → CLI Component (testability issues)
Future Components → CLI Component (testability issues)
```

If we only fix CMS, the other components still have the same problem.

### Correct Architecture
```
┌─────────────────────┐
│  CMS Component      │
│  - CLI commands     │
│  - Fully testable ✅│
└──────────┬──────────┘
           │ extends
           ↓
┌─────────────────────┐     ┌─────────────────────┐
│  CLI Component      │     │ Application Comp    │
│  - Command base     │◄────│ - CLI commands      │
│  - Output           │     │ - Fully testable ✅ │
│  - IInputReader ✅  │     └─────────────────────┘
└─────────────────────┘
           ↑
           │ extends
           │
    ┌─────────────────────┐
    │  Future Components  │
    │  - Testable by      │
    │    default ✅       │
    └─────────────────────┘
```

## Proposed CLI Component Changes

### 1. Add IInputReader Interface

**File: `vendor/neuron-php/cli/src/Cli/IO/IInputReader.php`**

```php
<?php

namespace Neuron\Cli\IO;

/**
 * Interface for reading user input in CLI commands.
 *
 * Provides an abstraction over STDIN to enable testable CLI commands.
 * Implementations can read from actual user input (StdinInputReader)
 * or from pre-programmed responses (TestInputReader) for testing.
 *
 * @package Neuron\Cli\IO
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
     * Ask user for yes/no confirmation.
     *
     * @param string $message The confirmation message
     * @param bool $default Default value if user just presses enter
     * @return bool True if user confirms, false otherwise
     */
    public function confirm( string $message, bool $default = false ): bool;

    /**
     * Prompt for sensitive input without echoing to console.
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
```

### 2. Add StdinInputReader Implementation

**File: `vendor/neuron-php/cli/src/Cli/IO/StdinInputReader.php`**

```php
<?php

namespace Neuron\Cli\IO;

use Neuron\Cli\Console\Output;

/**
 * Production input reader that reads from STDIN.
 *
 * @package Neuron\Cli\IO
 */
class StdinInputReader implements IInputReader
{
    public function __construct(
        private Output $output
    ) {}

    public function prompt( string $message ): string
    {
        $this->output->write( $message, false );
        $input = fgets( STDIN );
        return $input !== false ? trim( $input ) : '';
    }

    public function confirm( string $message, bool $default = false ): bool
    {
        $suffix = $default ? ' [Y/n]' : ' [y/N]';
        $response = $this->prompt( $message . $suffix );

        if( empty( $response ) ) {
            return $default;
        }

        return in_array( strtolower( $response ), ['y', 'yes', 'true', '1'] );
    }

    public function secret( string $message ): string
    {
        $this->output->write( $message, false );

        if( strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' ) {
            system( 'stty -echo' );
            $input = fgets( STDIN );
            system( 'stty echo' );
            $this->output->writeln( '' );
        } else {
            $input = fgets( STDIN );
        }

        return $input !== false ? trim( $input ) : '';
    }

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

        if( is_numeric( $response ) && isset( $options[(int)$response] ) ) {
            return $options[(int)$response];
        }

        if( in_array( $response, $options ) ) {
            return $response;
        }

        $this->output->error( "Invalid choice. Please try again." );
        return $this->choice( $message, $options, $default );
    }
}
```

### 3. Add TestInputReader for Testing

**File: `vendor/neuron-php/cli/src/Cli/IO/TestInputReader.php`**

```php
<?php

namespace Neuron\Cli\IO;

/**
 * Test double for IInputReader that returns pre-programmed responses.
 *
 * This allows CLI commands to be unit tested without requiring actual
 * user input or complex process isolation.
 *
 * Example:
 * ```php
 * $reader = new TestInputReader();
 * $reader->addResponse( 'yes' );
 * $reader->addResponse( 'John Doe' );
 *
 * $command->setInputReader( $reader );
 * $command->execute();
 * ```
 *
 * @package Neuron\Cli\IO
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
     */
    public function addResponse( string $response ): self
    {
        $this->responses[] = $response;
        return $this;
    }

    /**
     * Add multiple responses at once.
     */
    public function addResponses( array $responses ): self
    {
        $this->responses = array_merge( $this->responses, $responses );
        return $this;
    }

    /**
     * Get history of all prompts that were shown.
     * Useful for asserting correct prompts in tests.
     */
    public function getPromptHistory(): array
    {
        return $this->promptHistory;
    }

    /**
     * Reset the reader to initial state.
     */
    public function reset(): void
    {
        $this->responses = [];
        $this->currentIndex = 0;
        $this->promptHistory = [];
    }

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

    public function confirm( string $message, bool $default = false ): bool
    {
        $response = $this->prompt( $message );

        if( empty( $response ) ) {
            return $default;
        }

        return in_array( strtolower( $response ), ['y', 'yes', 'true', '1'] );
    }

    public function secret( string $message ): string
    {
        return $this->prompt( $message );
    }

    public function choice( string $message, array $options, ?string $default = null ): string
    {
        $response = $this->prompt( $message );

        if( empty( $response ) && $default !== null ) {
            return $default;
        }

        if( is_numeric( $response ) && isset( $options[(int)$response] ) ) {
            return $options[(int)$response];
        }

        if( in_array( $response, $options ) ) {
            return $response;
        }

        return $response;
    }
}
```

### 4. Update Base Command Class

**File: `vendor/neuron-php/cli/src/Cli/Commands/Command.php`**

```php
<?php

namespace Neuron\Cli\Commands;

use Neuron\Cli\Console\Input;
use Neuron\Cli\Console\Output;
use Neuron\Cli\IO\IInputReader;
use Neuron\Cli\IO\StdinInputReader;

abstract class Command
{
    protected Input $input;
    protected Output $output;
    protected ?IInputReader $inputReader = null;  // ← NEW

    public function __construct()
    {
        // Existing initialization
    }

    /**
     * Get the input reader instance.
     *
     * Creates a default StdinInputReader if not already set.
     */
    protected function getInputReader(): IInputReader
    {
        if( !$this->inputReader ) {
            $this->inputReader = new StdinInputReader( $this->output );
        }

        return $this->inputReader;
    }

    /**
     * Set the input reader (for dependency injection, especially in tests).
     */
    public function setInputReader( IInputReader $inputReader ): void
    {
        $this->inputReader = $inputReader;
    }

    /**
     * Convenience method: Prompt user for input.
     */
    protected function prompt( string $message ): string
    {
        return $this->getInputReader()->prompt( $message );
    }

    /**
     * Convenience method: Ask for yes/no confirmation.
     */
    protected function confirm( string $message, bool $default = false ): bool
    {
        return $this->getInputReader()->confirm( $message, $default );
    }

    /**
     * Convenience method: Prompt for secret input.
     */
    protected function secret( string $message ): string
    {
        return $this->getInputReader()->secret( $message );
    }

    /**
     * Convenience method: Prompt for choice from options.
     */
    protected function choice( string $message, array $options, ?string $default = null ): string
    {
        return $this->getInputReader()->choice( $message, $options, $default );
    }

    // ... existing methods (getName, getDescription, execute, etc.)
}
```

## Usage Examples

### In CMS Commands (Simplified)

**Before:**
```php
class DeleteCommand extends Command
{
    public function execute( array $parameters = [] ): int
    {
        // Had to do this manually with fgets(STDIN)
        $this->output->write( "Confirm? ", false );
        $response = trim( fgets( STDIN ) );  // ❌ Untestable

        if( $response !== 'DELETE' ) {
            return 1;
        }
        // ...
    }
}
```

**After:**
```php
class DeleteCommand extends Command
{
    public function execute( array $parameters = [] ): int
    {
        // Use inherited method from Command base class
        $response = $this->prompt( "Are you sure? Type 'DELETE': " );  // ✅ Testable

        if( $response !== 'DELETE' ) {
            return 1;
        }
        // ...
    }
}
```

### In Tests (Any Component)

```php
class DeleteCommandTest extends TestCase
{
    public function testDeleteWithConfirmation(): void
    {
        $command = new DeleteCommand();

        // Inject test input reader
        $testReader = new TestInputReader();
        $testReader->addResponse( 'DELETE' );
        $command->setInputReader( $testReader );

        $result = $command->execute( [ 'identifier' => '1' ] );

        $this->assertEquals( 0, $result );
    }

    public function testDeleteCancellation(): void
    {
        $command = new DeleteCommand();

        $testReader = new TestInputReader();
        $testReader->addResponse( 'CANCEL' );  // Wrong confirmation
        $command->setInputReader( $testReader );

        $result = $command->execute( [ 'identifier' => '1' ] );

        $this->assertEquals( 1, $result );
    }
}
```

## Benefits of CLI Component Approach

### 1. Universal Benefit
✅ **All components** using CLI get testability for free
✅ **Consistent** approach across the framework
✅ **No duplication** of input abstractions

### 2. Framework-Level Improvement
✅ CLI component becomes **more valuable**
✅ **Better architecture** for all users
✅ **Standard pattern** for CLI testing

### 3. Backward Compatible
```php
// Old code still works - getInputReader() creates default
class MyCommand extends Command
{
    public function execute(): int
    {
        // This automatically uses StdinInputReader
        $name = $this->prompt( "Enter name: " );
        // ...
    }
}

// New code can inject for testing
$command = new MyCommand();
$command->setInputReader( new TestInputReader() );
```

### 4. DRY Principle
```
Before (duplicated in each component):
- CMS/IO/IInputReader.php
- Application/IO/IInputReader.php
- SomeOtherComponent/IO/IInputReader.php

After (once in CLI component):
- CLI/IO/IInputReader.php  ← Used by all
```

## Migration Strategy

### Phase 1: Add to CLI Component (Non-Breaking)
1. Add `IInputReader` interface
2. Add `StdinInputReader` implementation
3. Add `TestInputReader` for testing
4. Add optional methods to `Command` base class
5. **All existing code continues to work**

### Phase 2: Update Component Commands
1. Update CMS commands to use `$this->prompt()` etc.
2. Update Application commands
3. Add tests with `TestInputReader`

### Phase 3: Deprecate Old Patterns
1. Mark direct `fgets(STDIN)` usage as deprecated
2. Update documentation
3. Provide migration examples

## Version Strategy

### CLI Component Version Bump
```json
{
  "name": "neuron-php/cli",
  "version": "1.1.0",  // Minor version bump (new features, backward compatible)
  "description": "CLI framework with testable input support"
}
```

**Why minor version bump:**
- ✅ Adds new features (IInputReader)
- ✅ Backward compatible (existing code works)
- ✅ No breaking changes
- ✅ Semantic versioning compliant

### Component Updates
```json
{
  "require": {
    "neuron-php/cli": "^1.1"  // Can use new features
  }
}
```

Components can upgrade at their own pace:
- **Immediately**: Get new features, old code still works
- **Gradually**: Update commands to use new patterns
- **Eventually**: Remove old STDIN patterns

## Comparison: CMS-Only vs CLI Component

| Aspect | CMS-Only Approach | CLI Component Approach |
|--------|-------------------|------------------------|
| **Testability** | CMS only | All components |
| **Code duplication** | High (per component) | None (in CLI) |
| **Consistency** | Varies by component | Framework-wide |
| **Maintenance** | Multiple places | Single source |
| **Future components** | Must implement own | Get for free |
| **Framework quality** | CMS improved | Entire framework improved |
| **Backward compatibility** | CMS only | All components |

## Recommended Action Plan

### 1. Create CLI Component PR
- Add IO interfaces and implementations
- Update Command base class
- Add tests for new functionality
- Update CLI component documentation

### 2. Release CLI v1.1.0
- Publish to packagist
- Announce new testability features
- Provide migration guide

### 3. Update Consuming Components
- Update CMS component to use new features
- Update Application component
- Add comprehensive tests

### 4. Document Best Practices
- Testing guide for CLI commands
- Examples of testable commands
- Migration guide from old patterns

## Conclusion

**You're absolutely right** - this should be a CLI component improvement.

### Why CLI Component is the Right Place:
1. ✅ **Universal benefit** - all components get testability
2. ✅ **Architectural integrity** - solves the problem at the source
3. ✅ **DRY principle** - one implementation, used everywhere
4. ✅ **Framework quality** - improves the foundation
5. ✅ **Future-proof** - new components testable by default

### Next Steps:
1. Open issue on CLI component repository
2. Propose these changes
3. Submit PR with implementation
4. Update consuming components after CLI release

This transforms the CLI component from a basic command framework into a **fully testable** CLI framework that benefits the entire Neuron-PHP ecosystem.
