# CLI Architecture Refactoring Proposal

## Overview
This document proposes architectural improvements to make CLI commands more testable, maintainable, and follow SOLID principles.

## Current Problems

### 1. Global State Dependencies
- Commands use `Registry::getInstance()->get('Settings')`
- Requires complex test setup with global state
- Tight coupling to infrastructure

### 2. Hard-coded Dependencies
- Repositories created inside private methods
- No dependency injection
- Can't inject mocks for testing

### 3. STDIN/STDOUT Coupling
- Direct `fgets(STDIN)` calls
- Impossible to test interactive prompts
- No abstraction for user input

### 4. Mixed Concerns
- Commands handle orchestration, business logic, and infrastructure
- Violates Single Responsibility Principle
- Business logic can't be tested independently

### 5. No Interfaces
- Commands don't implement testable interfaces
- Hard to create test doubles

## Proposed Solutions

### Solution 1: Constructor Dependency Injection

**Before:**
```php
class DeleteCommand extends Command
{
    private function getUserRepository(): ?DatabaseUserRepository
    {
        $settings = Registry::getInstance()->get( 'Settings' );
        return new DatabaseUserRepository( $settings );
    }
}
```

**After:**
```php
class DeleteCommand extends Command
{
    public function __construct(
        private ?IUserRepository $userRepository = null,
        private ?IInputReader $inputReader = null
    ) {
        $this->userRepository = $userRepository;
        $this->inputReader = $inputReader ?? new StdinInputReader();
    }

    // Setter for DI container
    public function setUserRepository( IUserRepository $repository ): void
    {
        $this->userRepository = $repository;
    }
}
```

**Benefits:**
- Easy to inject mocks in tests
- No Registry dependency
- Clear dependencies in constructor
- Falls back to defaults if not injected

### Solution 2: Input Reader Abstraction

Create an interface for reading user input:

```php
interface IInputReader
{
    public function prompt( string $message ): string;
    public function confirm( string $message, bool $default = false ): bool;
    public function secret( string $message ): string;
}

class StdinInputReader implements IInputReader
{
    public function __construct( private Output $output ) {}

    public function prompt( string $message ): string
    {
        $this->output->write( $message, false );
        return trim( fgets( STDIN ) );
    }

    public function confirm( string $message, bool $default = false ): bool
    {
        $response = $this->prompt( $message );
        // ... handle yes/no logic
    }
}

class TestInputReader implements IInputReader
{
    private array $responses = [];

    public function setResponse( string $response ): void
    {
        $this->responses[] = $response;
    }

    public function prompt( string $message ): string
    {
        return array_shift( $this->responses ) ?? '';
    }
}
```

**Usage in Command:**
```php
$response = $this->inputReader->prompt(
    "Are you sure you want to delete this user? Type 'DELETE' to confirm: "
);
```

**Usage in Tests:**
```php
$inputReader = new TestInputReader();
$inputReader->setResponse( 'DELETE' );
$command->setInputReader( $inputReader );
```

### Solution 3: Service Layer Extraction

Extract business logic from commands into services:

**Before:**
```php
class DeleteCommand extends Command
{
    public function execute( array $parameters = [] ): int
    {
        // Validation logic
        // Repository calls
        // Business logic
        // Error handling
    }
}
```

**After:**
```php
// Service handles business logic
class UserDeletionService
{
    public function __construct(
        private IUserRepository $userRepository
    ) {}

    public function deleteUser( string $identifier ): UserDeletionResult
    {
        // Find user
        // Validate deletion
        // Delete user
        // Return result with status and messages
    }
}

// Command handles CLI concerns only
class DeleteCommand extends Command
{
    public function __construct(
        private ?UserDeletionService $deletionService = null,
        private ?IInputReader $inputReader = null
    ) {}

    public function execute( array $parameters = [] ): int
    {
        $identifier = $parameters['identifier'] ?? null;

        if( !$identifier ) {
            $this->output->error( "Please provide a user ID or username." );
            return 1;
        }

        // Use service
        $result = $this->deletionService->deleteUser( $identifier );

        // Handle result and output
        if( $result->isSuccess() ) {
            $this->output->success( $result->getMessage() );
            return 0;
        }

        $this->output->error( $result->getMessage() );
        return 1;
    }
}
```

**Benefits:**
- Business logic testable independently
- Command focuses on CLI concerns
- Service reusable in web controllers, API, etc.
- Clear separation of concerns

### Solution 4: Command Factory Pattern

Create a factory to build commands with dependencies:

```php
class CommandFactory
{
    public function __construct(
        private IContainer $container
    ) {}

    public function createDeleteUserCommand(): DeleteCommand
    {
        return new DeleteCommand(
            $this->container->get( IUserRepository::class ),
            $this->container->get( IInputReader::class )
        );
    }

    public function createListUsersCommand(): ListCommand
    {
        return new ListCommand(
            $this->container->get( IUserRepository::class )
        );
    }
}
```

### Solution 5: Result Objects

Return structured results instead of mixing output with logic:

```php
class UserDeletionResult
{
    public function __construct(
        private bool $success,
        private string $message,
        private ?User $deletedUser = null,
        private ?string $errorCode = null
    ) {}

    public function isSuccess(): bool { return $this->success; }
    public function getMessage(): string { return $this->message; }
    public function getDeletedUser(): ?User { return $this->deletedUser; }
    public function getErrorCode(): ?string { return $this->errorCode; }
}
```

## Implementation Example

Here's a complete refactored `DeleteCommand`:

```php
class DeleteCommand extends Command
{
    private IUserRepository $userRepository;
    private IInputReader $inputReader;

    public function __construct(
        ?IUserRepository $userRepository = null,
        ?IInputReader $inputReader = null
    ) {
        $this->userRepository = $userRepository;
        $this->inputReader = $inputReader;
    }

    public function getName(): string
    {
        return 'cms:user:delete';
    }

    public function getDescription(): string
    {
        return 'Delete a user';
    }

    public function execute( array $parameters = [] ): int
    {
        $identifier = $parameters['identifier'] ?? null;

        if( !$identifier ) {
            $this->output->error( "Please provide a user ID or username." );
            return 1;
        }

        // Find user
        $user = $this->findUser( $identifier );
        if( !$user ) {
            $this->output->error( "User '$identifier' not found." );
            return 1;
        }

        // Display user info
        $this->displayUserInfo( $user );

        // Confirm deletion
        if( !$this->confirmDeletion() ) {
            $this->output->error( "Deletion cancelled." );
            return 1;
        }

        // Delete user
        try {
            $this->userRepository->delete( $user->getId() );
            $this->output->success( "User deleted successfully." );
            return 0;
        } catch( \Exception $e ) {
            $this->output->error( "Error: " . $e->getMessage() );
            return 1;
        }
    }

    private function findUser( string $identifier ): ?User
    {
        return is_numeric( $identifier )
            ? $this->userRepository->findById( (int)$identifier )
            : $this->userRepository->findByUsername( $identifier );
    }

    private function displayUserInfo( User $user ): void
    {
        $this->output->warning( "You are about to delete the following user:" );
        $this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );
        $this->output->writeln( "  ID:       " . $user->getId() );
        $this->output->writeln( "  Username: " . $user->getUsername() );
        $this->output->writeln( "  Email:    " . $user->getEmail() );
        $this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );
    }

    private function confirmDeletion(): bool
    {
        $response = $this->inputReader->prompt(
            "Are you sure you want to delete this user? Type 'DELETE' to confirm: "
        );
        return trim( $response ) === 'DELETE';
    }

    // Allow DI container to inject dependencies
    public function setUserRepository( IUserRepository $repository ): void
    {
        $this->userRepository = $repository;
    }

    public function setInputReader( IInputReader $reader ): void
    {
        $this->inputReader = $reader;
    }
}
```

## Testing Example

With these changes, testing becomes much simpler:

```php
class DeleteCommandTest extends TestCase
{
    public function testExecuteDeletesUserAfterConfirmation(): void
    {
        // Setup mocks
        $mockUser = $this->createMock( User::class );
        $mockUser->method( 'getId' )->willReturn( 1 );
        $mockUser->method( 'getUsername' )->willReturn( 'testuser' );
        $mockUser->method( 'getEmail' )->willReturn( 'test@example.com' );

        $mockRepository = $this->createMock( IUserRepository::class );
        $mockRepository->method( 'findById' )
            ->with( 1 )
            ->willReturn( $mockUser );
        $mockRepository->expects( $this->once() )
            ->method( 'delete' )
            ->with( 1 );

        $mockInputReader = new TestInputReader();
        $mockInputReader->setResponse( 'DELETE' );

        $mockOutput = $this->createMock( Output::class );
        $mockOutput->expects( $this->once() )
            ->method( 'success' )
            ->with( 'User deleted successfully.' );

        // Create command with dependencies
        $command = new DeleteCommand( $mockRepository, $mockInputReader );
        $command->setOutput( $mockOutput );

        // Execute
        $result = $command->execute( [ 'identifier' => '1' ] );

        // Assert
        $this->assertEquals( 0, $result );
    }

    public function testExecuteCancelsWhenUserDoesNotConfirm(): void
    {
        $mockUser = $this->createMock( User::class );
        $mockRepository = $this->createMock( IUserRepository::class );
        $mockRepository->method( 'findById' )->willReturn( $mockUser );
        $mockRepository->expects( $this->never() )->method( 'delete' );

        $mockInputReader = new TestInputReader();
        $mockInputReader->setResponse( 'CANCEL' );  // Wrong response

        $command = new DeleteCommand( $mockRepository, $mockInputReader );
        $result = $command->execute( [ 'identifier' => '1' ] );

        $this->assertEquals( 1, $result );
    }
}
```

## Migration Strategy

### Phase 1: Add Interfaces (Non-breaking)
1. Create `IInputReader` interface
2. Create `StdinInputReader` implementation
3. Create `TestInputReader` for tests

### Phase 2: Add Dependency Injection (Non-breaking)
1. Add optional constructor parameters to commands
2. Add setter methods for DI
3. Keep backward compatibility with current usage

### Phase 3: Extract Services (Non-breaking)
1. Create service classes for business logic
2. Commands use services internally
3. Keep existing command APIs

### Phase 4: Update Container Configuration
1. Register services in DI container
2. Register commands with dependencies
3. Update command factory

### Phase 5: Deprecate Old Patterns
1. Mark direct Registry usage as deprecated
2. Provide migration guides
3. Update documentation

## Benefits Summary

✅ **Testability**: Easy to inject mocks and test doubles
✅ **Maintainability**: Clear separation of concerns
✅ **Reusability**: Services can be used in web, API, CLI
✅ **SOLID Principles**: Each class has single responsibility
✅ **No Global State**: No Registry dependencies
✅ **Type Safety**: Clear interfaces and type hints
✅ **Better IDE Support**: Auto-completion for dependencies

## Recommended Next Steps

1. Create `IInputReader` interface and implementations
2. Refactor one command as proof of concept (DeleteCommand)
3. Write comprehensive tests for refactored command
4. Document patterns for team
5. Gradually migrate remaining commands
6. Update command factory to use DI container

## Additional Considerations

### Error Handling
- Use custom exceptions for different error scenarios
- Return result objects with error codes
- Log errors consistently

### Configuration
- Inject configuration objects instead of accessing Registry
- Use environment-specific configurations
- Validate configuration at startup

### Logging
- Inject logger instead of using static Log class
- Different log levels for different environments
- Structured logging for better debugging

### Testing Infrastructure
- Create test base classes for commands
- Provide test fixtures and factories
- Mock file system operations
