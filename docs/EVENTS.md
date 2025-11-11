# Event System

The CMS uses an event-driven architecture to decouple business logic from side effects like sending emails and clearing caches.

## Available Events

### User Events
- **UserCreatedEvent** - Fired when a new user is created
- **UserUpdatedEvent** - Fired when a user is updated
- **UserDeletedEvent** - Fired when a user is deleted

### Post Events
- **PostCreatedEvent** - Fired when a new post is created
- **PostPublishedEvent** - Fired when a post is published
- **PostDeletedEvent** - Fired when a post is deleted

### Category Events
- **CategoryCreatedEvent** - Fired when a new category is created
- **CategoryUpdatedEvent** - Fired when a category is updated
- **CategoryDeletedEvent** - Fired when a category is deleted

## Built-in Listeners

### SendWelcomeEmailListener

Sends a professional HTML welcome email to newly registered users using the **editable template** at `resources/views/emails/welcome.php`.

**Configuration:**
```yaml
# resources/config/email.yaml
email:
  test_mode: false              # Set true to log instead of send
  driver: smtp                  # or 'mail', 'sendmail'
  from_address: noreply@yourdomain.com
  from_name: Your Site Name
  # SMTP settings (if driver is 'smtp')
  host: smtp.gmail.com
  port: 587
  username: your-email@gmail.com
  password: your-app-password
  encryption: tls
```

**Template Location:**
`resources/views/emails/welcome.php` - Fully editable PHP template

**Template Variables:**
- `$Username` - New user's username
- `$SiteName` - Site name from settings
- `$SiteUrl` - Site URL from settings

**Features:**
- **Editable template** - Customize design, colors, text without code changes
- **PHPMailer integration** - Professional email delivery with SMTP support
- **Test mode** - Logs emails during development instead of sending
- **Responsive design** - Works on desktop and mobile email clients
- **Inline CSS** - Styles work across all email clients
- **XSS protection** - All variables properly escaped

**Customization:**
Simply edit `resources/views/emails/welcome.php` to:
- Change gradient colors
- Add your logo
- Modify greeting message
- Update call-to-action button
- Customize footer
- Add social media links

See [EMAIL_TEMPLATES.md](EMAIL_TEMPLATES.md) for complete customization guide.

### ClearCacheListener

Automatically clears view cache when content changes to ensure users see fresh content.

**Triggers:**
- Post published
- Post deleted
- Category updated

**How it Works:**
1. Listener receives content change event
2. Retrieves ViewCache instance from Registry
3. Calls `ViewCache::clear()` to invalidate all cached views
4. Logs success/failure for debugging

**Requirements:**
- ViewCache must be registered in Registry as 'ViewCache'
- Cache storage must be configured (File or Redis)

### LogUserActivityListener

Logs all user activity for audit trail purposes.

**Logged Activities:**
- User created: "User created: username (ID: 1)"
- User updated: "User updated: username (ID: 1)"
- User deleted: "User deleted: ID 1"

**Log Level:** INFO

## Event Configuration

Events and their listeners are configured in `resources/config/event-listeners.yaml`:

```yaml
events:
  user.created:
    class: 'Neuron\Cms\Events\UserCreatedEvent'
    listeners:
      - 'Neuron\Cms\Listeners\SendWelcomeEmailListener'
      - 'Neuron\Cms\Listeners\LogUserActivityListener'

  user.updated:
    class: 'Neuron\Cms\Events\UserUpdatedEvent'
    listeners:
      - 'Neuron\Cms\Listeners\LogUserActivityListener'

  user.deleted:
    class: 'Neuron\Cms\Events\UserDeletedEvent'
    listeners:
      - 'Neuron\Cms\Listeners\LogUserActivityListener'

  post.published:
    class: 'Neuron\Cms\Events\PostPublishedEvent'
    listeners:
      - 'Neuron\Cms\Listeners\ClearCacheListener'

  post.deleted:
    class: 'Neuron\Cms\Events\PostDeletedEvent'
    listeners:
      - 'Neuron\Cms\Listeners\ClearCacheListener'

  category.updated:
    class: 'Neuron\Cms\Events\CategoryUpdatedEvent'
    listeners:
      - 'Neuron\Cms\Listeners\ClearCacheListener'
```

## Creating Custom Listeners

### Step 1: Create Listener Class

```php
<?php
namespace Neuron\Cms\Listeners;

use Neuron\Events\IListener;
use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Log\Log;

class MyCustomListener implements IListener
{
    public function event( $event ): void
    {
        if( !$event instanceof UserCreatedEvent )
        {
            return;
        }

        $user = $event->user;

        // Your custom logic here
        Log::info( "Custom action for user: {$user->getUsername()}" );
    }
}
```

### Step 2: Register in Configuration

Add your listener to `resources/config/event-listeners.yaml`:

```yaml
events:
  user.created:
    class: 'Neuron\Cms\Events\UserCreatedEvent'
    listeners:
      - 'Neuron\Cms\Listeners\SendWelcomeEmailListener'
      - 'Neuron\Cms\Listeners\LogUserActivityListener'
      - 'Neuron\Cms\Listeners\MyCustomListener'  # Your listener
```

## How Events are Emitted

Events are emitted from service classes, not controllers. This keeps controllers thin and business logic encapsulated.

**Example from User\Creator service:**

```php
public function create( /* ... */ ): User
{
    // Create user
    $user = new User();
    // ... set user properties
    $user = $this->_userRepository->create( $user );

    // Emit event
    $emitter = Registry::getInstance()->get( 'EventEmitter' );
    if( $emitter )
    {
        $emitter->emit( new UserCreatedEvent( $user ) );
    }

    return $user;
}
```

## Benefits of Event-Driven Architecture

1. **Decoupling** - Services don't need to know about emails, caching, logging, etc.
2. **Extensibility** - Add new listeners without modifying existing code
3. **Testability** - Easy to test services without side effects
4. **Flexibility** - Enable/disable features by adding/removing listeners
5. **Single Responsibility** - Each component does one thing well

## Debugging Events

Enable debug logging to see event flow:

```php
Log::setLevel( Log::DEBUG );
```

You'll see logs like:
```
[INFO] User created: testuser (ID: 5)
[DEBUG] Settings not available - welcome email skipped for: test@example.com
[INFO] Cache cleared successfully: Post published: My New Post
```

## Testing Listeners

Listeners can be tested in isolation:

```php
public function testSendWelcomeEmailListener(): void
{
    $user = new User();
    $user->setEmail( 'test@example.com' );

    $event = new UserCreatedEvent( $user );
    $listener = new SendWelcomeEmailListener();

    // Should not throw exception
    $listener->event( $event );

    $this->assertTrue( true );
}
```

## Performance Considerations

- Listeners run synchronously during the request
- Keep listener logic fast (< 100ms)
- For slow operations (external APIs), use a job queue instead
- Cache clearing is fast (< 10ms typically)
- Email sending uses PHP's mail() which is non-blocking

## Future Enhancements

Potential improvements to the event system:

1. **Async Listeners** - Queue slow listeners for background processing
2. **Event Priorities** - Control listener execution order
3. **Conditional Listeners** - Only fire based on conditions
4. **Event Middleware** - Transform events before listeners receive them
5. **Dead Letter Queue** - Capture failed listener executions
