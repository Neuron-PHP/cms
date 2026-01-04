# Dependency Injection Container

The NeuronPHP framework includes a PSR-11 compatible dependency injection container for managing dependencies and promoting loose coupling.

## Service Configuration

**NEW:** Services are now configured via YAML files for better maintainability and separation of concerns.

See [Service Configuration Guide](SERVICE_CONFIGURATION.md) for complete documentation on YAML-based service configuration.

## Quick Start

### 1. Bootstrap the Container

The container is automatically bootstrapped in `src/Bootstrap.php`:

```php
<?php

use Neuron\Cms\Container\Container;

// Container automatically loads services from resources/config/services.yaml
$container = Container::build($settings, $environment);

// Container is automatically set on the application
$app->setContainer($container);
```

**That's it!** All services defined in `services.yaml` are available for injection.

### 2. Alternative: Manual Bootstrap (Legacy)

For backward compatibility, you can still use the service provider approach:

```php
<?php

use Neuron\Patterns\Container\Container;
use Neuron\Cms\Container\CmsServiceProvider;

// Create container
$container = new Container();

// Register CMS services
$provider = new CmsServiceProvider();
$provider->register($container);

// Set container on application
$app->setContainer($container);
```

**Note:** This approach is deprecated in favor of YAML configuration.

### 2. Use Dependency Injection in Controllers

**Before (Service Locator Anti-Pattern):**

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
        }

        $this->_repository = $repository;
    }
}
```

**After (Dependency Injection):**

```php
class Users extends Content
{
    private IUserRepository $_repository;
    private IUserCreator $_userCreator;

    public function __construct(
        Application $app,
        IUserRepository $repository,     // Auto-injected
        IUserCreator $userCreator        // Auto-injected
    ) {
        parent::__construct($app);
        $this->_repository = $repository;
        $this->_userCreator = $userCreator;
    }
}
```

The container automatically:
1. Sees `Users` needs `IUserRepository`
2. Looks up binding: `IUserRepository` → `DatabaseUserRepository`
3. Sees `DatabaseUserRepository` needs `SettingManager`
4. Resolves `SettingManager` from container
5. Creates fully configured instance

## Container Interface

### Core Methods

```php
// Get instance from container
$repository = $container->get(IUserRepository::class);

// Check if container can resolve
if ($container->has(IUserRepository::class)) {
    // ...
}

// Make new instance with auto-wiring
$controller = $container->make(UsersController::class);
```

### Registration Methods

```php
// Bind interface to implementation
$container->bind(IUserRepository::class, DatabaseUserRepository::class);

// Register singleton (shared instance)
$container->singleton(PasswordHasher::class, function($c) {
    return new PasswordHasher();
});

// Register existing instance
$container->instance(SettingManager::class, $settingManager);
```

## Service Provider

Service providers organize related bindings:

```php
<?php

namespace Neuron\Cms\Container;

use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Container\IServiceProvider;

class CmsServiceProvider implements IServiceProvider
{
    public function register(IContainer $container): void
    {
        // Repositories
        $container->bind(IUserRepository::class, DatabaseUserRepository::class);
        $container->bind(IPostRepository::class, DatabasePostRepository::class);

        // Services
        $container->bind(IUserCreator::class, Creator::class);

        // Singletons
        $container->singleton(PasswordHasher::class, function($c) {
            return new PasswordHasher();
        });
    }
}
```

## Auto-Wiring

The container uses reflection to automatically resolve dependencies:

```php
class UserController
{
    public function __construct(
        Application $app,              // Container resolves
        IUserRepository $repository,   // Container resolves
        IUserCreator $creator          // Container resolves
    ) {
        // All dependencies injected automatically!
    }
}

// Just call make()
$controller = $container->make(UserController::class);
```

## Benefits

### 1. Explicit Dependencies
All dependencies visible in constructor signature.

### 2. Easy Testing
Mock dependencies easily:

```php
$mockRepo = $this->createMock(IUserRepository::class);
$controller = new Users($app, $mockRepo, $mockCreator);
```

### 3. Swappable Implementations

```php
// Switch to Redis implementation
$container->bind(IUserRepository::class, RedisUserRepository::class);
```

### 4. Single Responsibility
Controllers focus on their logic, not creating dependencies.

## Migration Guide

### Step 1: Register Services

Create or update your service provider to register all services.

### Step 2: Update Controller Constructors

Remove null defaults and factory logic:

```php
// Before
public function __construct(?Application $app = null, ?IUserRepository $repo = null)
{
    parent::__construct($app);
    if ($repo === null) {
        // Factory logic...
    }
}

// After
public function __construct(Application $app, IUserRepository $repo)
{
    parent::__construct($app);
    $this->_repository = $repo;
}
```

### Step 3: Update Tests

Use dependency injection in tests:

```php
// Before
$controller = new Users($app); // Uses Registry internally

// After
$mockRepo = $this->createMock(IUserRepository::class);
$controller = new Users($app, $mockRepo);
```

## Advanced Usage

### Contextual Binding

```php
// Different implementation for different contexts
$container->bind(ILogger::class, FileLogger::class);

// Override for specific class
$container->when(ApiController::class)
    ->needs(ILogger::class)
    ->give(CloudLogger::class);
```

### Method Injection

```php
class UserService
{
    public function updateUser(IUserRepository $repo, int $userId)
    {
        // $repo auto-injected when calling via container
    }
}

$container->call([$service, 'updateUser'], ['userId' => 123]);
```

### Tagged Services

```php
// Tag services
$container->tag([LogFileHandler::class, LogEmailHandler::class], 'log.handlers');

// Resolve all tagged
$handlers = $container->tagged('log.handlers');
```

## Best Practices

1. **Always type-hint dependencies** - Enables auto-wiring
2. **Depend on interfaces, not implementations** - Loose coupling
3. **Use singletons sparingly** - Only for stateless services
4. **Avoid service locator pattern** - Don't inject container itself
5. **Keep constructors simple** - Just assign dependencies

## Troubleshooting

### "Entry 'X' not found in container"

The binding wasn't registered. Check your service provider.

### "Class is not instantiable"

You're trying to auto-wire an interface or abstract class. Add a binding:

```php
$container->bind(InterfaceName::class, ConcreteName::class);
```

### "Cannot resolve primitive parameter"

Container can't auto-wire primitives (strings, ints, etc.). Use parameters:

```php
$container->make(SomeClass::class, ['configPath' => '/path/to/config']);
```

## Examples

See `/Users/lee/projects/personal/neuron/cms/examples/bootstrap-with-container.php` for complete example.

## Related Documentation

- [Architecture Recommendations](ARCHITECTURE_RECOMMENDATIONS.md)
- [Service Layer Patterns](SERVICE_LAYER.md)
- [Repository Pattern](REPOSITORY_PATTERN.md)
