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

- **Queue System**
  - Background job processing
  - Email queue support
  - Extensible job framework

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
4. Create configuration files (config.yaml, routes.yaml, auth.yaml, event-listeners.yaml)
5. Generate the front controller (public/index.php)
6. Set up database migrations
7. Optionally run migrations to create database tables
8. Prompt you to create an admin user

That's it! The installer handles all setup automatically.

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
│   │   └── PasswordResetInitializer.php
│   ├── Jobs/                # Background jobs
│   ├── Listeners/           # Event listeners
│   ├── Models/              # Domain models
│   ├── Repositories/        # Data repositories
│   └── Services/            # Business logic services
│
├── config/
│   ├── auth.yaml           # Authentication configuration
│   ├── config.yaml         # Main application config
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
│       │   ├── dashboard/ # Dashboard views
│       │   ├── posts/     # Post management
│       │   ├── categories/
│       │   ├── tags/
│       │   ├── users/
│       │   └── profile/
│       ├── auth/          # Login/password reset
│       ├── blog/          # Public blog views
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

Log in with the admin credentials you created during installation.

### Optional Configuration

If you need to customize settings, edit `config/config.yaml`:

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

### Customizing Views

All view templates are in `resources/views/` and can be customized:

- `layouts/main.php` - Main site layout
- `blog/index.php` - Blog listing
- `blog/show.php` - Individual post
- `admin/*` - Admin panel templates

### Scheduling Jobs

Start the scheduler:

```bash
php neuron jobs:schedule
```

This will process scheduled jobs.

### Running Background Jobs

Start the queue worker:

```bash
php neuron jobs:work
```

This will process queued jobs like sending emails in the background.

## Testing

Run the test suite:

```bash
# Run all tests
vendor/bin/phpunit tests

# Run with coverage
vendor/bin/phpunit tests --coverage-text

# Run specific test
vendor/bin/phpunit tests/Cms/BlogControllerTest.php
```

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
- RSS feed (`/blog/rss`)

You can customize routes by editing `config/routes.yaml`.

### Email

Configure email in `config/config.yaml`:

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
