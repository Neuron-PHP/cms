<?php

require_once __DIR__ . '/vendor/autoload.php';

use Neuron\Cms\Maintenance\MaintenanceManager;

$basePath = getcwd();
$manager = new MaintenanceManager($basePath);

echo "Maintenance Status Check\n";
echo "========================\n\n";

echo "Base Path: $basePath\n";
echo "Maintenance File: " . $basePath . "/.maintenance.json\n";
echo "File Exists: " . (file_exists($basePath . '/.maintenance.json') ? 'Yes' : 'No') . "\n\n";

if (file_exists($basePath . '/.maintenance.json')) {
    echo "File Contents:\n";
    echo file_get_contents($basePath . '/.maintenance.json') . "\n\n";
}

echo "Is Enabled: " . ($manager->isEnabled() ? 'Yes' : 'No') . "\n";
echo "Message: " . $manager->getMessage() . "\n";
echo "Retry After: " . $manager->getRetryAfter() . "\n\n";

$status = $manager->getStatus();
echo "Full Status:\n";
print_r($status);
