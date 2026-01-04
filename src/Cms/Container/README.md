# Container Architecture

This directory contains the dependency injection container implementation for Neuron CMS.

## Architecture Overview

```
Container System
├── Container.php              - Main container builder (loads YAML config)
├── ContainerAdapter.php       - Adapts PHP-DI to Neuron's IContainer interface
├── YamlDefinitionLoader.php   - Parses YAML service definitions
├── CmsServiceProvider.php     - Legacy service provider (deprecated)
└── Factories/                 - Factory classes for complex services
    ├── SessionManagerFactory.php
    ├── PasswordHasherFactory.php
    ├── EmailVerifierFactory.php
    ├── PasswordResetterFactory.php
    └── RegistrationServiceFactory.php
```

## How It Works

### 1. YAML Configuration Loading

**File:** `resources/config/services.yaml`

Services are defined in YAML format:

```yaml
services:
  Neuron\Cms\Auth\PasswordHasher:
    type: factory
    factory_class: Neuron\Cms\Container\Factories\PasswordHasherFactory
```

### 2. Container Building

**File:** `Container.php` (88 lines)

```php
public static function build(SettingManager $settings, ?string $environment = null): IContainer
{
    // 1. Create PHP-DI ContainerBuilder
    $builder = new ContainerBuilder();

    // 2. Load YAML definitions
    $loader = new YamlDefinitionLoader($configPath, $environment);
    $definitions = $loader->load();

    // 3. Add definitions to builder
    $builder->addDefinitions($definitions);

    // 4. Build PSR-11 container
    $psr11Container = $builder->build();

    // 5. Wrap with Neuron adapter
    return new ContainerAdapter($psr11Container);
}
```

### 3. YAML to PHP-DI Conversion

**File:** `YamlDefinitionLoader.php`

The loader converts YAML service definitions to PHP-DI format:

```yaml
# YAML Input
Neuron\Cms\Services\User\Creator:
  type: autowire
```

```php
// PHP-DI Output
[
    'Neuron\Cms\Services\User\Creator' => \DI\autowire(Creator::class)
]
```

### 4. Factory Pattern for Complex Services

**Files:** `Factories/*.php`

Complex services use factory classes:

```php
class PasswordHasherFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $settings = $container->get(SettingManager::class);
        $hasher = new PasswordHasher();

        // Apply configuration
        $minLength = $settings->get('password', 'min_length');
        if ($minLength) {
            $hasher->setMinLength((int)$minLength);
        }

        return $hasher;
    }
}
```

### 5. Adapter Pattern

**File:** `ContainerAdapter.php`

Adapts PHP-DI's PSR-11 container to Neuron's `IContainer` interface:

```php
class ContainerAdapter implements IContainer
{
    private ContainerInterface $container; // PHP-DI

    public function get(string $id) {
        return $this->container->get($id);
    }

    public function bind(string $abstract, string $concrete): void {
        $this->container->set($abstract, \DI\autowire($concrete));
    }

    // ... other IContainer methods
}
```

## Design Decisions

### Why YAML Configuration?

**Before:** 296 lines of hardcoded PHP array definitions in `Container.php`

**After:** 88 lines of clean bootstrap code + organized YAML file

**Benefits:**
1. **Separation of concerns** - Configuration in YAML, logic in code
2. **Framework consistency** - Matches existing YAML config pattern
3. **Maintainability** - Easy to find and modify services
4. **Environment support** - Override services per environment
5. **Readability** - Clear service structure and dependencies

### Why PHP-DI?

1. **Feature-rich** - Advanced autowiring, compilation, caching
2. **PSR-11 compliant** - Standard container interface
3. **Performance** - Compiled containers for production
4. **Ecosystem** - Well-maintained, active community
5. **Laravel-style** - Similar to Laravel's service container

### Why Factory Classes?

Complex services need initialization logic that doesn't belong in YAML:

```php
// ❌ Bad: Complex logic in YAML would be messy
// ✅ Good: Clean YAML + dedicated factory class

Neuron\Cms\Services\Auth\EmailVerifier:
  type: factory
  factory_class: Neuron\Cms\Container\Factories\EmailVerifierFactory
```

Factory benefits:
- Testable initialization logic
- Type safety and IDE support
- Reusable across environments
- Clear separation of concerns

## Container Lifecycle

```
1. Bootstrap (src/Bootstrap.php)
   └─> Container::build($settings, $environment)

2. Load YAML Definitions
   └─> YamlDefinitionLoader->load()
       ├─> Parse resources/config/services.yaml
       └─> Parse resources/config/services.{environment}.yaml (if exists)

3. Convert to PHP-DI Format
   └─> convertToPhpDiDefinitions()
       ├─> autowire → \DI\autowire()
       ├─> create   → \DI\create()->constructor()
       ├─> factory  → \DI\factory(Factory::class)
       └─> alias    → \DI\get(Target::class)

4. Build Container
   └─> ContainerBuilder->build()
       └─> Creates PSR-11 container

5. Wrap with Adapter
   └─> new ContainerAdapter($psr11Container)
       └─> Implements Neuron\Patterns\Container\IContainer

6. Register in Registry
   └─> Registry::getInstance()->set('Container', $container)

7. Set on Application
   └─> $app->setContainer($container)

8. Ready for Dependency Injection!
   └─> Controllers auto-wired via MVC router
```

## Service Categories

Services are organized in `services.yaml` by category:

### Core Services (3)
- SlugGenerator
- SessionManager
- PasswordHasher

### Repositories (18 - 9 implementations + 9 interface bindings)
- User, Post, Page, Category, Tag, Event, EventCategory
- PasswordResetToken, EmailVerificationToken

### Authentication Services (10 - 5 implementations + 5 interface bindings)
- Authentication, CsrfToken
- EmailVerifier, PasswordResetter, RegistrationService

### CRUD Services (16 interface bindings)
- User: Creator, Updater, Deleter
- Post: Creator, Updater, Deleter
- Page: Creator, Updater
- Event: Creator, Updater
- EventCategory: Creator, Updater
- Category: Creator, Updater
- Tag: Creator

### Content Services (1)
- EditorJsRenderer

### Media Services (2)
- CloudinaryUploader
- MediaValidator

### Security Services (2)
- ResendVerificationThrottle
- IpResolver

**Total: 52 unique services, 97 total bindings**

## Adding New Services

### 1. Simple Service (Autowire)

```yaml
# resources/config/services.yaml
services:
  Neuron\Cms\Services\MyNewService:
    type: autowire
```

### 2. Service with Dependencies

```yaml
Neuron\Cms\Services\MyComplexService:
  type: create
  constructor:
    - '@Neuron\Cms\Repositories\IUserRepository'
    - '@Neuron\Data\Settings\SettingManager'
```

### 3. Service with Complex Initialization

**Step 1: Create factory**

```php
// src/Cms/Container/Factories/MyServiceFactory.php
class MyServiceFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $settings = $container->get(SettingManager::class);

        // Complex initialization logic
        $apiKey = $settings->get('api', 'key');
        $service = new MyService($apiKey);
        $service->configure($settings);

        return $service;
    }
}
```

**Step 2: Register in YAML**

```yaml
Neuron\Cms\Services\MyService:
  type: factory
  factory_class: Neuron\Cms\Container\Factories\MyServiceFactory
```

### 4. Interface Binding

```yaml
Neuron\Cms\Services\IMyService:
  type: alias
  target: Neuron\Cms\Services\MyServiceImplementation
```

## Environment-Specific Services

Override services per environment:

```yaml
# resources/config/services.testing.yaml
services:
  # Use mock uploader in tests
  Neuron\Cms\Services\Media\CloudinaryUploader:
    type: alias
    target: Neuron\Cms\Services\Media\MockUploader

  # Use in-memory cache
  Neuron\Cache\ICacheDriver:
    type: alias
    target: Neuron\Cache\ArrayCache
```

Load with environment:

```php
$container = Container::build($settings, 'testing');
```

## Migration from Hardcoded Container

### Before (Hardcoded - 296 lines)

```php
$builder->addDefinitions([
    SessionManager::class => \DI\factory(function() use ($settings) {
        $config = [];
        try {
            $lifetime = $settings->get('session', 'lifetime');
            if ($lifetime) {
                $config['lifetime'] = (int)$lifetime;
            }
        } catch (\Exception $e) {
            // Use defaults
        }
        return new SessionManager($config);
    }),
    // ... 250+ more lines ...
]);
```

### After (YAML + Factory - Clean!)

**services.yaml:**
```yaml
Neuron\Cms\Auth\SessionManager:
  type: factory
  factory_class: Neuron\Cms\Container\Factories\SessionManagerFactory
```

**SessionManagerFactory.php:**
```php
public function __invoke(ContainerInterface $container)
{
    $settings = $container->get(SettingManager::class);
    $config = [];

    $lifetime = $settings->get('session', 'lifetime');
    if ($lifetime) {
        $config['lifetime'] = (int)$lifetime;
    }

    return new SessionManager($config);
}
```

**Result:** 70% code reduction, better organization, easier to maintain!

## Testing

All services are tested for correct resolution:

```php
// Test service resolution
$container = Container::build($settings);
$service = $container->get(IUserRepository::class);
$this->assertInstanceOf(DatabaseUserRepository::class, $service);
```

Factory classes can be unit tested independently:

```php
$factory = new SessionManagerFactory();
$sessionManager = $factory($mockContainer);
$this->assertInstanceOf(SessionManager::class, $sessionManager);
```

## Performance Considerations

### Production Optimization

Enable container compilation for production:

```php
// Container.php
$builder->enableCompilation(__DIR__ . '/../../../var/cache/container');
```

Benefits:
- Faster container building (cached)
- Reduced memory usage
- No runtime definition parsing

### Development

Disable compilation for development:

```php
// Compilation disabled - definitions loaded from YAML each request
// Easier debugging and hot-reloading
```

## Related Documentation

- [Service Configuration Guide](../../../docs/SERVICE_CONFIGURATION.md)
- [Dependency Injection](../../../docs/DEPENDENCY_INJECTION.md)
- [Controller Migration Guide](../../../docs/CONTROLLER_MIGRATION_GUIDE.md)

## Summary

The YAML-based container architecture provides:

✅ **Clean code** - 70% reduction in Container.php
✅ **Maintainability** - Services organized in YAML
✅ **Flexibility** - Environment-specific overrides
✅ **Testability** - Factory classes are unit testable
✅ **Framework consistency** - Matches YAML config pattern
✅ **Laravel-style** - Familiar to modern PHP developers
✅ **Performance** - Compilation support for production
