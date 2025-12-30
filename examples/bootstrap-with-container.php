<?php

/**
 * Example: Bootstrapping application with dependency injection container
 *
 * This example shows how to set up and use the PSR-11 container
 * for dependency injection in a NeuronPHP CMS application.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Neuron\Patterns\Container\Container;
use Neuron\Cms\Container\CmsServiceProvider;
use Neuron\Mvc\Application;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\IniFile;

// Create application
$settingsSource = new IniFile(__DIR__ . '/../config/settings.ini');
$app = new Application('1.0.0', $settingsSource);

// Create and configure container
$container = new Container();

// Register CMS services
$provider = new CmsServiceProvider();
$provider->register($container);

// Register application instance (so it can be injected)
$container->instance(Application::class, $app);

// Set container on application
$app->setContainer($container);

// Example: Manually resolve a service
use Neuron\Cms\Repositories\IUserRepository;

$userRepository = $container->get(IUserRepository::class);
echo "User repository class: " . get_class($userRepository) . "\n";

// Example: Auto-wire a controller
use Neuron\Cms\Controllers\Admin\Users;

try {
    $controller = $container->make(Users::class);
    echo "Controller created with auto-wired dependencies!\n";
    echo "Controller class: " . get_class($controller) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Example: Check what's registered
echo "\nRegistered bindings:\n";
foreach ($container->getBindings() as $abstract => $concrete) {
    echo "  $abstract => $concrete\n";
}

// Example: Testing with mocks
echo "\n--- Testing Example ---\n";

// Create mock repository
class MockUserRepository implements IUserRepository {
    public function all(): array { return []; }
    public function findById(int $id) { return null; }
    public function findByUsername(string $username) { return null; }
    public function findByEmail(string $email) { return null; }
    public function create($user) { return $user; }
    public function update($user): bool { return true; }
    public function delete(int $id): bool { return true; }
    public function exists(int $id): bool { return false; }
}

// Override binding for testing
$container->bind(IUserRepository::class, MockUserRepository::class);

$testRepo = $container->get(IUserRepository::class);
echo "Test repository class: " . get_class($testRepo) . "\n";

echo "\n✓ Container configured successfully!\n";
