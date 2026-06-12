[![CI](https://github.com/Neuron-PHP/cms/actions/workflows/ci.yml/badge.svg)](https://github.com/Neuron-PHP/cms/actions)
[![codecov](https://codecov.io/gh/Neuron-PHP/cms/branch/develop/graph/badge.svg)](https://codecov.io/gh/Neuron-PHP/cms)
# Neuron-PHP CMS

A modern, database-backed Content Management System for PHP 8.4+ built on the Neuron framework. Provides a complete content platform with a blog, CMS-managed pages, an events calendar with registration, contact forms, reusable content shortcodes/widgets, user authentication, and a full admin panel.

## Features

- **User Authentication & Authorization**
  - Secure login/logout with password hashing
  - Role-based access control (Admin, Editor, Author, Subscriber)
  - Password reset functionality via email
  - Account locking after failed login attempts
  - "Remember me" functionality

- **Member Registration & Management**
  - Public member registration with email verification
  - Email verification system with secure tokens
  - Rate-limited verification email resends (DOS/spam protection)
  - Member dashboard with profile management
  - Enumeration protection for security

- **Blog System**
  - Create, edit, and publish blog posts
  - Category and tag organization
  - SEO-friendly URLs with slug support
  - RSS feed generation
  - Draft and scheduled post support
  - View count tracking

- **Pages**
  - CMS-managed static pages served at `/pages/:slug`
  - Editor.js block content with shortcode support
  - Draft/published status and SEO-friendly slugs

- **Events & Calendar**
  - Calendar and event listings with event categories
  - Public calendar at `/calendar` with month and category views
  - Featured events surfaced anywhere via the `[featured-event]` shortcode (full card or image-only)
  - Event registration (see below)

- **Event Registration**
  - Per-event registration forms via the `[event-registration]` shortcode (single event or "next upcoming dates" of a category)
  - Public or private (members-only) events; private events prompt guests to log in
  - Optional per-event capacity limit (full events hide the form and reject late submissions)
  - Admin email notification on each registration, plus an optional confirmation email to the registrant
  - Admin review screen listing/filtering registrations per event
  - CSRF protection and honeypot spam protection

- **Contact Forms**
  - Drop-in contact form via the `[contact]` shortcode
  - Persist-first submissions with admin email notification
  - Admin review of submissions; CSRF + honeypot protection

- **Shortcodes & Widgets**
  - Reusable content widgets rendered in any page or post body
  - Built in: `[latest-posts]`, `[calendar]`, `[featured-event]`, `[event-registration]`, `[contact]`

- **Media Library**
  - Upload and manage media through the admin panel
  - MIME/type validation

- **Content Revisions**
  - Revision history for posts and pages

- **Admin Panel**
  - Dashboard with statistics
  - Post and page management (CRUD operations)
  - Category and tag management
  - Event, event category, and event-registration management
  - Contact submission review
  - Media library
  - User management
  - Profile management
  - Background job monitoring

- **Maintenance Mode**
  - Toggle a site-wide maintenance page with IP allow-list and retry-after support
  - Managed via CLI commands or configuration

- **Email System**
  - Password reset emails
  - Configurable email templates
  - PHPMailer integration

- **Scheduling & Background Jobs**
  - Scheduled tasks (e.g., daily summaries)
  - Background job processing (e.g., email sending, report generation)
  - Email queue support

- **Database Support**
  - SQLite, MySQL, PostgreSQL via PDO
  - Database migrations via Phinx
  - Seeders for sample data

### Database Compatibility

All three databases are fully supported with identical behavior across platforms:

- **Foreign Key Constraints**: Properly enforced on all databases (including SQLite)
  - Cascade deletes work correctly (deleting a user cascades to their posts)
  - Referential integrity maintained across all platforms

- **Timestamp Management**: `created_at` and `updated_at` handled at application level
  - Automatic timestamp updates work consistently across all databases
  - No reliance on database-specific features (MySQL's ON UPDATE CURRENT_TIMESTAMP)

- **Transactions**: Full ACID compliance
  - Transaction rollback works identically on all platforms
  - Nested transaction support where available

- **Database-Specific Optimizations**:
  - **SQLite**: WAL mode enabled for better concurrency, foreign keys enforced by default
  - **MySQL**: UTF8MB4 charset, UTC timezone configuration
  - **PostgreSQL**: UTF8 encoding, UTC timezone configuration

**Performance Notes:**
- SQLite: Ideal for development and small-to-medium production sites
- MySQL: Recommended for large-scale production deployments
- PostgreSQL: Best for complex queries and enterprise features

All databases are tested in CI on every commit to ensure consistent behavior.

## Installation

### Requirements

- PHP 8.4 or higher
- Composer
- PDO extension (with SQLite, MySQL, or PostgreSQL driver)

### Install via Composer

```bash
composer require neuron-php/cms
```

### Run the Installer

The `cms:install` command sets up everything automatically:

```bash
php neuron cms:install
```

The installer will:
1. Create the complete directory structure (app/, config/, db/, public/, resources/, storage/)
2. Publish all view templates (admin panel, blog, auth, layouts)
3. Publish application initializers
4. Create configuration files (neuron.yaml, routing.yaml, auth.yaml, event-listeners.yaml)
5. Generate the front controller (public/index.php)
6. Set up database migrations
7. Optionally run migrations to create database tables
8. Prompt you to create an admin user

That's it. The installer handles all setup automatically.

### Upgrading

After updating the package with Composer, run the upgrade command to copy any new migrations and resources into your installation:

```bash
composer update neuron-php/cms
php neuron cms:upgrade
```

Useful flags:
- `--check` - show available updates without applying them
- `--migrations-only` - copy only new migration files
- `--skip-views` - don't touch published views
- `--run-migrations` - run database migrations automatically

The upgrade command preserves your customizations (it won't overwrite views by default) and reports any version-specific notes or breaking changes.

## Project Structure

After running `cms:install`, your project will have the following structure:

```
your-project/
├── app/
│   ├── Controllers/          # Your custom controllers
│   ├── Events/              # Custom event classes
│   ├── Initializers/        # Application initializers
│   │   ├── AuthInitializer.php
│   │   ├── MaintenanceInitializer.php
│   │   ├── PasswordResetInitializer.php
│   │   └── ViewDataInitializer.php
│   ├── Jobs/                # Background jobs
│   ├── Listeners/           # Event listeners
│   ├── Models/              # Domain models
│   ├── Repositories/        # Data repositories
│   └── Services/            # Business logic services
│
├── config/
│   ├── auth.yaml           # Authentication configuration
│   ├── neuron.yaml         # Main application config
│   ├── event-listeners.yaml # Event listener configuration
│   └── routing.yaml        # Routing configuration (URL rewrites, controller paths)
│
├── db/
│   ├── migrate/            # Database migrations
│   │   ├── *_create_users_table.php
│   │   ├── *_create_posts_table.php
│   │   ├── *_create_pages_table.php
│   │   ├── *_create_categories_table.php
│   │   ├── *_create_tags_table.php
│   │   ├── *_create_events_table.php
│   │   ├── *_create_event_categories_table.php
│   │   ├── *_create_event_registrations_table.php
│   │   ├── *_create_contact_submissions_table.php
│   │   └── *_create_queue_tables.php
│   └── seed/               # Database seeders
│
├── public/
│   ├── index.php          # Front controller
│   └── icon.png           # Default favicon
│
├── resources/
│   └── views/
│       ├── admin/         # Admin panel templates
│       │   ├── categories/
│       │   ├── dashboard/   # Dashboard views
│       │   ├── posts/       # Post management
│       │   ├── pages/       # Page management
│       │   ├── events/      # Event management
│       │   ├── event_categories/
│       │   ├── event_registrations/
│       │   ├── contact_submissions/
│       │   ├── media/
│       │   ├── profile/
│       │   ├── tags/
│       │   └── users/
│       ├── auth/          # Login/password reset
│       ├── blog/          # Public blog views
│       ├── calendar/      # Public calendar/event views
│       ├── pages/         # Public CMS page views
│       ├── member/        # Member registration & dashboard
│       │   ├── dashboard/
│       │   ├── profile/
│       │   └── registration/
│       ├── content/       # Content pages
│       ├── emails/        # Email templates
│       ├── http_codes/    # Error pages
│       └── layouts/       # Layout templates
│
├── storage/
│   ├── cache/            # Cache storage
│   ├── logs/             # Application logs
│   └── database.sqlite3  # SQLite database (if using SQLite)
│
└── composer.json
```

## Quick Start

After running `cms:install`, you're ready to go!

### Start the Development Server

```bash
php -S localhost:8000 -t public
```

Visit:
- Public blog: `http://localhost:8000/blog`
- Admin panel: `http://localhost:8000/admin`
- Member registration: `http://localhost:8000/register`
- Member dashboard: `http://localhost:8000/member` (after registration)

Log in with the admin credentials you created during installation.

### Start the Job System (Optional)

For background jobs and scheduled tasks:

```bash
vendor/bin/neuron jobs:run
```

This runs both the scheduler (for scheduled tasks) and worker (for email sending and background jobs).


### Optional Configuration

If you need to customize settings, edit `config/neuron.yaml`:

```yaml
site:
  name: My Blog
  title: Welcome to My Blog
  description: A blog about technology
  url: https://example.com

database:
  adapter: sqlite  # or mysql, pgsql
  name: storage/database.sqlite3
```

All routing, authentication settings, and event listeners are pre-configured by the installer.

## Usage

### Creating a Blog Post

1. Log in to the admin panel at `/admin`
2. Navigate to Posts → New Post
3. Enter title, content, categories, and tags
4. Choose status (Draft, Published, or Scheduled)
5. Click Save

### Managing Categories and Tags

- Navigate to Categories or Tags in the admin panel
- Create, edit, or delete as needed
- Organize your content for easy navigation

### Managing Users

Admin users can:
- Create new user accounts
- Assign roles (Admin, Editor, Author, Subscriber)
- Activate/deactivate accounts
- Reset passwords

### Member Registration

The CMS supports public member registration with email verification:

1. **Enable Registration**: Configure in `config/neuron.yaml`:
   ```yaml
   member:
     registration_enabled: true
     require_email_verification: true
   ```

2. **Registration Flow**:
   - Users visit `/register` to create an account
   - System sends verification email with secure token
   - Users click verification link to activate account
   - Upon verification, users can access `/member` dashboard

3. **Security Features**:
   - **Email Verification**: Prevents fake accounts with unverified emails
   - **Rate Limiting**: Resend verification requests are throttled:
     - Per-IP limit: 5 requests per 5 minutes
     - Per-email limit: 1 resend per 5 minutes
   - **Enumeration Protection**: Generic responses prevent email address discovery
   - **CSRF Protection**: All forms protected against cross-site request forgery
   - **IP Resolution**: Properly handles proxy headers (Cloudflare, X-Forwarded-For, etc.)

4. **Member Dashboard**:
   - Access at `/member` (requires authentication)
   - Profile management at `/member/profile`
   - Role-based access separate from admin panel

### Pages and Shortcodes

Create CMS-managed pages in the admin panel (Pages → New Page); they're served at `/pages/:slug`. Page and post bodies support shortcodes that render dynamic widgets:

| Shortcode | Renders |
|-----------|---------|
| `[latest-posts]` | The most recent blog posts |
| `[calendar]` | An events calendar/list |
| `[featured-event]` | The next available featured event (full card, or image-only) |
| `[event-registration]` | A registration form for an event or event category |
| `[contact]` | A contact form |

Examples:

```text
[latest-posts limit="5"]
[featured-event]
[featured-event display="image"]
[featured-event display="image" link="false"]
[event-registration event="open-house-2026"]
[event-registration category="workshops" limit="3"]
[contact]
```

#### Featured event display modes

The `[featured-event]` shortcode renders the next published featured event that
hasn't ended yet. Use the `display` attribute to control the layout:

| Attribute | Values | Default | Description |
|-----------|--------|---------|-------------|
| `display` | `card`, `image` | `card` | `card` renders the full event card (image, title, date, location, description, link). `image` renders only the event's featured image. |
| `link` | `true`, `false` | `true` | When `display="image"`, whether the image links to the event page. |

Image mode is handy for sponsored-event banners or cover-photo strips. If there
is no featured event — or `display="image"` is used and the featured event has
no image — the shortcode renders nothing visible (an HTML comment), so a
surrounding template can safely fall back to its own placeholder.

### Events and Registration

1. Create events in the admin panel (Events → New Event), optionally assigning an event category.
2. Mark an event as **featured** to surface it via `[featured-event]`, and/or enable **registration** on the event.
3. For registration, choose visibility (public or members-only) and an optional **capacity** (leave blank for unlimited).
4. Place an `[event-registration]` shortcode on a page/post (single event or the next upcoming dates of a category).
5. Registrations are stored and reviewable under Admin → Event Registrations; the configured admin recipient is emailed for each new registration. See the [Events & Registration](#events--registration) configuration below.

### Customizing Views

All view templates are in `resources/views/` and can be customized:

- `layouts/main.php` - Main site layout
- `blog/index.php` - Blog listing
- `blog/show.php` - Individual post
- `admin/*` - Admin panel templates
- `member/*` - Member registration and dashboard templates
  - `member/registration/register.php` - Registration form
  - `member/registration/verify-email-sent.php` - Email verification sent page
  - `member/registration/email-verified.php` - Email verification success/failure
  - `member/dashboard/index.php` - Member dashboard
  - `member/profile/edit.php` - Profile editing

### Running the Job System

[Neuron Jobs](https://github.com/Neuron-PHP/jobs)

The CMS includes a complete job system for scheduled tasks and background processing. You can run it in three different modes:

#### 1. Combined Mode (Recommended)

Run both scheduler and queue worker together with a single command:

```bash
vendor/bin/neuron jobs:run
```

This is the **easiest way** to run the complete job system. It manages both the scheduler and worker in one process.

**Options:**
- `--schedule-interval=30` - Scheduler polling interval in seconds (default: 60)
- `--queue=emails,default` - Queue(s) to process (default: default)
- `--worker-sleep=5` - Worker sleep when queue is empty (default: 3)
- `--worker-timeout=120` - Job timeout in seconds (default: 60)
- `--max-jobs=100` - Max jobs before restarting worker (default: unlimited)

**Examples:**
```bash
# Run with defaults (both scheduler and worker)
vendor/bin/neuron jobs:run

# Run with custom schedule interval and specific queues (in order of priority)
vendor/bin/neuron jobs:run --schedule-interval=30 --queue=emails,notifications
```

#### 2. Scheduler Only

Run just the scheduler for executing scheduled tasks:

```bash
vendor/bin/neuron jobs:schedule
```

Handles recurring tasks and scheduled jobs defined in `config/schedule.yaml`.

**Options:**
- `--interval=30` - Polling interval in seconds (default: 60)
- `--poll` - Run a single poll and exit (useful for cron)

#### 3. Worker Only

Run just the queue worker for processing background jobs:

```bash
vendor/bin/neuron jobs:work
```

Processes queued jobs including emails and background tasks.

**Options:**
- `--queue=emails` - Process specific queue
- `--sleep=5` - Seconds to sleep when queue is empty
- `--timeout=120` - Job timeout in seconds
- `--max-jobs=100` - Maximum jobs to process before stopping

## Configuration

### Authentication

The installer creates `config/auth.yaml` with sensible defaults. You can customize:

- Password requirements (min length, complexity)
- Session timeout duration
- Failed login attempt limits
- Account lockout duration

### Routing

The CMS uses attribute-based routing defined directly on controller methods. The installer creates `config/routing.yaml` to configure URL rewrites and controller paths.

#### URL Rewrites

By default, the CMS rewrites the root URL (`/`) to `/blog`. You can customize this in `config/routing.yaml`:

```yaml
# config/routing.yaml
rewrites:
  '/': '/custom/landing'  # Rewrite root to your custom controller

controller_paths:
  - path: 'app/Controllers'        # Your controllers first (takes precedence)
    namespace: 'App\Controllers'
  - path: 'vendor/neuron-php/cms/src/Cms/Controllers'
    namespace: 'Neuron\Cms\Controllers'
```

Then create your custom landing controller:

```php
// app/Controllers/Landing.php
use Neuron\Mvc\Controller;
use Neuron\Routing\Attributes\Get;

class Landing extends Controller
{
    #[Get('/custom/landing', name: 'landing')]
    public function index()
    {
        return $this->renderHtml(OK, [], 'custom-home');
    }
}
```

URL rewrites are transparent (no HTTP redirect) - the browser URL stays the same while the application routes to a different path internally.

#### Available Routes

The CMS provides these pre-configured routes via controller attributes:

- **Public blog pages**:
  - `/blog` - Blog listing
  - `/blog/article/:slug` - Individual post
  - `/blog/category/:slug` - Category listing
  - `/blog/tag/:slug` - Tag listing
  - `/blog/rss` - RSS feed

- **Pages**:
  - `/pages/:slug` - CMS-managed page

- **Calendar & events**:
  - `/calendar` - Calendar/event listing
  - `/calendar/event/:slug` - Individual event
  - `/calendar/category/:slug` - Events by category

- **Event registration**:
  - `/events/register` - Registration form submission (CSRF protected)
  - `/events/register/token` - CSRF token endpoint for cached forms

- **Contact**:
  - `/contact` - Contact form
  - `/contact/submit` - Contact form submission (CSRF protected)

- **Admin panel**: `/admin/*` - Full admin interface with authentication (posts, pages, categories, tags, events, event categories, event registrations, contact submissions, media, users, jobs)

- **Authentication**:
  - `/login` - Login form
  - `/logout` - Logout handler

- **Password reset**:
  - `/password/reset` - Request reset form
  - `/password/reset/confirm` - Reset confirmation

- **Member registration and dashboard**:
  - `/register` - Registration form
  - `/verify-email` - Email verification
  - `/resend-verification` - Resend verification email (rate-limited)
  - `/member` - Member dashboard (requires authentication)
  - `/member/profile` - Profile management

For more information about routing configuration, URL rewrites, and attribute-based routing, see the [MVC Routing Documentation](https://github.com/Neuron-PHP/mvc).

### Email

Configure email in `config/neuron.yaml`:

```yaml
email:
  driver: smtp                    # smtp, sendmail, or mail
  host: smtp.example.com
  port: 587
  username: user@example.com
  password: your_password
  encryption: tls                 # tls or ssl
  from_address: noreply@example.com
  from_name: My Blog
  test_mode: false               # optional - logs emails instead of sending
```

### Events & Registration

Event registration notifications are configured under the `events.registration` section of `config/neuron.yaml`:

```yaml
events:
  registration:
    notify_email: "registrations@example.com"  # admin recipient for new-registration emails
                                                # (falls back to email.from_address when blank)
    confirmation_enabled: false                 # also email a confirmation to the registrant
    success_message: "Thank you for registering. We look forward to seeing you!"
```

- `notify_email` is who receives the "New Event Registration" email; the registrant's address is set as Reply-To.
- If `notify_email` is blank, it falls back to `email.from_address`. If neither is set, no admin email is sent (a warning is logged).
- Notifications require a working `email` configuration (and `test_mode: false`) to actually deliver.

### Rate Limiting

The resend verification email endpoint is protected by rate limiting to prevent DOS attacks and spam. The default configuration is:

- **Per-IP limit**: 5 requests per 5 minutes
- **Per-email limit**: 1 resend per 5 minutes

These limits are enforced server-side using the `ResendVerificationThrottle` service, which:
- Hashes email addresses (SHA-256) before storage for privacy
- Uses file-based storage by default (configurable to Redis or memory)
- Properly resolves client IPs through proxy headers (Cloudflare, X-Forwarded-For, etc.)
- Returns generic success messages to prevent email enumeration attacks

To customize rate limits, modify the throttle configuration in your application initialization:

```php
// app/Initializers/CustomRateLimitInitializer.php
$throttle = new ResendVerificationThrottle(null, [
    'ip_limit' => 10,           // 10 requests per window
    'ip_window' => 600,         // 10 minutes
    'email_limit' => 2,         // 2 resends per window
    'email_window' => 900       // 15 minutes
]);
```

## CLI Commands

The CMS registers the following `neuron` console commands:

**Installation & upgrades**
- `cms:install` - Scaffold a new CMS project (directories, views, config, migrations, admin user)
- `cms:upgrade` - Copy new migrations/resources after a Composer update (see [Upgrading](#upgrading))

**User management**
- `cms:user:create` - Create a user account
- `cms:user:list` - List user accounts
- `cms:user:delete` - Delete a user account
- `cms:user:reset-password` - Reset a user's password

**Maintenance mode**
- `cms:maintenance:enable` - Put the site into maintenance mode
- `cms:maintenance:disable` - Take the site out of maintenance mode
- `cms:maintenance:status` - Show current maintenance status

Run any command with `php neuron <command>` (for example, `php neuron cms:user:create`).

## Dependencies

- `neuron-php/mvc` (0.8.*) - MVC framework
- `neuron-php/cli` (0.8.*) - CLI commands
- `neuron-php/jobs` (0.2.*) - Background jobs
- `phpmailer/phpmailer` (^6.9) - Email sending
- `robmorgan/phinx` (^0.16) - Database migrations

## More Information

- **Framework Documentation**: [neuronphp.com](http://neuronphp.com)
- **GitHub**: [github.com/neuron-php/cms](https://github.com/neuron-php/cms)
- **Packagist**: [packagist.org/packages/neuron-php/cms](https://packagist.org/packages/neuron-php/cms)
- **Issues**: [github.com/neuron-php/cms/issues](https://github.com/neuron-php/cms/issues)

## License

MIT License - see LICENSE file for details
