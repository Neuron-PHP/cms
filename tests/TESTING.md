# Testing Guide

This document explains the test structure and how to run different types of tests.

## Test Directory Structure

```
tests/
├── Unit/                    # Unit tests (fast, isolated, use mocks)
│   ├── Cms/                # CMS component unit tests (546 tests)
│   └── BootstrapTest.php   # Bootstrap unit test
├── Integration/            # Integration tests (slower, use real infrastructure)
│   ├── IntegrationTestCase.php           # Base class for integration tests
│   ├── PostPublishingFlowTest.php        # Post workflow (6 tests)
│   ├── PostPublishingSchedulingTest.php  # Publishing/scheduling (9 tests)
│   ├── UserAuthenticationFlowTest.php    # User auth (8 tests)
│   ├── CategoryTagRelationshipTest.php   # Category/tag relations (10 tests)
│   ├── PageManagementFlowTest.php        # Page management (9 tests)
│   └── TagManagementTest.php             # Tag operations (13 tests)
├── resources/              # Test resources (views, fixtures, etc.)
├── bootstrap.php           # PHPUnit bootstrap file
└── phpunit.xml            # PHPUnit configuration
```

## Test Types

### Unit Tests
- **Location**: `tests/Unit/`
- **Purpose**: Fast, isolated tests that use mocks and stubs
- **Database**: In-memory SQLite or mocks
- **Speed**: Very fast (< 1 second for most tests)
- **When to use**: Testing individual classes, methods, and logic

### Integration Tests
- **Location**: `tests/Integration/`
- **Purpose**: Test real infrastructure and component interactions
- **Database**: Real database (SQLite file, MySQL, or PostgreSQL)
- **Migrations**: Runs actual Phinx migrations from `resources/database/migrate/`
- **Speed**: Slower (1-2 seconds per test)
- **When to use**: Testing database operations, workflows, constraints

**Current Integration Test Coverage (55 tests, 195 assertions):**

1. **Post Publishing Flow** (6 tests)
   - Complete post creation → update → publish → delete workflow
   - Posts with categories (many-to-many)
   - Posts with tags (many-to-many)
   - Foreign key cascade deletes (user → posts)
   - Slug uniqueness constraints
   - Transaction isolation between tests

2. **Post Publishing & Scheduling** (9 tests)
   - Publishing draft posts (draft → published)
   - Unpublishing posts (published → draft)
   - Scheduling posts for future publication
   - Auto-publishing scheduled posts when date arrives
   - View count tracking and increment
   - Querying posts by status (draft, published, scheduled)
   - Most viewed posts ranking
   - Recently published posts queries
   - Published date preservation on updates

3. **User Authentication Flow** (8 tests)
   - User registration with password hashing
   - Email verification token generation and validation
   - Password reset token workflow
   - Expired token cleanup
   - Failed login attempt tracking and account locking
   - Username uniqueness constraint
   - Email uniqueness constraint
   - User roles and status management

4. **Category & Tag Relationships** (10 tests)
   - Category creation and retrieval
   - Category slug uniqueness
   - Attaching multiple categories to posts
   - Querying posts by category
   - Tag creation and retrieval
   - Tag slug uniqueness
   - Attaching multiple tags to posts
   - Querying posts by tag
   - Category deletion cascade to pivot table
   - Posts with both categories and tags

5. **Page Management Flow** (9 tests)
   - Page creation with templates and metadata
   - Page updates and content changes
   - Page publishing workflow (draft → published → draft)
   - Page slug uniqueness constraints
   - Multiple page templates (default, full-width, sidebar, landing)
   - View count tracking
   - SEO metadata (meta_title, meta_description, meta_keywords)
   - User deletion cascades to pages
   - Querying published pages

6. **Tag Management** (13 tests)
   - Creating tags from tag names
   - Auto-generating slugs from multi-word names
   - Finding existing tags (find-or-create pattern)
   - Parsing comma-separated tag strings
   - Tag case normalization
   - Finding unused/orphaned tags
   - Tag usage count tracking
   - Most popular tags ranking
   - Deleting unused tags
   - Tag deletion cascades to pivot table
   - Whitespace handling in tag names
   - Empty tag string handling

## Running Tests

### Run All Tests
```bash
vendor/bin/phpunit -c tests/phpunit.xml
```

### Run Only Unit Tests (Default for CI)
```bash
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=unit
```

### Run Only Integration Tests
```bash
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=integration
```

### Run with Coverage
```bash
# Unit tests with coverage
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=unit --coverage-text --coverage-filter=src

# Integration tests with coverage
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=integration --coverage-text --coverage-filter=src
```

### Run Specific Test File
```bash
# Unit test
vendor/bin/phpunit -c tests/phpunit.xml tests/Unit/Cms/Services/Post/CreatorTest.php

# Integration test
vendor/bin/phpunit -c tests/phpunit.xml tests/Integration/PostPublishingFlowTest.php
```

### Run with Testdox (Human-Readable Output)
```bash
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=unit --testdox
```

## Integration Test Configuration

Integration tests can be configured to use different databases via environment variables:

### SQLite (Default)
```bash
# Uses temporary SQLite file
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=integration
```

### MySQL
```bash
export TEST_DB_DRIVER=mysql
export TEST_DB_HOST=localhost
export TEST_DB_PORT=3306
export TEST_DB_NAME=cms_test
export TEST_DB_USER=root
export TEST_DB_PASSWORD=secret
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=integration
```

### PostgreSQL
```bash
export TEST_DB_DRIVER=pgsql
export TEST_DB_HOST=localhost
export TEST_DB_PORT=5432
export TEST_DB_NAME=cms_test
export TEST_DB_USER=postgres
export TEST_DB_PASSWORD=secret
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=integration
```

## Writing Tests

### Unit Test Example
```php
<?php

namespace Tests\Unit\Cms\Services\Post;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Post\Creator;

class CreatorTest extends TestCase
{
    public function testCreatePost(): void
    {
        // Use mocks for dependencies
        $repository = $this->createMock(IPostRepository::class);
        $creator = new Creator($repository);

        // Test logic
        $post = $creator->create('Title', '{}', 1, 'draft');

        $this->assertEquals('Title', $post->getTitle());
    }
}
```

### Integration Test Example
```php
<?php

namespace Tests\Integration;

class PostWorkflowTest extends IntegrationTestCase
{
    public function testCompletePostWorkflow(): void
    {
        // Uses real database with real migrations
        $userId = $this->createTestUser(['username' => 'test']);

        $stmt = $this->pdo->prepare(
            "INSERT INTO posts (title, slug, author_id, status) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute(['My Post', 'my-post', $userId, 'draft']);

        $postId = (int)$this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $postId);

        // Transaction will be rolled back after test
    }
}
```

## Continuous Integration (GitHub Actions)

The CI workflow (`.github/workflows/ci.yml`) runs **both unit and integration tests** to ensure comprehensive validation.

```yaml
- name: Run Unit Tests with Coverage
  run: vendor/bin/phpunit -c tests/phpunit.xml --testsuite=unit --coverage-clover coverage.xml --coverage-filter src

- name: Run Integration Tests
  run: vendor/bin/phpunit -c tests/phpunit.xml --testsuite=integration
```

**Why both test suites in CI?**
- Unit tests provide fast feedback (< 30 seconds)
- Integration tests validate real database behavior and migrations (< 20 seconds)
- Total CI time remains under 1 minute
- Catches both logic errors and infrastructure issues automatically
- Uses SQLite with real Phinx migrations for reliable testing

## Best Practices

1. **Write unit tests first**: Fast feedback, test logic in isolation
2. **Use integration tests for infrastructure**: Database constraints, migrations, real workflows
3. **Keep integration tests focused**: Test specific workflows, not every edge case
4. **Use transactions for isolation**: IntegrationTestCase handles this automatically
5. **Don't skip tests in CI**: If a test is flaky, fix it or remove it
6. **Test behavior, not implementation**: Tests should verify outcomes, not internals

## Coverage

View coverage reports:

```bash
# Generate HTML coverage report
vendor/bin/phpunit -c tests/phpunit.xml --testsuite=unit --coverage-html coverage

# Open in browser
open coverage/index.html
```

Current coverage (as of last update):
- **Overall**: 39.57%
- **Classes**: 25.00% (26/104)
- **Methods**: 55.62% (406/730)
- **Lines**: 39.57% (1956/4943)

## Troubleshooting

### "No tests executed"
- Check that you're in the correct directory
- Verify the test file has `Test.php` suffix
- Ensure the test class extends `TestCase` or `IntegrationTestCase`

### Integration tests fail with database errors
- Check that migrations exist in `resources/database/migrate/`
- Verify PDO extension is installed
- For MySQL/PostgreSQL, ensure database exists and credentials are correct

### Tests are very slow
- Are you running integration tests? Try `--testsuite=unit` for faster tests
- Check for network calls or file I/O in unit tests (should use mocks)

### "Serialization of 'Closure' is not allowed"
- This warning can appear with process isolation
- Usually safe to ignore if tests pass
- To suppress: Add `@runInSeparateProcess` annotation to specific tests if needed
