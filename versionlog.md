* Added `queue:install` command for installing the job queue system.
* Queue installer generates migration for jobs and failed_jobs tables.
* Queue installer automatically adds queue configuration to config.yaml.
* Queue installer checks for neuron-php/jobs component availability.
* Queue installer provides helpful usage information after installation.
* Added comprehensive email system with PHPMailer integration.
* Added EmailService class with fluent interface for composing and sending emails.
* Added email helper functions: `sendEmail()`, `sendEmailTemplate()`, and `email()`.
* Added `mail:generate` command for scaffolding email templates.
* Added EMAIL.md documentation covering configuration, usage, and best practices.
* Enhanced installer to create `resources/views/emails/` directory.
* Added support for SMTP, sendmail, and PHP mail drivers.
* Added template rendering for emails with data binding.
* Added test mode for development (logs emails instead of sending).
* Enhanced installer to create complete application directory structure.
* Removed deprecated storage/migrations directory (now using db/migrate).
* MigrationManager and all migration CLI commands now in `neuron-php/mvc` package.
* Removed phinx dependency (now inherited from MVC component).

## 0.8.1 2025-11-10

* Added the maintenance mode command.
* Added the authentication layer.
* Added database migrations.

* Cleaned up blog controller.
* Updated components.

## 0.2.3 2025-11-04
## 0.2.2 2025-08-21
* Now requires the cli component.

## 0.2.1 2025-08-14
* Fixed constructors.

## 0.2.0 2025-08-14
* Upgraded to mvc 0.7

## 0.1.4 2025-08-11
## 0.1.3 2025-08-11
* Renamed boot function.
* Updated mvc component.
* Updated blog controller tests.

## 0.1.2 2025-08-10
## 0.1.1 2025-08-10
## 0.1.0 2025-08-10
