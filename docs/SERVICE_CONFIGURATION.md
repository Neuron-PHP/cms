# Service Configuration with YAML

The NeuronPHP CMS uses YAML-based service configuration for the dependency injection container. This approach provides clean separation between configuration and code, making services easier to maintain, override, and audit.

## Quick Start

### Basic Service Configuration

Services are defined in `resources/config/services.yaml`:

```yaml
services:
  # Simple autowired service
  Neuron\Cms\Services\SlugGenerator:
    type: autowire

  # Service with constructor parameters
  Neuron\Cms\Repositories\DatabaseUserRepository:
    type: create
    constructor:
      - '@Neuron\Data\Settings\SettingManager'

  # Interface binding (alias)
  Neuron\Cms\Repositories\IUserRepository:
    type: alias
    target: Neuron\Cms\Repositories\DatabaseUserRepository

  # Factory-based service
  Neuron\Cms\Auth\SessionManager:
    type: factory
    factory_class: Neuron\Cms\Container\Factories\SessionManagerFactory
```

## Definition Types

### `autowire` - Automatic Dependency Resolution

The container automatically resolves constructor dependencies using reflection.

```yaml
Neuron\Cms\Services\Content\EditorJsRenderer:
  type: autowire
```

**Use when:**
- Service has simple dependencies
- All dependencies are type-hinted
- No complex configuration needed

---

### `create` - Explicit Constructor Parameters

Explicitly specify constructor parameters, useful when you need to customize instantiation.

```yaml
Neuron\Cms\Services\Auth\Authentication:
  type: create
  constructor:
    - '@Neuron\Cms\Repositories\DatabaseUserRepository'
    - '@Neuron\Cms\Auth\SessionManager'
    - '@Neuron\Cms\Auth\PasswordHasher'
```

**Parameters:**
- Use `@ServiceName` to reference other services
- Use plain values for scalars (strings, numbers, booleans)

**Use when:**
- You need explicit control over constructor parameters
- Order matters and autowiring might be ambiguous

---

### `factory` - Factory Class Pattern

Use a factory class to create the service. Ideal for complex initialization logic.

```yaml
Neuron\Cms\Auth\PasswordHasher:
  type: factory
  factory_class: Neuron\Cms\Container\Factories\PasswordHasherFactory
```

**Factory class example:**

```php
<?php
namespace Neuron\Cms\Container\Factories;

use Psr\Container\ContainerInterface;

class PasswordHasherFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $settings = $container->get(SettingManager::class);
        $hasher = new PasswordHasher();

        $minLength = $settings->get('password', 'min_length');
        if ($minLength) {
            $hasher->setMinLength((int)$minLength);
        }

        return $hasher;
    }
}
```

**Use when:**
- Service requires complex initialization
- Configuration needs to be read and applied
- Multiple conditional setup steps are needed
- You want to keep business logic out of YAML

---

### `alias` - Interface Bindings

Bind an interface to a concrete implementation.

```yaml
Neuron\Cms\Repositories\IUserRepository:
  type: alias
  target: Neuron\Cms\Repositories\DatabaseUserRepository
```

**Shorthand syntax:**

```yaml
Neuron\Cms\Repositories\IUserRepository: Neuron\Cms\Repositories\DatabaseUserRepository
```

**Use when:**
- Binding interfaces to implementations
- Creating service aliases
- Enabling dependency inversion

---

### `instance` - Runtime Instances

Mark services that will be set at runtime (not loaded from YAML).

```yaml
Neuron\Data\Settings\SettingManager:
  type: instance
  source: registry
```

These services are typically registered in code:

```php
$container->instance(SettingManager::class, $settingsInstance);
```

**Use when:**
- Service is created outside the container
- Instance comes from Registry or other sources

---

### `value` - Simple Values

Store scalar values or configuration.

```yaml
app.version:
  type: value
  value: "1.0.0"

app.debug:
  type: value
  value: false
```

## Environment-Specific Configuration

### Override Services Per Environment

Create environment-specific YAML files:

```
resources/config/
├── services.yaml              # Base configuration
├── services.testing.yaml      # Testing overrides
└── services.production.yaml   # Production overrides
```

**Example: services.testing.yaml**

```yaml
services:
  # Use in-memory cache for testing
  Neuron\Cache\ICacheDriver:
    type: alias
    target: Neuron\Cache\ArrayCache

  # Mock external APIs
  Neuron\Cms\Services\Media\CloudinaryUploader:
    type: alias
    target: Neuron\Cms\Services\Media\MockUploader
```

### Load Environment Configuration

```php
$environment = getenv('APP_ENV') ?: 'production';
$container = Container::build($settings, $environment);
```

## Adding New Services

### Step 1: Add to services.yaml

```yaml
services:
  Neuron\Cms\Services\MyNewService:
    type: autowire
```

### Step 2: Create Service Class

```php
<?php
namespace Neuron\Cms\Services;

class MyNewService
{
    private IUserRepository $_repository;

    public function __construct(IUserRepository $repository)
    {
        $this->_repository = $repository;
    }
}
```

### Step 3: Use in Controllers

```php
class MyController extends Content
{
    public function __construct(
        Application $app,
        MyNewService $myService
    ) {
        parent::__construct($app);
        $this->_myService = $myService;
    }
}
```

The container automatically resolves all dependencies!

## Complex Service Example

### Factory-Based Service with Configuration

**YAML Configuration:**

```yaml
Neuron\Cms\Services\Auth\EmailVerifier:
  type: factory
  factory_class: Neuron\Cms\Container\Factories\EmailVerifierFactory
```

**Factory Class:**

```php
<?php
namespace Neuron\Cms\Container\Factories;

use Psr\Container\ContainerInterface;

class EmailVerifierFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $tokenRepo = $container->get(IEmailVerificationTokenRepository::class);
        $userRepo = $container->get(IUserRepository::class);
        $settings = $container->get(SettingManager::class);

        // Get configuration
        $basePath = Registry::getInstance()->get('Base.Path') ?? getcwd();
        $siteUrl = $settings->get('site', 'url') ?? 'http://localhost';
        $verificationUrl = rtrim($siteUrl, '/') . '/verify';

        return new EmailVerifier(
            $tokenRepo,
            $userRepo,
            $settings,
            $basePath,
            $verificationUrl
        );
    }
}
```

## Benefits

### ✅ Clean Separation
- Configuration in YAML
- Business logic in code
- Easy to review all registered services

### ✅ Maintainability
- Services organized by category
- Easy to find and modify
- Clear dependencies

### ✅ Environment Flexibility
- Override services per environment
- Different implementations for testing
- Easy A/B testing

### ✅ Framework Consistency
- Matches existing YAML configuration pattern
- Familiar to Neuron developers
- Consistent with routing, events, etc.

## Migration from Hardcoded Container

### Before (Container.php - 296 lines)

```php
$builder->addDefinitions([
    PasswordHasher::class => \DI\factory(function() use ($settings) {
        $hasher = new PasswordHasher();
        try {
            $minLength = $settings->get('password', 'min_length');
            if ($minLength) {
                $hasher->setMinLength((int)$minLength);
            }
        } catch (\Exception $e) {
            // Use defaults
        }
        return $hasher;
    }),
    // ... 200+ more lines ...
]);
```

### After (services.yaml + Factory)

**services.yaml:**
```yaml
Neuron\Cms\Auth\PasswordHasher:
  type: factory
  factory_class: Neuron\Cms\Container\Factories\PasswordHasherFactory
```

**PasswordHasherFactory.php:**
```php
public function __invoke(ContainerInterface $container)
{
    $settings = $container->get(SettingManager::class);
    $hasher = new PasswordHasher();

    $minLength = $settings->get('password', 'min_length');
    if ($minLength) {
        $hasher->setMinLength((int)$minLength);
    }

    return $hasher;
}
```

**Result:** Container.php reduced from 296 lines to 88 lines (70% reduction)!

## Troubleshooting

### "Service configuration file not found"

**Cause:** services.yaml doesn't exist or is in wrong location

**Solution:**
```bash
# Check file exists
ls resources/config/services.yaml

# Verify path in Container.php
$configPath = __DIR__ . '/../../../resources/config';
```

### "Unknown service definition type"

**Cause:** Invalid type specified in YAML

**Solution:** Use only supported types:
- `autowire`
- `create`
- `factory`
- `alias`
- `instance`
- `value`

### "Factory definition must specify factory_class"

**Cause:** Factory type missing factory_class parameter

**Solution:**
```yaml
# ❌ Wrong
MyService:
  type: factory

# ✅ Correct
MyService:
  type: factory
  factory_class: My\FactoryClass
```

### "Cannot resolve dependency"

**Cause:** Missing service binding or circular dependency

**Solution:**
1. Check service is registered in services.yaml
2. Verify interface bindings exist
3. Check for circular dependencies (A depends on B, B depends on A)

## Best Practices

1. **Use autowire by default** - Let the container do the work
2. **Create factories for complex services** - Keep configuration logic in code
3. **Bind interfaces, not implementations** - Enable dependency inversion
4. **Group related services** - Use YAML comments to organize
5. **Document complex bindings** - Add comments explaining why
6. **Test with environment overrides** - Ensure production config works

## Related Documentation

- [Dependency Injection Container](DEPENDENCY_INJECTION.md)
- [Controller Migration Guide](CONTROLLER_MIGRATION_GUIDE.md)
- [Container Architecture](../src/Cms/Container/README.md)

## Examples

See complete working examples in:
- `resources/config/services.yaml` - Full CMS service configuration
- `src/Cms/Container/Factories/` - Example factory classes
- `examples/container-bootstrap-full.php` - Complete bootstrap example
