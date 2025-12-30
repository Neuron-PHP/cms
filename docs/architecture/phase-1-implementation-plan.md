# Phase 1 Implementation Plan - No CLI Component Changes Required

## Overview

Phase 1 can be implemented **entirely within the CMS component** without modifying the `neuron-php/cli` component. The `IInputReader` abstraction is a CMS-specific pattern that sits on top of the base CLI framework.

## Why No CLI Component Changes Needed

### Current CLI Component Structure
```
vendor/neuron-php/cli/
├── src/
│   └── Cli/
│       ├── Commands/
│       │   └── Command.php          # Base command class
│       └── Console/
│           ├── Input.php             # Already exists
│           └── Output.php            # Already exists
```

The CLI component already provides:
- ✅ Base `Command` class
- ✅ `Input` for reading arguments/options
- ✅ `Output` for writing to console

### What We're Adding (CMS Component Only)
```
src/Cms/Cli/
├── IO/
│   ├── IInputReader.php              # NEW - CMS-specific interface
│   ├── StdinInputReader.php          # NEW - CMS implementation
│   └── TestInputReader.php           # NEW - CMS test double
└── Commands/
    └── User/
        └── DeleteCommand.php         # MODIFIED - uses IInputReader
```

## Implementation Details

### 1. IInputReader is CMS-Specific

The `IInputReader` interface is a **higher-level abstraction** built on top of the CLI component's existing functionality:

```php
// src/Cms/Cli/IO/IInputReader.php
namespace Neuron\Cms\Cli\IO;  // ← CMS namespace, not CLI namespace

interface IInputReader
{
    public function prompt( string $message ): string;
    public function confirm( string $message, bool $default = false ): bool;
    public function secret( string $message ): string;
    public function choice( string $message, array $options, ?string $default = null ): string;
}
```

### 2. StdinInputReader Uses Existing CLI Components

```php
// src/Cms/Cli/IO/StdinInputReader.php
namespace Neuron\Cms\Cli\IO;

use Neuron\Cli\Console\Output;  // ← Uses CLI component's Output

class StdinInputReader implements IInputReader
{
    public function __construct(
        private Output $output  // ← Existing CLI component class
    ) {}

    public function prompt( string $message ): string
    {
        // Uses existing Output from CLI component
        $this->output->write( $message, false );

        // Uses standard PHP STDIN (not from CLI component)
        return trim( fgets( STDIN ) );
    }

    // ... other methods
}
```

### 3. CMS Commands Use Both

```php
// src/Cms/Cli/Commands/User/DeleteCommand.php
namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Cli\Commands\Command;      // ← From CLI component
use Neuron\Cms\Cli\IO\IInputReader;   // ← From CMS component

class DeleteCommand extends Command    // ← Extends CLI component
{
    private IInputReader $inputReader; // ← Uses CMS abstraction

    public function __construct(
        ?IUserRepository $userRepository = null,
        ?IInputReader $inputReader = null
    ) {
        // IInputReader is optional, created on-demand
        $this->userRepository = $userRepository;
        $this->inputReader = $inputReader;

        parent::__construct(); // ← CLI component constructor
    }

    public function execute( array $parameters = [] ): int
    {
        // Lazy-initialize input reader if not injected
        if( !$this->inputReader ) {
            $this->inputReader = new StdinInputReader( $this->output );
        }

        // Use the abstraction
        $response = $this->inputReader->prompt( "Confirm? " );

        // ...
    }
}
```

## Phase 1 Implementation Steps

### Step 1: Create Interface (No Dependencies)
```bash
# Create directory
mkdir -p src/Cms/Cli/IO

# Create interface
touch src/Cms/Cli/IO/IInputReader.php
```

**File: `src/Cms/Cli/IO/IInputReader.php`**
- No dependencies on CLI component
- Pure interface definition
- CMS namespace

### Step 2: Create StdinInputReader (Uses Existing CLI)
```bash
touch src/Cms/Cli/IO/StdinInputReader.php
```

**File: `src/Cms/Cli/IO/StdinInputReader.php`**
- Depends on `Neuron\Cli\Console\Output` (already exists in vendor)
- Uses standard PHP `fgets(STDIN)`
- No modifications to CLI component needed

### Step 3: Create TestInputReader (Test Only)
```bash
touch src/Cms/Cli/IO/TestInputReader.php
```

**File: `src/Cms/Cli/IO/TestInputReader.php`**
- Zero dependencies
- Pure PHP, no framework code
- Used only in tests

### Step 4: Update CMS Commands (Backward Compatible)
```bash
# Update commands one at a time
vim src/Cms/Cli/Commands/User/DeleteCommand.php
```

**Changes to DeleteCommand:**
```php
// Add optional constructor parameter
public function __construct(
    ?IUserRepository $userRepository = null,
    ?IInputReader $inputReader = null  // ← NEW, optional
) {
    $this->userRepository = $userRepository;
    $this->inputReader = $inputReader;
    parent::__construct();
}

// Lazy-create if not injected (backward compatible)
public function execute( array $parameters = [] ): int
{
    if( !$this->inputReader ) {
        $this->inputReader = new StdinInputReader( $this->output );
    }
    // ... rest of code
}
```

**Backward compatibility:**
```php
// Old usage still works - creates StdinInputReader internally
$command = new DeleteCommand();

// New usage - inject for testing
$command = new DeleteCommand( $repo, new TestInputReader() );
```

## Why This Works Without CLI Component Changes

### 1. Composition Over Inheritance
We're **composing** the CLI component's `Output` class, not modifying it:
```php
class StdinInputReader implements IInputReader
{
    public function __construct(
        private Output $output  // ← Composition
    ) {}
}
```

### 2. Building on Top, Not Modifying
```
┌─────────────────────────────────────┐
│   CMS Component (Phase 1 Changes)  │
│                                     │
│  ┌──────────────────────────────┐  │
│  │  IInputReader (new)          │  │
│  │  StdinInputReader (new)      │  │
│  │  TestInputReader (new)       │  │
│  └────────┬─────────────────────┘  │
│           │ uses                    │
│           ↓                         │
│  ┌──────────────────────────────┐  │
│  │  CLI Component (unchanged)   │  │
│  │  - Command.php              │  │
│  │  - Output.php               │  │
│  │  - Input.php                │  │
│  └──────────────────────────────┘  │
└─────────────────────────────────────┘
```

### 3. CMS Commands Own Their Dependencies
```php
class DeleteCommand extends Command
{
    // CMS-specific dependencies
    private IUserRepository $userRepository;  // CMS
    private IInputReader $inputReader;        // CMS

    // Inherited from CLI component
    protected Output $output;                 // CLI component
    protected Input $input;                   // CLI component
}
```

## Testing Phase 1 Changes

### Unit Test (No CLI Component Needed)
```php
class StdinInputReaderTest extends TestCase
{
    public function testPromptWritesToOutput(): void
    {
        $mockOutput = $this->createMock( Output::class );
        $mockOutput->expects( $this->once() )
            ->method( 'write' )
            ->with( 'Enter name: ', false );

        $reader = new StdinInputReader( $mockOutput );

        // Can't actually test STDIN read without process isolation
        // But we CAN verify output interaction
    }
}
```

### Command Test (Using TestInputReader)
```php
class DeleteCommandTest extends TestCase
{
    public function testDeleteWithConfirmation(): void
    {
        $mockRepo = $this->createMock( IUserRepository::class );
        $testReader = new TestInputReader();
        $testReader->addResponse( 'DELETE' );

        // ✅ No CLI component mocking needed
        $command = new DeleteCommand( $mockRepo, $testReader );

        $result = $command->execute( [ 'identifier' => '1' ] );

        $this->assertEquals( 0, $result );
    }
}
```

## Verification Checklist

After Phase 1 implementation, verify:

- [ ] ✅ No changes to `vendor/neuron-php/cli`
- [ ] ✅ All new files in `src/Cms/Cli/IO/`
- [ ] ✅ Commands still extend `Neuron\Cli\Commands\Command`
- [ ] ✅ StdinInputReader uses `Neuron\Cli\Console\Output`
- [ ] ✅ Old command usage still works (backward compatible)
- [ ] ✅ New command usage works with injected IInputReader
- [ ] ✅ Tests can inject TestInputReader
- [ ] ✅ No composer.json changes needed

## Composer Perspective

### Current composer.json
```json
{
    "require": {
        "neuron-php/cli": "^1.0"
    }
}
```

### After Phase 1
```json
{
    "require": {
        "neuron-php/cli": "^1.0"  // ← Same version, no changes
    }
}
```

**No version bump needed** because we're not modifying the CLI component.

## Benefits of This Approach

### 1. Independence
- CMS component owns its abstractions
- CLI component remains generic
- Other projects can use CLI component without IInputReader

### 2. Flexibility
- Can create CMS-specific input methods
- Can add business logic to input readers
- Can version independently

### 3. No Breaking Changes
- CLI component users unaffected
- CMS can evolve input handling independently
- Easy to test and iterate

### 4. Clear Ownership
- CMS owns testability patterns
- CLI owns base command infrastructure
- Each component has clear responsibility

## When Would CLI Component Need Updates?

The CLI component would only need updates if you wanted:

1. **To add IInputReader to the base Command class**
   ```php
   // In vendor/neuron-php/cli/src/Cli/Commands/Command.php
   abstract class Command
   {
       protected IInputReader $inputReader; // ← Would require CLI update
   }
   ```
   **Not needed** - Commands can have their own properties

2. **To make IInputReader part of the CLI framework**
   ```php
   // In vendor/neuron-php/cli/src/Cli/IO/IInputReader.php
   namespace Neuron\Cli\IO; // ← Would require CLI update
   ```
   **Not needed** - CMS namespace is fine

3. **To modify Command base class behavior**
   ```php
   // In vendor/neuron-php/cli/src/Cli/Commands/Command.php
   public function __construct()
   {
       $this->inputReader = new InputReader(); // ← Would require CLI update
   }
   ```
   **Not needed** - CMS commands handle their own initialization

## Conclusion

✅ **Phase 1 requires ZERO changes to the CLI component**

All work is contained within the CMS component:
- New interfaces in `src/Cms/Cli/IO/`
- Updated commands in `src/Cms/Cli/Commands/`
- New tests in `tests/Unit/Cms/Cli/`

The CLI component remains a stable, generic foundation that the CMS component builds upon.
