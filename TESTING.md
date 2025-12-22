# Testing Guide

## Running Tests

### Quick Start

Run all tests:
```bash
./vendor/bin/phpunit tests
```

Run tests with coverage:
```bash
./vendor/bin/phpunit tests --coverage-text --coverage-filter=src
```

Run a specific test file:
```bash
./vendor/bin/phpunit tests/Unit/Cms/Services/Media/CloudinaryUploaderTest.php
```

## Integration Tests

### Cloudinary Integration Tests

By default, the 3 Cloudinary integration tests are **skipped** because they require real Cloudinary API credentials.

Cloudinary credentials can be configured in **two ways**:

#### Option 1: During Installation (Recommended)

When you run the CMS installation:
```bash
php neuron cms:install
```

The installer will optionally prompt you for Cloudinary credentials and save them to `config/neuron.yaml`.

#### Option 2: For Testing Only (Using .env.testing)

To run Cloudinary integration tests locally without affecting your production config:

1. **Copy the example environment file:**
   ```bash
   cp .env.testing.example .env.testing
   ```

2. **Get your Cloudinary credentials:**
   - Sign up for a free account at https://cloudinary.com
   - Go to your dashboard: https://console.cloudinary.com/
   - Copy your credentials

3. **Edit `.env.testing` and add your credentials:**
   ```bash
   # Option 1: Use CLOUDINARY_URL (easiest - copy from dashboard)
   CLOUDINARY_URL=cloudinary://123456789012345:abcdefghijklmnopqrstuvwxyz@your-cloud-name

   # Option 2: Use individual credentials
   CLOUDINARY_CLOUD_NAME=your-cloud-name
   CLOUDINARY_API_KEY=123456789012345
   CLOUDINARY_API_SECRET=abcdefghijklmnopqrstuvwxyz
   CLOUDINARY_FOLDER=test-uploads
   ```

4. **Run the tests:**
   ```bash
   ./vendor/bin/phpunit tests/Unit/Cms/Services/Media/CloudinaryUploaderTest.php
   ```

#### Important Notes:

- ✅ `.env.testing` is git-ignored and will **never be committed**
- ✅ You can use a free Cloudinary account for testing
- ✅ Set `CLOUDINARY_FOLDER=test-uploads` to keep test files organized
- ⚠️ Integration tests will upload actual files to your Cloudinary account
- ⚠️ Remember to clean up test uploads periodically

### CI/CD Integration

For continuous integration (GitHub Actions, GitLab CI, etc.), set the `CLOUDINARY_URL` environment variable as a secret in your CI platform:

**GitHub Actions:**
1. Go to repository Settings → Secrets and variables → Actions
2. Add a new repository secret: `CLOUDINARY_URL`
3. The tests will automatically run when the secret is set

**GitLab CI:**
```yaml
# .gitlab-ci.yml
test:
  script:
    - composer install
    - ./vendor/bin/phpunit tests
  variables:
    CLOUDINARY_URL: $CLOUDINARY_URL  # Set in GitLab CI/CD variables
```

## Test Categories

- **Unit Tests**: Fast tests that mock dependencies (run on every commit)
- **Integration Tests**: Tests that connect to external services (run when credentials are available)
- **Skipped Tests**: Integration tests without credentials (3 Cloudinary tests)

## Expected Test Results

**Without Cloudinary credentials:**
- Tests: 727
- Assertions: ~1,775
- Skipped: 3 (Cloudinary integration tests)
- Status: ✅ All passing

**With Cloudinary credentials:**
- Tests: 727
- Assertions: ~1,790
- Skipped: 0
- Status: ✅ All passing (including integration tests)
