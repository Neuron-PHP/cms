# Maintenance Mode

The CMS component includes a comprehensive maintenance mode system that allows you to temporarily disable public access to your site while performing updates, backups, or other maintenance tasks.

## Features

- **Simple CLI Commands**: Easy enable/disable/status commands
- **IP Whitelisting**: Allow specific IP addresses or CIDR ranges to bypass maintenance mode
- **Custom Messages**: Display custom maintenance messages to visitors
- **HTTP 503 Status**: Properly returns HTTP 503 Service Unavailable status
- **Retry-After Header**: SEO-friendly header tells search engines when to check back
- **Custom Views**: Support for custom maintenance page templates
- **File-Based Storage**: No database required - uses `.maintenance.json` file

## Quick Start

### Enable Maintenance Mode

```bash
# Basic usage
neuron cms:maintenance:enable

# With custom message
neuron cms:maintenance:enable --message="Upgrading database, back in 30 minutes"

# Allow specific IPs
neuron cms:maintenance:enable --allow-ip="192.168.1.100,10.0.0.0/8"

# Set estimated downtime (in seconds)
neuron cms:maintenance:enable --retry-after=1800

# Skip confirmation
neuron cms:maintenance:enable --force
```

### Disable Maintenance Mode

```bash
# Basic usage
neuron cms:maintenance:disable

# Skip confirmation
neuron cms:maintenance:disable --force
```

### Check Status

```bash
# Human-readable format
neuron cms:maintenance:status

# JSON format
neuron cms:maintenance:status --json
```

## Configuration

Add maintenance mode settings to your `config/neuron.yaml`:

```yaml
maintenance:
  default_message: "Site is currently under maintenance. We'll be back soon!"
  allowed_ips:
    - 127.0.0.1
    - ::1
    - 192.168.1.0/24  # Allow entire subnet
  retry_after: 3600   # 1 hour in seconds
  custom_view: null   # Path to custom maintenance view
```

## Integration with Application

To activate the maintenance mode filter in your application, you need to register it with the router. Here's an example:

```php
<?php
use Neuron\Cms\Maintenance\MaintenanceManager;
use Neuron\Cms\Maintenance\MaintenanceFilter;
use Neuron\Cms\Maintenance\MaintenanceConfig;

// In your Application bootstrap or initialization
$basePath = __DIR__;
$manager = new MaintenanceManager($basePath);
$config = MaintenanceConfig::fromSettings($settingsSource);

// Create and register the filter
$filter = new MaintenanceFilter(
    $manager,
    $config->getCustomView()
);

$router->registerFilter('maintenance', $filter);
$router->addFilter('maintenance'); // Apply globally
```

## How It Works

1. **File-Based State**: Maintenance mode state is stored in `.maintenance.json` at the application root
2. **Route Filter**: A router filter intercepts all requests before they reach controllers
3. **IP Checking**: The filter checks if the requesting IP is in the allowed list
4. **HTTP Response**: Returns HTTP 503 with maintenance page for non-allowed IPs

## Storage Format

The `.maintenance.json` file contains:

```json
{
  "enabled": true,
  "message": "Site under maintenance",
  "allowed_ips": ["127.0.0.1", "::1"],
  "retry_after": 3600,
  "enabled_at": "2024-01-15T10:30:00+00:00",
  "enabled_by": "admin"
}
```

## IP Whitelisting

### Single IP Address
```bash
neuron cms:maintenance:enable --allow-ip="192.168.1.100"
```

### Multiple IP Addresses
```bash
neuron cms:maintenance:enable --allow-ip="192.168.1.100,203.0.113.5"
```

### CIDR Notation (Subnet)
```bash
# Allow entire 192.168.1.0/24 subnet (192.168.1.0 - 192.168.1.255)
neuron cms:maintenance:enable --allow-ip="192.168.1.0/24"

# Allow private network ranges
neuron cms:maintenance:enable --allow-ip="10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"
```

## Custom Maintenance Page

You can create a custom maintenance page template:

1. Create your custom view file (e.g., `themes/my-theme/maintenance.php`):

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Mode</title>
</head>
<body>
    <h1>We'll be right back!</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <?php if ($retryAfter): ?>
        <p>Estimated time: <?= ceil($retryAfter / 60) ?> minutes</p>
    <?php endif; ?>
</body>
</html>
```

2. Configure it in `neuron.yaml`:

```yaml
maintenance:
  custom_view: themes/my-theme/maintenance.php
```

Or specify it when creating the filter:

```php
$filter = new MaintenanceFilter($manager, 'path/to/custom/view.php');
```

## SEO Considerations

The maintenance mode system is SEO-friendly:

- Returns **HTTP 503 Service Unavailable** status (not 404 or 500)
- Includes **Retry-After** header to inform search engines when to return
- Prevents search engines from deindexing your site during temporary maintenance

## Security

- Localhost (127.0.0.1 and ::1) is allowed by default
- IP whitelist supports both exact matches and CIDR notation
- Handles proxied requests (checks X-Forwarded-For, X-Real-IP headers)
- Validates IP addresses before checking whitelist

## Use Cases

### Scheduled Maintenance
```bash
# Before maintenance window
neuron cms:maintenance:enable \
  --message="Scheduled maintenance: 2am-4am EST" \
  --retry-after=7200 \
  --allow-ip="192.168.1.0/24"

# After maintenance
neuron cms:maintenance:disable
```

### Database Migrations
```bash
neuron cms:maintenance:enable \
  --message="Updating database schema" \
  --retry-after=600

# Run migrations
php artisan migrate

# Disable maintenance
neuron cms:maintenance:disable --force
```

### Deployment Process
```bash
#!/bin/bash
# deploy.sh

# Enable maintenance
neuron cms:maintenance:enable --force --retry-after=300

# Pull latest code
git pull origin main

# Update dependencies
composer install --no-dev

# Clear caches
neuron cms:cache:clear

# Disable maintenance
neuron cms:maintenance:disable --force
```

## Troubleshooting

### Maintenance mode won't enable
- Check write permissions on the application root directory
- Ensure `.maintenance.json` can be created

### Can't access site after disabling
- Verify the `.maintenance.json` file was deleted
- Check for cached responses (clear browser cache)

### IP whitelist not working
- Verify IP address format (use `cms:maintenance:status` to see current config)
- Check if behind a proxy (filter checks X-Forwarded-For header)
- Ensure CIDR notation is correct for subnets

## API Reference

### MaintenanceManager

```php
// Create manager
$manager = new MaintenanceManager($basePath);

// Enable maintenance mode
$manager->enable($message, $allowedIps, $retryAfter, $enabledBy);

// Disable maintenance mode
$manager->disable();

// Check if enabled
$isEnabled = $manager->isEnabled();

// Get current status
$status = $manager->getStatus();

// Check if IP is allowed
$isAllowed = $manager->isIpAllowed('192.168.1.100');

// Get message
$message = $manager->getMessage();

// Get retry-after value
$retryAfter = $manager->getRetryAfter();
```

### MaintenanceConfig

```php
// Create from settings
$config = MaintenanceConfig::fromSettings($settingsSource);

// Get configuration values
$message = $config->getDefaultMessage();
$ips = $config->getAllowedIps();
$retry = $config->getRetryAfter();
$view = $config->getCustomView();
```

### MaintenanceFilter

```php
// Create filter
$filter = new MaintenanceFilter($manager, $customViewPath);

// Register with router
$router->registerFilter('maintenance', $filter);
$router->addFilter('maintenance');
```

## License

MIT License - Part of the Neuron-PHP framework.
