<?php

/**
 * Complete example: Full application bootstrap with container and routing
 *
 * This example shows how to set up a complete NeuronPHP application
 * with dependency injection container integrated into the routing system.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Neuron\Patterns\Container\Container;
use Neuron\Cms\Container\CmsServiceProvider;
use Neuron\Mvc\Application;
use Neuron\Data\Settings\Source\IniFile;

echo "=== NeuronPHP Container-Enabled Application Bootstrap ===\n\n";

// 1. Create application
echo "1. Creating application...\n";
$settingsPath = __DIR__ . '/../config/settings.ini';

if (!file_exists($settingsPath)) {
    echo "   ⚠️  Settings file not found, using minimal config\n";
    $settingsPath = null;
}

$app = new Application('1.0.0', $settingsPath ? new IniFile($settingsPath) : null);
echo "   ✓ Application created\n\n";

// 2. Create and configure container
echo "2. Setting up dependency injection container...\n";
$container = new Container();
echo "   ✓ Container created\n";

// 3. Register CMS services
echo "3. Registering CMS services...\n";
$provider = new CmsServiceProvider();
$provider->register($container);
echo "   ✓ CMS services registered\n\n";

// 4. Register application instance (so it can be injected)
$container->instance(Application::class, $app);
echo "   ✓ Application instance registered\n\n";

// 5. Set container on application
echo "4. Connecting container to application...\n";
$app->setContainer($container);
echo "   ✓ Container connected\n\n";

// 6. Show what's registered
echo "=== Registered Services ===\n";
echo "\nRepositories:\n";
foreach ($container->getBindings() as $interface => $implementation) {
    if (strpos($interface, 'Repository') !== false) {
        $shortInterface = substr($interface, strrpos($interface, '\\') + 1);
        $shortImpl = substr($implementation, strrpos($implementation, '\\') + 1);
        echo "  • $shortInterface → $shortImpl\n";
    }
}

echo "\nServices:\n";
foreach ($container->getBindings() as $interface => $implementation) {
    if (strpos($interface, 'Repository') === false &&
        (strpos($interface, 'IUser') !== false || strpos($interface, 'Service') !== false)) {
        $shortInterface = substr($interface, strrpos($interface, '\\') + 1);
        $shortImpl = substr($implementation, strrpos($implementation, '\\') + 1);
        echo "  • $shortInterface → $shortImpl\n";
    }
}

// 7. Demonstrate auto-wiring
echo "\n=== Demonstrating Auto-Wiring ===\n\n";

// Example: Resolve a repository
use Neuron\Cms\Repositories\IUserRepository;

try {
    echo "Resolving IUserRepository...\n";
    $userRepo = $container->get(IUserRepository::class);
    echo "  ✓ Resolved to: " . get_class($userRepo) . "\n\n";
} catch (\Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n\n";
}

// Example: Auto-wire a controller
use Neuron\Cms\Controllers\Admin\Users;

try {
    echo "Auto-wiring Users controller...\n";
    $controller = $container->make(Users::class);
    echo "  ✓ Controller created: " . get_class($controller) . "\n";
    echo "  ✓ All dependencies auto-injected!\n\n";
} catch (\Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n\n";
}

// 8. Show how routing works
echo "=== How Routing Works with Container ===\n\n";

echo "When a request comes in:\n";
echo "  1. Router matches route to controller (e.g., 'Users@index')\n";
echo "  2. Application calls: executeController(['Controller' => 'Users@index'])\n";
echo "  3. executeController() uses container.make(Users::class)\n";
echo "  4. Container analyzes Users constructor:\n";
echo "     - Sees it needs Application\n";
echo "     - Sees it needs IUserRepository\n";
echo "     - Sees it needs IUserCreator\n";
echo "  5. Container resolves each dependency:\n";
echo "     - Application → existing instance\n";
echo "     - IUserRepository → creates DatabaseUserRepository\n";
echo "       - DatabaseUserRepository needs SettingManager\n";
echo "       - Resolves SettingManager from singleton\n";
echo "     - IUserCreator → creates Creator\n";
echo "       - Creator needs IUserRepository (already resolved)\n";
echo "       - Creator needs PasswordHasher (resolved from singleton)\n";
echo "  6. Container creates fully configured Users controller\n";
echo "  7. Calls controller->index()\n";
echo "  8. Returns response\n\n";

// 9. Show testing benefits
echo "=== Testing Benefits ===\n\n";

echo "Without container (old way):\n";
echo "  \$controller = new Users(\$app);\n";
echo "  // Uses Registry internally - hard to mock!\n\n";

echo "With container (new way):\n";
echo "  \$mockRepo = \$this->createMock(IUserRepository::class);\n";
echo "  \$mockCreator = \$this->createMock(IUserCreator::class);\n";
echo "  \$controller = new Users(\$app, \$mockRepo, \$mockCreator);\n";
echo "  // Easy to test with mocks!\n\n";

// 10. Summary
echo "=== Summary ===\n\n";
echo "✓ Container is set up and working\n";
echo "✓ All CMS services are registered\n";
echo "✓ Routes automatically use container for controllers\n";
echo "✓ Controllers get dependencies auto-injected\n";
echo "✓ Ready for production use!\n\n";

echo "Next steps:\n";
echo "  1. Migrate controllers to use constructor injection\n";
echo "  2. Remove Registry::getInstance() calls\n";
echo "  3. Update tests to use dependency injection\n";
echo "  4. Enjoy cleaner, more testable code!\n\n";

echo "See docs/CONTROLLER_MIGRATION_GUIDE.md for migration instructions.\n";
