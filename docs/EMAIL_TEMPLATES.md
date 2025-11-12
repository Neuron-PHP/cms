# Email Templates

The CMS uses a template-based email system powered by **PHPMailer** and the `Email\Sender` service. All email templates are editable PHP files located in `resources/views/emails/`.

## Quick Start

### 1. Configure Email Settings

Copy the example configuration:
```bash
cp resources/config/email.yaml.example resources/config/email.yaml
```

Edit `resources/config/email.yaml`:
```yaml
email:
  test_mode: true  # Enable for development
  driver: mail     # or 'smtp' for production
  from_address: noreply@yourdomain.com
  from_name: Your Site Name
```

### 2. Customize Email Templates

Templates are in `resources/views/emails/`. Edit them like any PHP file:

```php
// resources/views/emails/welcome.php
<p>Hi <?= htmlspecialchars($Username) ?>,</p>
<p>Welcome to <?= htmlspecialchars($SiteName) ?>!</p>
```

### 3. Test in Development

With `test_mode: true`, emails are logged instead of sent:
```
[INFO] TEST MODE - Email not sent
[INFO]   To: newuser@example.com
[INFO]   Subject: Welcome to My Site!
[INFO]   Body: Hi John, Welcome to My Site...
```

## Available Templates

### welcome.php
**Sent when**: A new user is created
**Triggered by**: `SendWelcomeEmailListener` (listens to `user.created` event)
**Variables available**:
- `$Username` - The new user's username
- `$SiteName` - Site name from settings
- `$SiteUrl` - Site URL from settings

**Customization ideas**:
- Add your logo (use absolute URL: `https://yoursite.com/logo.png`)
- Change gradient colors in header
- Modify greeting message
- Add onboarding steps
- Include social media links
- Add help/support contact info

### password-reset.php
**Sent when**: A user requests a password reset
**Triggered by**: `PasswordResetManager::requestReset()` (via forgot password form)
**Variables available**:
- `$ResetLink` - The password reset URL with token
- `$ExpirationMinutes` - Token expiration time in minutes (default: 60)
- `$SiteName` - Site name from settings

**Customization ideas**:
- Change gradient colors in header to match your brand
- Modify the security notice styling
- Add your logo
- Include support contact information
- Customize expiration warning message
- Add additional security tips

## Email Configuration

### Drivers

**mail** (default)
- Uses PHP's `mail()` function
- Requires server to have mail configured
- Works on most shared hosting
- May have deliverability issues

**sendmail**
- Uses sendmail binary
- Available on Linux/Mac servers
- Better than `mail()` for deliverability

**smtp** (recommended for production)
- Uses SMTP server (Gmail, SendGrid, Mailgun, etc.)
- Best deliverability
- Requires SMTP credentials

### SMTP Configuration Examples

#### Gmail
```yaml
email:
  driver: smtp
  host: smtp.gmail.com
  port: 587
  username: your-email@gmail.com
  password: your-app-password  # NOT your regular Gmail password!
  encryption: tls
  from_address: your-email@gmail.com
  from_name: Your Site Name
```

**Important**: Use an [App Password](https://support.google.com/accounts/answer/185833), not your regular password!

#### SendGrid
```yaml
email:
  driver: smtp
  host: smtp.sendgrid.net
  port: 587
  username: apikey
  password: SG.your-sendgrid-api-key-here
  encryption: tls
  from_address: noreply@yourdomain.com
  from_name: Your Site Name
```

#### Mailgun
```yaml
email:
  driver: smtp
  host: smtp.mailgun.org
  port: 587
  username: postmaster@yourdomain.com
  password: your-mailgun-smtp-password
  encryption: tls
  from_address: noreply@yourdomain.com
  from_name: Your Site Name
```

### Test Mode

Enable test mode during development to log emails instead of sending:

```yaml
email:
  test_mode: true
```

Emails will be logged with full details:
```
[INFO] TEST MODE - Email not sent
[INFO]   To: test@example.com
[INFO]   Subject: Welcome!
[INFO]   Body: Hi John, Welcome to...
```

## Customizing Templates

### Basic Customization

Templates are standard PHP files with variables. Edit them directly:

```php
// resources/views/emails/welcome.php

<!DOCTYPE html>
<html>
<head>
  <style>
    /* Change colors */
    .email-header {
      background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%);
    }
    .cta-button {
      background-color: #FF6B6B;
    }
  </style>
</head>
<body>
  <!-- Customize greeting -->
  <p>Hey <?= htmlspecialchars($Username) ?>! 👋</p>

  <!-- Customize message -->
  <p>Thanks for signing up! We're excited to have you.</p>

  <!-- Customize button text -->
  <a href="<?= htmlspecialchars($SiteUrl) ?>" class="cta-button">
    Get Started Now
  </a>
</body>
</html>
```

### Adding Images

Use absolute URLs for images (relative paths don't work in emails):

```php
<!-- ✅ Correct - absolute URL -->
<img src="https://yoursite.com/images/logo.png" alt="Logo">

<!-- ❌ Wrong - relative URL won't work -->
<img src="/images/logo.png" alt="Logo">
```

### Inline CSS

Always use inline CSS or `<style>` tags. External stylesheets don't work in most email clients:

```php
<!-- ✅ Correct - inline styles -->
<p style="color: #333; font-size: 16px;">Hello!</p>

<!-- ✅ Correct - style tag -->
<style>
  p { color: #333; font-size: 16px; }
</style>

<!-- ❌ Wrong - external stylesheet -->
<link rel="stylesheet" href="/css/email.css">
```

### Variables and Logic

Templates support full PHP:

```php
<!-- Conditionals -->
<?php if( $ShowBonus ): ?>
  <p>You've earned a bonus!</p>
<?php endif; ?>

<!-- Loops -->
<?php foreach( $Items as $item ): ?>
  <li><?= htmlspecialchars($item) ?></li>
<?php endforeach; ?>

<!-- Functions -->
<p>Year: <?= date('Y') ?></p>
<p>URL: <?= htmlspecialchars($SiteUrl) ?></p>
```

### Email-Safe HTML

Follow these best practices:

1. **Use tables for layout** (not divs) for better email client support
2. **Inline CSS** - Styles must be inline or in `<style>` tags
3. **Absolute URLs** - All links and images must be absolute
4. **Plain text fallback** - PHPMailer automatically creates this
5. **Test in multiple clients** - Gmail, Outlook, Apple Mail, etc.

## Creating New Email Templates

### 1. Create Template File

Create `resources/views/emails/your-template.php`:

```php
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; }
    .container { max-width: 600px; margin: 0 auto; }
  </style>
</head>
<body>
  <div class="container">
    <h1><?= htmlspecialchars($Subject) ?></h1>
    <p>Hi <?= htmlspecialchars($Username) ?>,</p>
    <p><?= htmlspecialchars($Message) ?></p>
  </div>
</body>
</html>
```

### 2. Send Email with Template

```php
use Neuron\Cms\Services\Email\Sender;

$sender = new Sender( $settings, $basePath );
$sender
  ->to( 'user@example.com', 'John Doe' )
  ->subject( 'Your Subject' )
  ->template( 'emails/your-template', [
    'Username' => 'John',
    'Subject' => 'Hello',
    'Message' => 'This is a test'
  ])
  ->send();
```

### 3. Create Listener (Optional)

To send automatically on events:

```php
namespace Neuron\Cms\Listeners;

use Neuron\Events\IListener;
use Neuron\Cms\Services\Email\Sender;

class YourEmailListener implements IListener
{
  public function event( $event ): void
  {
    $settings = Registry::getInstance()->get( 'Settings' );
    $basePath = Registry::getInstance()->get( 'Base.Path' );

    $sender = new Sender( $settings, $basePath );
    $sender
      ->to( $event->user->getEmail() )
      ->subject( 'Your Subject' )
      ->template( 'emails/your-template', [
        'Username' => $event->user->getUsername()
      ])
      ->send();
  }
}
```

Register in `resources/config/event-listeners.yaml`:
```yaml
events:
  your.event:
    class: 'Neuron\Cms\Events\YourEvent'
    listeners:
      - 'Neuron\Cms\Listeners\YourEmailListener'
```

## Advanced Features

### Multiple Recipients

```php
$sender
  ->to( 'user1@example.com', 'User One' )
  ->to( 'user2@example.com', 'User Two' )
  ->cc( 'manager@example.com' )
  ->bcc( 'admin@example.com' )
  ->send();
```

### Attachments

```php
$sender
  ->to( 'user@example.com' )
  ->subject( 'Invoice' )
  ->template( 'emails/invoice', $data )
  ->attach( '/path/to/invoice.pdf', 'Invoice.pdf' )
  ->send();
```

### Reply-To Address

```php
$sender
  ->to( 'user@example.com' )
  ->subject( 'Contact Form' )
  ->replyTo( 'support@yoursite.com', 'Support Team' )
  ->template( 'emails/contact-form', $data )
  ->send();
```

### Plain Text Body

```php
$sender
  ->to( 'user@example.com' )
  ->subject( 'Hello' )
  ->body( 'Plain text message', false )  // false = plain text
  ->send();
```

## Troubleshooting

### Email not sending?

1. **Check logs** - Look for error messages in log files
2. **Enable test mode** - Verify template renders correctly
3. **Check mail server** - Ensure `mail()` works: `php -r "mail('you@example.com', 'Test', 'Test');"`
4. **Try SMTP** - More reliable than `mail()`
5. **Check spam folder** - Emails might be filtered

### Template not found?

```
RuntimeException: Failed to render email template: emails/welcome
```

**Solutions**:
- Verify file exists at `resources/views/emails/welcome.php`
- Check file permissions (must be readable)
- Ensure correct base path in Registry

### Variables not showing?

Template receives: `$Username`, `$SiteName`, `$SiteUrl`

Check your template data array:
```php
$templateData = [
  'Username' => $user->getUsername(),  // ✅ PascalCase
  'SiteName' => $siteName,             // ✅ PascalCase
  'SiteUrl' => $siteUrl                // ✅ PascalCase
];
```

### SMTP authentication failed?

**Gmail**: Use App Password, not regular password
**SendGrid**: Username must be `apikey`
**Mailgun**: Use SMTP password, not API key

### Email goes to spam?

1. **Use SMTP** instead of `mail()`
2. **Configure SPF/DKIM** for your domain
3. **Warm up IP** if using dedicated IP
4. **Avoid spam trigger words** (FREE, ACT NOW, etc.)
5. **Test with** [Mail-Tester.com](https://www.mail-tester.com/)

## Email Client Testing

Test templates in popular email clients:

- **Gmail** (Desktop & Mobile)
- **Outlook** (Desktop & Web)
- **Apple Mail** (Mac & iOS)
- **Yahoo Mail**
- **Outlook.com / Hotmail**

Tools:
- [Litmus](https://litmus.com/) - Paid, comprehensive
- [Email on Acid](https://www.emailonacid.com/) - Paid
- [Mailtrap](https://mailtrap.io/) - Free tier for development

## Best Practices

1. **Keep it simple** - Complex layouts break in email clients
2. **Mobile first** - 60%+ of emails opened on mobile
3. **Inline CSS** - External styles don't work
4. **Absolute URLs** - For all images and links
5. **Test thoroughly** - Check multiple clients
6. **Use test mode** - During development
7. **Personalize** - Use recipient name
8. **Clear CTA** - One primary call-to-action
9. **Unsubscribe link** - For marketing emails
10. **Monitor deliverability** - Track bounce rates

## Security

### XSS Prevention

**Always escape variables**:
```php
<!-- ✅ Safe -->
<p><?= htmlspecialchars($Username) ?></p>

<!-- ❌ Dangerous - XSS vulnerability -->
<p><?= $Username ?></p>
```

### Template Injection

Templates are PHP files. **Never** load user-supplied template names:
```php
// ❌ Dangerous - template injection
$sender->template( $_POST['template'], $data );

// ✅ Safe - whitelist templates
$templates = ['welcome', 'password-reset'];
if( in_array( $_POST['template'], $templates ) ) {
  $sender->template( "emails/{$_POST['template']}", $data );
}
```

## Examples

### Welcome Email with Logo

```php
<!DOCTYPE html>
<html>
<body>
  <div style="text-align: center; padding: 20px;">
    <img src="https://yoursite.com/logo.png" alt="Logo" width="150">
    <h1>Welcome, <?= htmlspecialchars($Username) ?>!</h1>
    <p>Thanks for joining <?= htmlspecialchars($SiteName) ?>.</p>
    <a href="<?= htmlspecialchars($SiteUrl) ?>"
       style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
      Get Started
    </a>
  </div>
</body>
</html>
```

### Two-Column Layout

```php
<table width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td width="50%" valign="top">
      <h3>Feature 1</h3>
      <p>Description...</p>
    </td>
    <td width="50%" valign="top">
      <h3>Feature 2</h3>
      <p>Description...</p>
    </td>
  </tr>
</table>
```

## Further Reading

- [PHPMailer Documentation](https://github.com/PHPMailer/PHPMailer)
- [Email Design Best Practices](https://www.campaignmonitor.com/resources/guides/email-design-best-practices/)
- [HTML Email Coding Guide](https://templates.mailchimp.com/getting-started/html-email-basics/)
- [Can I email...](https://www.caniemail.com/) - Email client support reference
