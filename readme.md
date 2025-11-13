[![CI](https://github.com/Neuron-PHP/cms/actions/workflows/ci.yml/badge.svg)](https://github.com/Neuron-PHP/cms/actions)
# Neuron-PHP CMS

A modern, database-backed Content Management System for PHP 8.4+ built on the Neuron framework. Provides a complete blog platform with user authentication, admin panel, and content management.

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

- **Admin Panel**
  - Dashboard with statistics
  - Post management (CRUD operations)
  - Category and tag management
  - User management
  - Profile management

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
4. Create configuration files (neuron.yaml, routes.yaml, auth.yaml, event-listeners.yaml)
5. Generate the front controller (public/index.php)
6. Set up database migrations
7. Optionally run migrations to create database tables
8. Prompt you to create an admin user

That's it. The installer handles all setup automatically.

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
│   └── routes.yaml         # Route definitions
│
├── db/
│   ├── migrate/            # Database migrations
│   │   ├── *_create_users_table.php
│   │   ├── *_create_posts_table.php
│   │   ├── *_create_categories_table.php
│   │   ├── *_create_tags_table.php
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
│       │   ├── dashboard/ # Dashboard views
│       │   ├── posts/     # Post management
│       │   ├── profile/     # Post management
│       │   ├── tags/
│       │   └── users/
│       ├── auth/          # Login/password reset
│       ├── blog/          # Public blog views
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

All routes, authentication settings, and event listeners are pre-configured by the installer.

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

### Routes

The installer automatically creates `config/routes.yaml` with pre-configured routes for:

- Public blog pages (`/blog`, `/blog/article/{slug}`, `/blog/category/{slug}`, `/blog/tag/{slug}`)
- Admin panel (`/admin/*`)
- Authentication (`/login`, `/logout`)
- Password reset (`/password/reset`, `/password/reset/confirm`)
- Member registration and dashboard:
  - `/register` - Registration form
  - `/verify-email` - Email verification
  - `/resend-verification` - Resend verification email (rate-limited)
  - `/member` - Member dashboard (requires authentication)
  - `/member/profile` - Profile management
- RSS feed (`/blog/rss`)

You can customize routes by editing `config/routes.yaml`.

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
