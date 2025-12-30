# Cloudinary Configuration Guide

This document explains how to configure Cloudinary for the Neuron CMS, both for production use and for running integration tests.

## What is Cloudinary?

Cloudinary is a cloud-based image and video management service used by the CMS for:
- Uploading and storing media files
- Image transformation and optimization
- CDN delivery of media assets

## Configuration Methods

### Method 1: During Installation (Recommended for Production)

When you install the CMS, the installer will prompt you to configure Cloudinary:

```bash
php neuron cms:install
```

**During installation, you'll see:**

```
╔═══════════════════════════════════════╗
║  Cloudinary Configuration (Optional)  ║
╚═══════════════════════════════════════╝

Cloudinary is a cloud-based image and video management service.
It's used for uploading, storing, and delivering media files.

Get a free account at: https://cloudinary.com
Find credentials at: https://console.cloudinary.com/settings/general

Would you like to configure Cloudinary now? [y/N]:
```

**If you choose 'y', you'll be prompted for:**
- Cloud name (from your Cloudinary dashboard)
- API key (from your Cloudinary dashboard)
- API secret (from your Cloudinary dashboard)
- Upload folder (default: `neuron-cms/images`)
- Max file size in bytes (default: `5242880` = 5MB)

**Your credentials will be saved to:** `config/neuron.yaml`

```yaml
cloudinary:
  cloud_name: your-cloud-name
  api_key: your-api-key
  api_secret: your-api-secret
  folder: neuron-cms/images
  max_file_size: 5242880
  allowed_formats: [jpg, jpeg, png, gif, webp]
```

⚠️ **Security Note:** Make sure `config/neuron.yaml` is in your `.gitignore` if you plan to commit your code to a public repository.

### Method 2: Manual Configuration

Edit `config/neuron.yaml` and add the Cloudinary section:

```yaml
cloudinary:
  cloud_name: your-cloud-name
  api_key: your-api-key
  api_secret: your-api-secret
  folder: neuron-cms/images
  max_file_size: 5242880
  allowed_formats: [jpg, jpeg, png, gif, webp]
```

### Method 3: Environment Variables (For Testing)

For running integration tests without modifying your production config:

1. Copy the example file:
   ```bash
   cp .env.testing.example .env.testing
   ```

2. Edit `.env.testing` and add your credentials:
   ```bash
   CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
   # OR use individual variables:
   CLOUDINARY_CLOUD_NAME=your-cloud-name
   CLOUDINARY_API_KEY=your-api-key
   CLOUDINARY_API_SECRET=your-api-secret
   CLOUDINARY_FOLDER=test-uploads
   ```

3. `.env.testing` is git-ignored and safe from accidental commits

## Getting Cloudinary Credentials

1. **Sign up:** https://cloudinary.com (free tier available)
2. **Login** to your dashboard
3. **Navigate to:** Settings → Access Keys
4. **Copy your:**
   - Cloud name
   - API Key
   - API Secret

## Testing Integration

### Without Credentials

```bash
./vendor/bin/phpunit tests
```

**Result:** 727 tests, 3 skipped (Cloudinary integration tests)

### With Credentials

After configuring via `.env.testing`:

```bash
./vendor/bin/phpunit tests
```

**Result:** 727 tests, 0 skipped (all tests run including Cloudinary integration)

## Which Method Should I Use?

| Scenario | Method | File |
|----------|--------|------|
| **Production deployment** | Installation prompt | `config/neuron.yaml` |
| **Local development** | Installation prompt | `config/neuron.yaml` |
| **Running integration tests** | Environment variables | `.env.testing` |
| **CI/CD pipeline** | Environment variables | CI secrets |
| **Manual setup** | Edit config file | `config/neuron.yaml` |

## Security Best Practices

✅ **DO:**
- Use `.env.testing` for local testing
- Add `config/neuron.yaml` to `.gitignore` if it contains credentials
- Use environment variables in CI/CD (GitHub Secrets, GitLab CI Variables)
- Use different Cloudinary accounts for development and production

❌ **DON'T:**
- Commit API secrets to version control
- Share credentials in chat/email
- Use production credentials for testing
- Hard-code credentials in application code

## Skipping Cloudinary During Installation

If you choose **not** to configure Cloudinary during installation:

1. The installer will skip and remind you:
   ```
   Skipping Cloudinary configuration.
   You can add credentials later in config/neuron.yaml
   ```

2. You can add credentials later by:
   - Running the installer again
   - Manually editing `config/neuron.yaml`
   - Using environment variables for testing

## Free Tier Limits

Cloudinary's free tier includes:
- 25 GB storage
- 25 GB monthly bandwidth
- 25,000 transformations/month

This is sufficient for most small-to-medium websites and all development/testing needs.
