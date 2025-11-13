## 0.8.6
* Cleanup and minor fixes.

## 0.8.5 2025-11-12

## 0.8.4 2025-11-12

## 0.8.3 2025-11-12

* Large refactoring.
* Updated to use the new ORM component.
* Added user timezone support.
* Renamed config.yaml to neuron.yaml
* Added member registration and portal.
* Added bootswatch theme support.


## 0.8.2 2025-11-11

* Added `queue:install` command for installing the job queue system.
* Added email system with PHPMailer integration.
* Added EmailService class.
* Added email helper functions: `sendEmail()`, `sendEmailTemplate()`, and `email()`.
* Added `mail:generate` command for scaffolding email templates.
* Enhanced installer to create `resources/views/emails/` directory.
* Added test mode for development (logs emails instead of sending).
* Enhanced installer to create complete application directory structure.
* Removed deprecated storage/migrations directory (now using db/migrate).
* MigrationManager and all migration CLI commands now in `neuron-php/mvc` package.

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
