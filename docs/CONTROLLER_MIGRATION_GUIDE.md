# Controller Migration Guide: From Registry to Dependency Injection

This guide shows how to migrate controllers from the Service Locator (Registry) pattern to modern Dependency Injection using the container.

## Quick Reference

### Before (Service Locator)
```php
class Users extends Content
{
    private IUserRepository $_repository;

    public function __construct(?Application $app = null, ?IUserRepository $repository = null)
    {
        parent::__construct($app);

        // Hidden dependency - uses Registry
        if ($repository === null) {
            $settings = Registry::getInstance()->get('Settings');
            $repository = new DatabaseUserRepository($settings);
            $hasher = new PasswordHasher();
            $userCreator = new Creator($repository, $hasher);
        }

        $this->_repository = $repository;
    }
}
```

### After (Dependency Injection)
```php
class Users extends Content
{
    private IUserRepository $_repository;
    private IUserCreator $_userCreator;

    public function __construct(
        Application $app,
        IUserRepository $repository,      // Auto-injected
        IUserCreator $userCreator         // Auto-injected
    ) {
        parent::__construct($app);
        $this->_repository = $repository;
        $this->_userCreator = $userCreator;
    }
}
```

## Migration Steps

### Step 1: Identify Dependencies

Look for these patterns in the constructor:
- `Registry::getInstance()->get(...)`
- `new DatabaseRepository(...)`
- `new ServiceClass(...)`

Example from Users controller:
```php
// OLD: Hidden dependencies
$settings = Registry::getInstance()->get('Settings');
$repository = new DatabaseUserRepository($settings);
$hasher = new PasswordHasher();
$userCreator = new Creator($repository, $hasher);
$userUpdater = new Updater($repository, $hasher);
$userDeleter = new Deleter($repository);
```

### Step 2: Add Constructor Parameters

Replace factory logic with type-hinted parameters:

```php
// BEFORE
public function __construct(?Application $app = null, ?IUserRepository $repository = null)
{
    parent::__construct($app);

    if ($repository === null) {
        // Factory logic...
    }
}

// AFTER
public function __construct(
    Application $app,
    IUserRepository $repository,
    IUserCreator $userCreator,
    IUserUpdater $userUpdater,
    IUserDeleter $userDeleter
) {
    parent::__construct($app);
    $this->_repository = $repository;
    $this->_userCreator = $userCreator;
    $this->_userUpdater = $userUpdater;
    $this->_userDeleter = $userDeleter;
}
```

### Step 3: Update Service Provider

Ensure all dependencies are registered in `CmsServiceProvider`:

```php
// src/Cms/Container/CmsServiceProvider.php
public function register(IContainer $container): void
{
    // Repositories
    $container->bind(IUserRepository::class, DatabaseUserRepository::class);

    // Services
    $container->bind(IUserCreator::class, Creator::class);
    $container->bind(IUserUpdater::class, Updater::class);
    $container->bind(IUserDeleter::class, Deleter::class);
}
```

### Step 4: Remove Null Defaults (Optional)

For cleaner code, remove optional parameters:

```php
// BEFORE: Optional parameters for backward compatibility
public function __construct(?Application $app = null, ?IUserRepository $repo = null)

// AFTER: Required parameters (container always provides them)
public function __construct(Application $app, IUserRepository $repo)
```

### Step 5: Update Tests

Use dependency injection in tests:

```php
// BEFORE: Uses Registry internally
public function testIndexReturnsAllUsers()
{
    $app = $this->createMock(Application::class);
    $controller = new Users($app);
    // ...
}

// AFTER: Inject mocks
public function testIndexReturnsAllUsers()
{
    $app = $this->createMock(Application::class);
    $mockRepo = $this->createMock(IUserRepository::class);
    $mockCreator = $this->createMock(IUserCreator::class);

    $controller = new Users($app, $mockRepo, $mockCreator, ...);
    // ...
}
```

## Complete Example: Users Controller Migration

### Before Migration

```php
<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Services\User\Creator;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Mvc\Application;
use Neuron\Patterns\Registry;

class Users extends Content
{
    private IUserRepository $_repository;
    private Creator $_userCreator;

    public function __construct(
        ?Application $app = null,
        ?IUserRepository $repository = null,
        ?Creator $userCreator = null
    ) {
        parent::__construct($app);

        if ($repository === null) {
            // Service Locator anti-pattern
            $settings = Registry::getInstance()->get('Settings');
            $repository = new DatabaseUserRepository($settings);
            $hasher = new PasswordHasher();
            $userCreator = new Creator($repository, $hasher);
        }

        $this->_repository = $repository;
        $this->_userCreator = $userCreator;
    }

    public function index(Request $request): string
    {
        $users = $this->_repository->all();
        // ... render view
    }
}
```

### After Migration

```php
<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\IUserCreator;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Services\User\IUserDeleter;
use Neuron\Mvc\Application;

class Users extends Content
{
    private IUserRepository $_repository;
    private IUserCreator $_userCreator;
    private IUserUpdater $_userUpdater;
    private IUserDeleter $_userDeleter;

    public function __construct(
        Application $app,
        IUserRepository $repository,
        IUserCreator $userCreator,
        IUserUpdater $userUpdater,
        IUserDeleter $userDeleter
    ) {
        parent::__construct($app);

        // Just assign - no factory logic!
        $this->_repository = $repository;
        $this->_userCreator = $userCreator;
        $this->_userUpdater = $userUpdater;
        $this->_userDeleter = $userDeleter;
    }

    public function index(Request $request): string
    {
        $users = $this->_repository->all();
        // ... render view
    }
}
```

### What Changed?

1. ✅ **Removed** `Registry::getInstance()` calls
2. ✅ **Removed** `new DatabaseUserRepository()` instantiation
3. ✅ **Removed** `new Creator()` instantiation
4. ✅ **Removed** factory logic from constructor
5. ✅ **Added** type-hinted parameters
6. ✅ **Changed** to interface types (IUserCreator instead of Creator)
7. ✅ **Simplified** constructor to just assignment

### Lines of Code

- **Before:** 25 lines of constructor code
- **After:** 11 lines of constructor code
- **Savings:** 56% reduction in boilerplate!

## Common Patterns

### Pattern 1: Repository-Only Controller

```php
// Simple case - just needs a repository
class Pages extends Content
{
    public function __construct(
        Application $app,
        IPageRepository $repository
    ) {
        parent::__construct($app);
        $this->_repository = $repository;
    }
}
```

### Pattern 2: Repository + Services

```php
// Common case - repository and CRUD services
class Posts extends Content
{
    public function __construct(
        Application $app,
        IPostRepository $repository,
        IPostCreator $creator,
        IPostUpdater $updater
    ) {
        parent::__construct($app);
        $this->_repository = $repository;
        $this->_creator = $creator;
        $this->_updater = $updater;
    }
}
```

### Pattern 3: Multiple Repositories

```php
// Complex case - multiple repositories
class Blog extends Content
{
    public function __construct(
        Application $app,
        IPostRepository $postRepo,
        ICategoryRepository $categoryRepo,
        ITagRepository $tagRepo
    ) {
        parent::__construct($app);
        $this->_postRepository = $postRepo;
        $this->_categoryRepository = $categoryRepo;
        $this->_tagRepository = $tagRepo;
    }
}
```

## Troubleshooting

### "Cannot resolve dependency"

**Problem:** Container can't find a binding.

**Solution:** Add binding to service provider:
```php
$container->bind(IMissingService::class, ConcreteService::class);
```

### "Too few arguments to function __construct"

**Problem:** Calling constructor manually without all dependencies.

**Solution:** Use container to create instance:
```php
// DON'T: new Users($app) - missing parameters!
// DO: $container->make(Users::class) - auto-wired!
```

### "Constructor parameter must be nullable"

**Problem:** Trying to maintain backward compatibility.

**Solution:** Either:
1. Keep optional parameters during transition
2. Or fully commit to DI (recommended)

```php
// Transition approach
public function __construct(
    Application $app,
    ?IUserRepository $repository = null  // Still optional
) {
    parent::__construct($app);

    // Fallback for old code
    if ($repository === null) {
        $repository = $app->getContainer()->get(IUserRepository::class);
    }
}
```

## Migration Checklist

For each controller:

- [ ] Identify all dependencies (Registry calls, new statements)
- [ ] Add constructor parameters with type hints
- [ ] Remove factory logic from constructor
- [ ] Verify bindings exist in service provider
- [ ] Update tests to inject mocks
- [ ] Remove unused imports (DatabaseRepository, Registry, etc.)
- [ ] Test that routes still work

## Benefits After Migration

### Before
- ❌ Hidden dependencies
- ❌ Hard to test (must mock Registry)
- ❌ Tight coupling
- ❌ Complex constructors
- ❌ Runtime errors if dependency missing

### After
- ✅ Explicit dependencies
- ✅ Easy to test (inject mocks)
- ✅ Loose coupling
- ✅ Simple constructors
- ✅ Compile-time checking

## Next Steps

1. **Start with simple controllers** - Migrate Pages, Calendar first
2. **Move to complex controllers** - Posts, Users, Events
3. **Remove Registry calls** - Search for `Registry::getInstance()`
4. **Update tests** - Use dependency injection
5. **Celebrate!** - Clean, testable code

## See Also

- [Dependency Injection Documentation](DEPENDENCY_INJECTION.md)
- [Architecture Recommendations](ARCHITECTURE_RECOMMENDATIONS.md)
- [Service Provider Guide](../src/Cms/Container/CmsServiceProvider.php)
