# Architecture Recommendations for NeuronPHP CMS

## Executive Summary

This document outlines architectural improvements to enhance code quality, testability, and maintainability of the NeuronPHP CMS component.

## Current State Analysis

### ✅ What's Working Well

1. **Repository Pattern** - 9 repository interfaces properly defined
2. **Dependency Injection** - Constructor injection used in controllers
3. **Service Layer** - Business logic separated from controllers
4. **Exception Hierarchy** - Custom exceptions (ValidationException, RepositoryException, etc.)
5. **Enum Usage** - Type-safe constants for roles, statuses, templates

### ⚠️ Areas for Improvement

1. **Service Locator Anti-Pattern** - 20+ Registry::getInstance() calls
2. **Missing Service Interfaces** - 0 service interfaces vs 20+ service classes
3. **Concrete Dependencies** - Controllers depend on DatabaseUserRepository instead of IUserRepository
4. **Magic Numbers** - Hard-coded values scattered throughout
5. **Generic Exceptions** - 18 instances of generic Exception usage

---

## 1. Service Locator to Dependency Injection

### Problem
```php
// Anti-pattern: Service Locator
$this->_authentication = Registry::getInstance()->get('Authentication');
$settings = Registry::getInstance()->get('Settings');
```

### Solution
```php
// Use PSR-11 Container interface
interface IContainer
{
    public function get(string $id);
    public function has(string $id): bool;
    public function make(string $class, array $parameters = []);
}

// Controllers receive dependencies
public function __construct(
    ?Application $app = null,
    ?IAuthenticationService $auth = null,
    ?IUserRepository $users = null
)
{
    parent::__construct($app);
    $this->_auth = $auth ?? $app->getContainer()->get(IAuthenticationService::class);
    $this->_users = $users ?? $app->getContainer()->get(IUserRepository::class);
}
```

**Benefits:**
- Explicit dependencies (visible in constructor)
- Better testability (easy to mock)
- No hidden coupling
- IDE autocomplete works

---

## 2. Service Interfaces

### Problem
Services have no interfaces, making them hard to mock and test.

### Solution
Create interfaces for all services:

```php
// src/Cms/Services/User/IUserCreationService.php
interface IUserCreationService
{
    public function create(
        string $username,
        string $email,
        string $password,
        string $role
    ): User;
}

// Implementation
class Creator implements IUserCreationService
{
    public function create(string $username, string $email, string $password, string $role): User
    {
        // Implementation
    }
}
```

**Create interfaces for:**
- `IAuthenticationService`
- `IPasswordResetService`
- `IEmailVerificationService`
- `IUserCreationService`
- `IPostCreationService`
- `IPageCreationService`
- `IEventCreationService`
- `IMediaUploadService`

**Benefits:**
- Dependency inversion principle
- Easy to swap implementations
- Better testing with mocks
- Clear contracts

---

## 3. Replace Concrete with Interface Dependencies

### Problem
```php
public function __construct(
    ?DatabaseUserRepository $repository = null  // Concrete type!
)
```

### Solution
```php
public function __construct(
    ?IUserRepository $repository = null  // Interface!
)
```

**Update all controllers to use interfaces:**
- Users.php: `IUserRepository`
- Posts.php: `IPostRepository`
- Pages.php: `IPageRepository`
- Events.php: `IEventRepository`
- Categories.php: `ICategoryRepository`

**Benefits:**
- Loosely coupled code
- Can swap database implementation
- Easier unit testing
- Follows SOLID principles

---

## 4. Eliminate Magic Numbers

### Problem
```php
'ttl' => 3600                    // What is 3600?
'max_file_size' => 5242880       // What is 5242880?
if ($retryAfter / 3600)          // Magic calculation
```

### Solution

**Created configuration classes:**
- `/src/Cms/Config/CacheConfig.php`
- `/src/Cms/Config/UploadConfig.php`

```php
use Neuron\Cms\Config\CacheConfig;
use Neuron\Cms\Config\UploadConfig;

'ttl' => CacheConfig::DEFAULT_TTL
'max_file_size' => UploadConfig::MAX_FILE_SIZE_5MB
$hours = floor($retryAfter / CacheConfig::DEFAULT_TTL)
```

**Benefits:**
- Self-documenting code
- Single source of truth
- Easy to modify
- Type-safe constants

---

## 5. Specific Domain Exceptions

### Problem
```php
throw new \Exception('User not found');        // Generic!
throw new \Exception('Invalid password');      // Generic!
```

### Solution
```php
// Create specific exceptions
throw new UserNotFoundException($userId);
throw new InvalidPasswordException();
throw new InsufficientPermissionsException($requiredRole);
throw new DuplicateUsernameException($username);
```

**Benefits:**
- Easier to catch specific errors
- Better error messages
- More maintainable
- Self-documenting

---

## 6. Value Objects for Complex Data

### Problem
```php
// Primitive obsession
public function create(
    string $title,
    string $slug,
    string $content,
    string $status,
    ?string $excerpt,
    ?string $featuredImage,
    array $categoryIds,
    string $tagNames
): Post
```

### Solution
```php
// Use DTOs/Value Objects
class CreatePostRequest
{
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly EditorJsContent $content,
        public readonly ContentStatus $status,
        public readonly ?string $excerpt = null,
        public readonly ?ImageUrl $featuredImage = null,
        public readonly CategoryIdCollection $categoryIds = new CategoryIdCollection([]),
        public readonly TagNameCollection $tagNames = new TagNameCollection([])
    ) {}
}

public function create(CreatePostRequest $request): Post
```

**Benefits:**
- Type safety
- Validation in one place
- Easier to refactor
- Self-documenting

---

## 7. Command/Query Separation (CQRS)

### Problem
Services mix commands (mutations) and queries (reads).

### Solution
```php
// Commands (write operations)
interface ICreateUserCommand
{
    public function execute(CreateUserRequest $request): User;
}

// Queries (read operations)
interface IGetUserQuery
{
    public function execute(int $userId): ?User;
}

interface IListUsersQuery
{
    public function execute(UserFilters $filters): UserCollection;
}
```

**Benefits:**
- Clearer intent
- Optimized separately
- Easier to cache queries
- Better scalability

---

## 8. Event Sourcing for Audit Trail

### Current
Limited event usage (only domain events).

### Recommendation
```php
// Store events for audit trail
interface IDomainEvent
{
    public function getAggregateId(): int;
    public function getOccurredAt(): DateTimeImmutable;
    public function getEventData(): array;
}

class UserCreatedEvent implements IDomainEvent
{
    public function __construct(
        private readonly int $userId,
        private readonly string $username,
        private readonly string $email,
        private readonly string $role,
        private readonly DateTimeImmutable $occurredAt
    ) {}
}

// Event store
interface IEventStore
{
    public function append(IDomainEvent $event): void;
    public function getEvents(string $aggregateType, int $aggregateId): array;
}
```

**Benefits:**
- Complete audit trail
- Temporal queries
- Event replay
- Better debugging

---

## 9. Factory Pattern for Complex Object Creation

### Problem
```php
// Complex creation logic in constructor
if ($repository === null) {
    $settings = Registry::getInstance()->get('Settings');
    $repository = new DatabaseUserRepository($settings);
    $hasher = new PasswordHasher();
    $userCreator = new Creator($repository, $hasher);
}
```

### Solution
```php
// Factory handles complexity
interface IUserControllerFactory
{
    public function create(Application $app): Users;
}

class UserControllerFactory implements IUserControllerFactory
{
    public function __construct(
        private readonly IUserRepository $repository,
        private readonly IPasswordHasher $hasher
    ) {}

    public function create(Application $app): Users
    {
        return new Users(
            $app,
            $this->repository,
            new Creator($this->repository, $this->hasher),
            new Updater($this->repository, $this->hasher),
            new Deleter($this->repository)
        );
    }
}
```

**Benefits:**
- Single Responsibility
- Easier testing
- Centralized creation logic
- Reusable

---

## 10. Repository Query Objects

### Problem
```php
// Limited query capabilities
$users = $repository->all();  // Gets everything!
```

### Solution
```php
// Query specification pattern
class UserQueryBuilder
{
    private ?string $role = null;
    private ?string $status = null;
    private ?int $limit = null;
    private ?int $offset = null;
    private string $orderBy = 'created_at';
    private string $orderDirection = 'DESC';

    public function withRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function withStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function build(): UserQuery
    {
        return new UserQuery(
            $this->role,
            $this->status,
            $this->limit,
            $this->offset,
            $this->orderBy,
            $this->orderDirection
        );
    }
}

// Usage
$users = $repository->findBy(
    (new UserQueryBuilder())
        ->withRole(UserRole::ADMIN->value)
        ->withStatus(UserStatus::ACTIVE->value)
        ->limit(10)
        ->build()
);
```

**Benefits:**
- Flexible queries
- Reusable query logic
- Type-safe
- Better performance (only fetch what's needed)

---

## Implementation Priority

### Phase 1: Foundation (High Impact, Low Risk)
1. ✅ **Create configuration classes** (DONE - CacheConfig, UploadConfig)
2. ✅ **Create service interfaces** (STARTED - IAuthenticationService)
3. Replace concrete repository types with interfaces in controllers
4. Replace magic numbers with config constants

### Phase 2: Dependency Management (High Impact, Medium Risk)
1. Implement PSR-11 container interface
2. Remove Registry usage from controllers
3. Update controller constructors to use interfaces

### Phase 3: Advanced Patterns (Medium Impact, Higher Risk)
1. Introduce DTOs/Value Objects for complex operations
2. Implement CQRS pattern for services
3. Add factory pattern for complex object creation
4. Implement repository query objects

### Phase 4: Event Sourcing (Optional, Long-term)
1. Implement event store
2. Add domain event logging
3. Create event replay mechanism

---

## Testing Improvements

### Current Coverage
- 57.47% line coverage
- 1067 tests passing

### Recommendations
1. **Increase controller coverage** - Add tests for all controller actions
2. **Service layer tests** - Mock repositories, test business logic
3. **Integration tests** - Test repository implementations
4. **Contract tests** - Verify interfaces are properly implemented

### Testing Tools
```bash
# Mutation testing
composer require --dev infection/infection

# Static analysis
composer require --dev phpstan/phpstan

# Code quality
composer require --dev squizlabs/php_codesniffer
```

---

## Summary

Implementing these architectural improvements will result in:

✅ **Better Testability** - Mock interfaces instead of concrete classes
✅ **Loose Coupling** - Depend on abstractions, not implementations
✅ **Maintainability** - Clear contracts and responsibilities
✅ **Scalability** - Easy to add new features
✅ **Type Safety** - Enums, value objects, and strict typing
✅ **Code Quality** - Self-documenting, SOLID principles

The phased approach allows incremental improvements without breaking existing functionality.
