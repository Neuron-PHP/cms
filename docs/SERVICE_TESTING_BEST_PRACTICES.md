# Service Testing Best Practices

This document outlines best practices for testing services in the Neuron CMS, based on comprehensive test improvements across Authentication, Email, Registration, and Updater services.

## Table of Contents

1. [Overview](#overview)
2. [Test Structure](#test-structure)
3. [Mocking Strategies](#mocking-strategies)
4. [Coverage Goals](#coverage-goals)
5. [Testing Patterns](#testing-patterns)
6. [Examples from the Codebase](#examples-from-the-codebase)

## Overview

Services contain the core business logic of the application. Comprehensive service testing provides:
- **High ROI**: Services are where business rules live
- **Fast execution**: Unit tests run in milliseconds
- **Early bug detection**: Catch issues before integration testing
- **Living documentation**: Tests document expected behavior

## Test Structure

### Basic Test Class Template

```php
<?php

namespace Tests\Cms\Services;

use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    private ServiceClass $_service;
    private DependencyInterface $_mockDependency;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->_mockDependency = $this->createMock(DependencyInterface::class);

        // Instantiate service with mocked dependencies
        $this->_service = new ServiceClass($this->_mockDependency);
    }

    protected function tearDown(): void
    {
        // Clean up resources if needed
        parent::tearDown();
    }
}
```

## Mocking Strategies

### 1. Repository Mocking

Repositories are the most common dependency to mock:

```php
// Mock findById to return a specific entity
$this->_mockRepository
    ->expects($this->once())
    ->method('findById')
    ->with(1)
    ->willReturn($entity);

// Mock findByUsername to return null (user doesn't exist)
$this->_mockRepository
    ->expects($this->once())
    ->method('findByUsername')
    ->with('testuser')
    ->willReturn(null);

// Use willReturnCallback for dynamic behavior
$this->_mockRepository
    ->method('create')
    ->willReturnCallback(function($entity) {
        $entity->setId(1);
        return $entity;
    });
```

### 2. Settings Mocking

Settings often control service behavior:

```php
// Using Memory source for real SettingManager
$memorySource = new Memory();
$memorySource->set('email', 'test_mode', true);
$memorySource->set('email', 'driver', 'smtp');
$settings = new SettingManager($memorySource);

// Or using mock with willReturnCallback
$mockSettings->method('get')->willReturnCallback(function($section, $key) {
    if($section === 'email' && $key === 'test_mode') return true;
    if($section === 'email' && $key === 'driver') return 'smtp';
    return null;
});
```

### 3. Testing Private Methods

Use reflection to test private methods when necessary:

```php
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('privateMethod');
$method->setAccessible(true);

$result = $method->invoke($service, $arg1, $arg2);

$this->assertEquals('expected', $result);
```

### 4. Event Emitter Mocking

Test that events are emitted correctly:

```php
$emitter = $this->createMock(Emitter::class);

// Expect event to be emitted
$emitter
    ->expects($this->once())
    ->method('emit')
    ->with($this->isInstanceOf(UserCreatedEvent::class));

$service = new Service($repository, $emitter);
```

## Coverage Goals

### Target Coverage Levels

- **Method Coverage**: Aim for 90%+ (covering all public methods)
- **Line Coverage**: Aim for 85%+ (covering all logic branches)
- **100% Coverage**: Achievable for simple CRUD services

### What to Test

**Always test:**
- ✅ Happy path scenarios
- ✅ Error conditions and exceptions
- ✅ Edge cases (empty strings, nulls, boundary values)
- ✅ Business rule enforcement
- ✅ State transitions

**Consider testing:**
- Fluent interfaces (method chaining)
- Default values when settings are null
- Exception handling (graceful degradation)
- Optional parameters

**May skip:**
- Trivial getters/setters without logic
- Framework-generated code
- Deep third-party library internals

## Testing Patterns

### 1. Testing Validation Logic

Test each validation rule separately:

```php
public function testRejectsEmptyUsername(): void
{
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Username is required');

    $this->_service->register('', 'email@test.com', 'Pass123', 'Pass123');
}

public function testRejectsShortUsername(): void
{
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Username must be between 3 and 50 characters');

    $this->_service->register('ab', 'email@test.com', 'Pass123', 'Pass123');
}

public function testRejectsInvalidUsernameCharacters(): void
{
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Username can only contain letters, numbers, and underscores');

    $this->_service->register('user@name', 'email@test.com', 'Pass123', 'Pass123');
}
```

### 2. Testing State Transitions

Verify business rules around state changes:

```php
public function testSetsPublishedAtWhenChangingToPublished(): void
{
    $post = new Post();
    $post->setStatus(Post::STATUS_DRAFT);
    $this->assertNull($post->getPublishedAt());

    $this->_mockRepository
        ->method('findById')
        ->willReturn($post);

    $dto = $this->createDto(1, 'Title', 'Content', Post::STATUS_PUBLISHED);

    $result = $this->_service->update($dto);

    $this->assertEquals(Post::STATUS_PUBLISHED, $result->getStatus());
    $this->assertInstanceOf(\DateTimeImmutable::class, $result->getPublishedAt());
}

public function testDoesNotOverwriteExistingPublishedAt(): void
{
    $existingDate = new \DateTimeImmutable('2024-01-01');
    $post = new Post();
    $post->setStatus(Post::STATUS_PUBLISHED);
    $post->setPublishedAt($existingDate);

    $this->_mockRepository
        ->method('findById')
        ->willReturn($post);

    $dto = $this->createDto(1, 'Title', 'Content', Post::STATUS_PUBLISHED);

    $result = $this->_service->update($dto);

    $this->assertSame($existingDate, $result->getPublishedAt());
}
```

### 3. Testing Security Features

Test security-critical functionality thoroughly:

```php
// Test account lockout after failed attempts
public function testLocksAccountAfterMaxFailedAttempts(): void
{
    $user = $this->createTestUser('locktest', 'ValidPass123');

    // Make 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $this->_authentication->attempt('locktest', 'WrongPassword');
    }

    $user = $this->_userRepository->findByUsername('locktest');
    $this->assertTrue($user->isLockedOut());

    // Should not be able to login even with correct password
    $result = $this->_authentication->attempt('locktest', 'ValidPass123');
    $this->assertFalse($result);
}

// Test timing attack prevention
public function testPerformsDummyHashWhenUserNotFound(): void
{
    // Even when user doesn't exist, still perform hash verification
    // to prevent timing attacks that reveal valid usernames
    $result = $this->_authentication->attempt('nonexistent', 'password');

    $this->assertFalse($result);
}
```

### 4. Testing Graceful Degradation

Test that failures don't cascade:

```php
public function testRegistrationSucceedsEvenWhenEmailVerificationFails(): void
{
    $this->_mockRepository
        ->method('findByUsername')
        ->willReturn(null);
    $this->_mockRepository
        ->method('create')
        ->willReturnCallback(function($user) {
            $user->setId(1);
            return $user;
        });

    // Email service fails
    $this->_emailVerifier
        ->method('sendVerificationEmail')
        ->willThrowException(new \Exception('Email service unavailable'));

    // Registration should still succeed (user can request resend later)
    $user = $this->_service->register('user', 'user@test.com', 'Pass123', 'Pass123');

    $this->assertInstanceOf(User::class, $user);
}
```

### 5. Testing Configuration Variations

Test different configuration scenarios:

```php
public function testRegistrationWithEmailVerificationEnabled(): void
{
    $memorySource = new Memory();
    $memorySource->set('member', 'require_email_verification', true);
    $settings = new SettingManager($memorySource);

    $service = new RegistrationService($repository, $hasher, $verifier, $settings);

    // ... setup mocks ...

    $user = $service->register('user', 'user@test.com', 'Pass123', 'Pass123');

    $this->assertEquals(User::STATUS_INACTIVE, $user->getStatus());
    $this->assertFalse($user->isEmailVerified());
}

public function testRegistrationWithEmailVerificationDisabled(): void
{
    $memorySource = new Memory();
    $memorySource->set('member', 'require_email_verification', false);
    $settings = new SettingManager($memorySource);

    $service = new RegistrationService($repository, $hasher, $verifier, $settings);

    // ... setup mocks ...

    $user = $service->register('user', 'user@test.com', 'Pass123', 'Pass123');

    $this->assertEquals(User::STATUS_ACTIVE, $user->getStatus());
    $this->assertTrue($user->isEmailVerified());
}
```

### 6. Testing with DTOs

When services use DTOs, create helper methods:

```php
private function createDto(
    int $id,
    string $title,
    string $content,
    string $status,
    ?string $slug = null
): Dto
{
    $factory = new Factory(__DIR__ . '/config/update-request.yaml');
    $dto = $factory->create();

    $dto->id = $id;
    $dto->title = $title;
    $dto->content = $content;
    $dto->status = $status;

    if ($slug !== null) {
        $dto->slug = $slug;
    }

    return $dto;
}

// Use in tests
public function testUpdateWithDto(): void
{
    $dto = $this->createDto(1, 'Title', 'Content', 'published', 'custom-slug');

    $result = $this->_service->update($dto);

    $this->assertEquals('custom-slug', $result->getSlug());
}
```

## Examples from the Codebase

### Authentication Service (75% coverage, 96% lines)

**Strengths:**
- Comprehensive security testing (lockout, timing attacks)
- Role-based authorization testing
- Remember me functionality
- Password rehashing on login

**Key Tests:**
- `testAttemptWithCorrectCredentials()`
- `testAccountLockoutAfterMaxAttempts()`
- `testLoginUsingRememberToken()`
- `testIsEditorOrHigher()` with multiple role scenarios

**File:** `tests/Unit/Cms/Services/AuthenticationTest.php`

### Registration Service (100% coverage)

**Strengths:**
- All validation rules tested individually
- Both standard and DTO registration paths
- Email verification enabled/disabled scenarios
- Event emission testing
- Graceful email send failures

**Key Tests:**
- `testRegisterWithValidData()`
- `testRegisterWithExistingUsername()`
- `testRegisterSucceedsWhenEmailVerificationFails()`
- `testRegisterWithDtoEmitsUserCreatedEvent()`

**File:** `tests/Unit/Cms/Services/RegistrationServiceTest.php`

### Email Sender Service (91.67% coverage)

**Strengths:**
- Multiple email driver configurations (SMTP/TLS/SSL, sendmail, mail)
- Authentication vs no-authentication
- Private method testing with Reflection
- Fluent interface validation

**Key Tests:**
- `testCreateMailerWithSmtpAndTls()`
- `testSendInTestModeLogsEmail()`
- `testFluentChaining()`

**File:** `tests/Unit/Cms/Services/Email/SenderTest.php`

### Post Updater Service (100% coverage)

**Strengths:**
- Complete CRUD operation testing
- Relationship management (categories, tags)
- Optional field handling
- Business rule enforcement (auto-set published date)

**Key Tests:**
- `testUpdatesPostWithRequiredFields()`
- `testSetsPublishedAtWhenChangingToPublished()`
- `testThrowsExceptionWhenPostNotFound()`

**File:** `tests/Unit/Cms/Services/Post/UpdaterTest.php`

## Running Coverage Reports

### For a Single Service

```bash
./vendor/bin/phpunit tests/Unit/Cms/Services/Auth/AuthenticationTest.php \
  --coverage-text \
  --coverage-filter=src/Cms/Services/Auth
```

### For All Services

```bash
./vendor/bin/phpunit tests/Unit/Cms/Services \
  --coverage-text \
  --coverage-filter=src/Cms/Services
```

### HTML Coverage Report

```bash
./vendor/bin/phpunit tests \
  --coverage-html coverage \
  --coverage-filter=src
```

Then open `coverage/index.html` in a browser.

## Common Pitfalls to Avoid

### ❌ Don't Test Implementation Details

```php
// BAD: Testing private variable directly
$this->assertEquals('value', $service->_privateVar);

// GOOD: Test through public interface
$this->assertEquals('expected', $service->getPublicValue());
```

### ❌ Don't Create Brittle Tests

```php
// BAD: Overly specific assertions
$this->assertEquals('Error: The username field is required and must be between...', $exception->getMessage());

// GOOD: Assert on key information
$this->assertStringContainsString('username', $exception->getMessage());
```

### ❌ Don't Mock What You Don't Own

```php
// BAD: Mocking PHPMailer internals
$mockMailer = $this->createMock(PHPMailer::class);

// GOOD: Test in integration mode or use test mode
$settings->set('email', 'test_mode', true);
```

### ❌ Don't Write One Giant Test

```php
// BAD: One test for everything
public function testEverything(): void
{
    // 500 lines testing every scenario
}

// GOOD: Focused, single-purpose tests
public function testRejectsEmptyUsername(): void { }
public function testRejectsShortUsername(): void { }
public function testRejectsInvalidCharacters(): void { }
```

## Continuous Improvement

### Regular Coverage Checks

Add to CI pipeline:

```bash
./vendor/bin/phpunit --coverage-text --coverage-filter=src | grep -A3 "Summary"
```

### Coverage Trends

Track coverage over time:
- Set minimum coverage thresholds in phpunit.xml
- Require coverage improvement for new code
- Celebrate milestones (80%, 90%, 95%)

### Test Code Reviews

During code reviews, check:
- [ ] All public methods have tests
- [ ] Error paths are tested
- [ ] Edge cases are covered
- [ ] Tests are clear and maintainable
- [ ] No brittle assertions

## Conclusion

Service testing is a high-value investment:

1. **Start with the happy path** - Get basic coverage quickly
2. **Add error cases** - Test exceptions and validation
3. **Cover edge cases** - Nulls, empty strings, boundaries
4. **Test business rules** - State transitions, authorization
5. **Refactor for clarity** - Make tests readable and maintainable

Target 90%+ method coverage and 85%+ line coverage for services. Focus on testing behavior through public interfaces, use mocks for dependencies, and write focused tests that document expected behavior.

---

**Coverage Achievements (Dec 2025):**
- Authentication Service: 43.75% → 75% methods (96.15% lines)
- Email Sender: 44.19% → 91.67% methods (75.58% lines)
- Registration Service: 50% → 100% methods (100% lines)
- Post Updater: 66.67% → 100% methods (100% lines)
