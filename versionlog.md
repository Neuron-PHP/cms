## 0.8.31

## 0.8.30 2026-01-02

## 0.8.29 2026-01-02

## 0.8.28 2026-01-01

## 0.8.27 2026-01-01

## 0.8.26 2026-01-01

## 0.8.25 2025-12-31

## 0.8.24 2025-12-30
* Architectural improvements.

## 0.8.23 2025-12-27
## 0.8.22 2025-12-27
## 0.8.21 2025-12-27
## 0.8.20 2025-12-27
## 0.8.19 2025-12-27
## 0.8.18 2025-12-27
## 0.8.17 2025-12-27
## 0.8.16 2025-12-26
## 0.8.15 2025-12-26
## 0.8.14 2025-12-26
## 0.8.13 2025-12-26
* Auth exception handling improvements.

## 0.8.12 2025-12-26

* Added media management.
* Added page management.
* Lots of refactoring.
* Added more tests.

## 0.8.11 2025-12-25

* Fixed the installer.
* Updated all remaining queries to use the orm component.

## 0.8.10 2025-12-22

* Added current_user_identifier helper function.
* Database compatability improvements.
* Added calendar functionality to cms.

## 0.8.9 2025-12-19

* **Slug generation now uses system abstractions** - All content service classes refactored to use `IRandom` interface
* Refactored 6 service classes: Post/Creator, Post/Updater, Category/Creator, Category/Updater, Page/Creator, Tag/Creator
* Services support dependency injection with optional `IRandom` parameter for testability
* Maintains full backward compatibility - existing code works without changes
* **Security services now use system abstractions** - PasswordResetter and EmailVerifier refactored to use `IRandom` interface
* Secure token generation now uses abstraction instead of direct random_bytes() calls
* Services support dependency injection with optional `IRandom` parameter for testability

## 0.8.8 2025-11-16

* Refactored requests to use the new dto component powered format.
* Security improvements.

## 0.8.7 2025-11-14

* Refactored all controllers to support the new method signature.

## 0.8.6 2025-11-13

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
