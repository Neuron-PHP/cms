# CMS Exception Handling Guide

## Overview

The Neuron CMS uses a standardized exception hierarchy to provide consistent error handling, logging, and user feedback across the application.

## Exception Hierarchy

```
CmsException (base)
├── ValidationException
├── DuplicateEntityException
├── EntityNotFoundException
├── AuthorizationException
├── RepositoryException
└── SecurityException
```

## Exception Types

### CmsException (Base)

Base exception for all CMS-specific exceptions. Provides:
- Technical message (for logs)
- User-friendly message (for display)
- shouldLog() method to control logging behavior

```php
use Neuron\Cms\Exceptions\CmsException;

throw new CmsException(
    'Technical error message',  // For logs
    'User-friendly message',    // For display (optional)
    0,                          // Error code (optional)
    $previousException          // Previous exception (optional)
);

// Get user-safe message
$userMessage = $e->getUserMessage();

// Check if should be logged
if( $e->shouldLog() ) {
    Log::error( $e->getMessage() );
}
```

### ValidationException

For validation failures. Supports single or multiple errors.

```php
use Neuron\Cms\Exceptions\ValidationException;

// Single error
throw new ValidationException( 'Email is required' );

// Multiple errors
throw new ValidationException([
    'Email is required',
    'Password must be at least 8 characters'
]);

// Get all errors
$errors = $e->getErrors();  // Returns array

// Auto-logged: No (validation errors are expected)
```

### DuplicateEntityException

For duplicate entity detection (usernames, emails, slugs, etc.).

```php
use Neuron\Cms\Exceptions\DuplicateEntityException;

throw new DuplicateEntityException(
    'User',                // Entity type
    'username',            // Field name
    'john_doe'             // Duplicate value
);

// User message: "Username 'john_doe' is already in use"
// Log message: "Duplicate User: username 'john_doe' already exists"
// Auto-logged: No
```

### EntityNotFoundException

For missing entities (users, posts, categories, etc.).

```php
use Neuron\Cms\Exceptions\EntityNotFoundException;

// By ID
throw new EntityNotFoundException(
    'User',     // Entity type
    123,        // Identifier
    'ID'        // Identifier type (default)
);

// By slug
throw new EntityNotFoundException(
    'Post',
    'my-post-slug',
    'slug'
);

// User message: "User not found"
// Log message: "User not found: ID 123"
// Auto-logged: Yes
```

### AuthorizationException

For permission/authorization failures.

```php
use Neuron\Cms\Exceptions\AuthorizationException;

// With resource
throw new AuthorizationException(
    'edit',         // Action
    'this post'     // Resource
);

// Without resource
throw new AuthorizationException( 'access admin panel' );

// User message: "You don't have permission to perform this action"
// Log message: "Unauthorized to edit this post"
// Auto-logged: Yes
```

### RepositoryException

For database/repository operation failures.

```php
use Neuron\Cms\Exceptions\RepositoryException;

throw new RepositoryException(
    'save',                             // Operation
    'User',                             // Entity type
    'Database connection failed'        // Details (optional)
);

// User message: "An error occurred while processing your request. Please try again."
// Log message: "Failed to save User: Database connection failed"
// Auto-logged: Yes
```

### SecurityException

For security violations (CSRF, XSS, etc.).

```php
use Neuron\Cms\Exceptions\SecurityException;

throw new SecurityException( 'CSRF token validation failed' );

// User message: "Invalid security token. Please try again."
// Log message: "Security violation: CSRF token validation failed"
// Auto-logged: Yes
```

## Migration Guide

### Repository Pattern

**Before:**
```php
if( $this->findByUsername( $user->getUsername() ) )
{
    throw new Exception( 'Username already exists' );
}
```

**After:**
```php
if( $this->findByUsername( $user->getUsername() ) )
{
    throw new DuplicateEntityException( 'User', 'username', $user->getUsername() );
}
```

### Validation Pattern

**Before:**
```php
$errors = [];
if( empty( $username ) ) $errors[] = 'Username is required';
if( empty( $email ) ) $errors[] = 'Email is required';

if( !empty( $errors ) )
{
    throw new Exception( implode( ', ', $errors ) );
}
```

**After:**
```php
$errors = [];
if( empty( $username ) ) $errors[] = 'Username is required';
if( empty( $email ) ) $errors[] = 'Email is required';

if( !empty( $errors ) )
{
    throw new ValidationException( $errors );
}
```

### Controller Pattern

**Before:**
```php
try
{
    $this->service->doSomething();
}
catch( \Exception $e )
{
    $this->redirect( 'route_name', [], ['error', 'Failed: ' . $e->getMessage()] );
}
```

**After:**
```php
use Neuron\Cms\Exceptions\CmsException;

try
{
    $this->service->doSomething();
}
catch( CmsException $e )
{
    if( $e->shouldLog() )
    {
        Log::error( $e->getMessage(), ['exception' => $e] );
    }

    $this->redirect( 'route_name', [], ['error', $e->getUserMessage()] );
}
```

### Service Pattern

**Before:**
```php
if( !$user )
{
    throw new \RuntimeException( 'User not found' );
}

if( !$user->isAdmin() )
{
    throw new \RuntimeException( 'Unauthorized' );
}
```

**After:**
```php
if( !$user )
{
    throw new EntityNotFoundException( 'User', $userId );
}

if( !$user->isAdmin() )
{
    throw new AuthorizationException( 'access', 'admin panel' );
}
```

## Best Practices

### 1. Use Specific Exceptions

Use the most specific exception type for the error:
- ✅ `DuplicateEntityException` for duplicates
- ❌ `CmsException` for duplicates

### 2. Provide Context

Include entity types, identifiers, and field names:
```php
// Good
throw new EntityNotFoundException( 'Post', 123, 'ID' );

// Bad
throw new EntityNotFoundException( 'Entity', 123 );
```

### 3. Separate Technical and User Messages

```php
// CmsException allows both
throw new CmsException(
    'Database query failed: SELECT * FROM users WHERE id = ?',  // Technical (logs)
    'Unable to load user profile. Please try again.'             // User-friendly
);
```

### 4. Chain Exceptions

Preserve the original exception for debugging:
```php
try
{
    $pdo->query( $sql );
}
catch( \PDOException $e )
{
    throw new RepositoryException(
        'query',
        'User',
        $e->getMessage(),
        0,
        $e  // Preserve original exception
    );
}
```

### 5. Let Exceptions Bubble

Don't catch exceptions just to re-throw them. Let specific exceptions bubble to controllers:

```php
// Service layer - let exceptions bubble
public function createUser( string $username, string $email ): User
{
    // Will throw DuplicateEntityException if duplicate
    return $this->repository->create( $user );
}

// Controller layer - catch and handle
public function store( Request $request ): never
{
    try
    {
        $this->service->createUser( $username, $email );
        $this->redirect( 'users', [], ['success', 'User created'] );
    }
    catch( DuplicateEntityException $e )
    {
        $this->redirect( 'users_create', [], ['error', $e->getUserMessage()] );
    }
}
```

## Testing Exceptions

```php
use PHPUnit\Framework\TestCase;
use Neuron\Cms\Exceptions\DuplicateEntityException;

class UserRepositoryTest extends TestCase
{
    public function test_create_throws_exception_for_duplicate_username(): void
    {
        $this->expectException( DuplicateEntityException::class );
        $this->expectExceptionMessage( "username 'john' already exists" );

        // Create duplicate user
        $this->repository->create( $user );
    }

    public function test_exception_provides_user_friendly_message(): void
    {
        try
        {
            $this->repository->create( $duplicateUser );
            $this->fail( 'Expected exception was not thrown' );
        }
        catch( DuplicateEntityException $e )
        {
            $this->assertEquals(
                "Username 'john' is already in use",
                $e->getUserMessage()
            );
        }
    }
}
```

## Logging Integration

```php
use Neuron\Log\Log;
use Neuron\Cms\Exceptions\CmsException;

// In controller or global exception handler
try
{
    $this->service->doSomething();
}
catch( CmsException $e )
{
    // Only log if the exception says it should be logged
    if( $e->shouldLog() )
    {
        Log::error( $e->getMessage(), [
            'exception' => get_class( $e ),
            'user_id' => $user?->getId(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    // Always show user-friendly message
    return $this->errorResponse( $e->getUserMessage() );
}
```
