# CMS Authentication Implementation Plan

**Version**: 1.0
**Date**: 2025-11-05
**Component**: Neuron-PHP CMS (neuron-php/cms)
**Target Version**: 0.9.0

## Executive Summary

This document outlines the comprehensive plan for implementing authentication and authorization capabilities in the Neuron-PHP CMS component. The CMS currently lacks user authentication, making it suitable only for public content display. To support content management, admin panels, and API access, a robust authentication system is required.

### Goals

1. Enable secure admin access for content management
2. Provide API authentication for programmatic access
3. Support role-based access control (RBAC)
4. Integrate with existing routing and rate limiting systems
5. Maintain framework simplicity and PHP 8.4+ best practices

## Current State Analysis

### What Exists in Neuron Framework

#### Session Management
- **Location**: `patterns/src/Patterns/Singleton/Session.php`
- **Capabilities**:
  - Session singleton pattern
  - Serialize/deserialize to PHP `$_SESSION`
  - Session invalidation support
- **Limitations**: Basic implementation, no security features

#### Session Data Filtering
- **Location**: `data/src/Data/Filter/Session.php`
- **Capabilities**:
  - Sanitization of session data
  - Type-safe session access via `filter_var()`

#### Routing & Filters
- **Location**: `routing/src/Routing/Filter.php`
- **Capabilities**:
  - Pre/post request filtering
  - Per-route filter assignment
  - Global filter application
- **Use Case**: Perfect for authentication middleware

#### Rate Limiting
- **Location**: `routing/src/Routing/RateLimit/`
- **Capabilities**:
  - Per-IP and per-user rate limiting
  - Multiple storage backends (Redis, file, memory)
  - Configurable limits per route
- **Integration Opportunity**: Can limit by authenticated user

### What CMS Currently Lacks

- ❌ User management (create, read, update, delete users)
- ❌ Authentication (login, logout, session management)
- ❌ Authorization (role-based access control)
- ❌ Password management (hashing, reset, strength validation)
- ❌ Admin panel for content management
- ❌ API authentication for programmatic access
- ❌ Security features (CSRF protection, brute force prevention)
- ❌ Audit logging (who did what, when)

## Architecture Overview

### Recommended Approach: Hybrid Authentication

The CMS will support **two authentication modes** for different use cases:

1. **Session-Based Authentication** (Admin Panel)
   - For human users accessing the admin interface
   - Cookie-based sessions with CSRF protection
   - Traditional login/logout flows
   - "Remember me" functionality

2. **API Key Authentication** (Programmatic Access)
   - For applications and services
   - Header-based authentication (`X-API-Key`)
   - Integration with rate limiting
   - Support for tiered access (free/paid)

### High-Level Architecture

```
┌─────────────────────────────────────────────────────┐
│                   CMS Application                    │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌─────────────┐           ┌──────────────┐        │
│  │   Public     │           │    Admin     │        │
│  │   Routes     │           │    Routes    │        │
│  │  (No Auth)   │           │ (Requires    │        │
│  │              │           │  Session)    │        │
│  └─────────────┘           └──────┬───────┘        │
│                                    │                 │
│                            ┌───────▼────────┐       │
│                            │ Authentication │       │
│                            │     Filter     │       │
│                            └───────┬────────┘       │
│                                    │                 │
│  ┌─────────────┐           ┌──────▼───────┐        │
│  │     API      │           │ Authorization│        │
│  │   Routes     │───────────▶    Filter    │        │
│  │ (Requires    │           │   (RBAC)     │        │
│  │  API Key)    │           └──────────────┘        │
│  └─────────────┘                                    │
│                                                       │
│  ┌──────────────────────────────────────────────┐  │
│  │          Rate Limiting Filter                 │  │
│  │  (Per User/IP, Tier-based)                   │  │
│  └──────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

## Authentication Approaches Comparison

### Option 1: Session-Based Authentication

**Best For**: Admin panel, human users

**Pros**:
- Simple to implement
- Works with traditional web forms
- Server has full control over sessions
- Easy to invalidate (logout)
- Built-in CSRF protection

**Cons**:
- Requires server-side session storage
- Not suitable for distributed systems without sticky sessions
- Requires cookies (browser-dependent)

**Use Cases**:
- Admin dashboard login
- Content editor interface
- User management panel

### Option 2: API Key Authentication

**Best For**: API consumers, service-to-service

**Pros**:
- Stateless (no session storage needed)
- Simple for API consumers
- Easy to revoke/rotate keys
- Works across domains
- Integrates with rate limiting

**Cons**:
- Keys can be stolen if exposed
- No built-in expiration (must be implemented)
- Less secure than JWT for sensitive operations

**Use Cases**:
- Public API access
- Third-party integrations
- Mobile applications
- Webhooks

### Option 3: JWT Token Authentication

**Best For**: Modern SPAs, mobile apps

**Pros**:
- Stateless authentication
- Cross-domain support
- Contains user claims (roles, permissions)
- Short-lived with refresh tokens
- Industry standard

**Cons**:
- Cannot be revoked without blacklist
- More complex implementation
- Larger payload size
- Requires careful key management

**Use Cases**:
- Single-page applications (React, Vue)
- Mobile applications (iOS, Android)
- Microservices authentication
- Federated identity

### Recommendation: Hybrid (Session + API Key)

For the CMS component, implement **Session-Based** for admin panel and **API Key** for public API:

| Feature | Admin Panel | Public API |
|---------|-------------|------------|
| Auth Method | Session + Cookie | API Key (Header) |
| Storage | Server sessions | Database table |
| Expiration | Configurable timeout | No expiration (manual revoke) |
| Rate Limiting | Per user account | Per API key (tiered) |
| CSRF Protection | Yes | No (stateless) |
| Use Case | Content management | Programmatic access |

## Implementation Plan

### Phase 1: Core Authentication Infrastructure

**Estimated Time**: 2-3 weeks

#### 1.1 User Management System

**Create User Model**

```
cms/src/Cms/Models/User.php
```

**Features**:
- User entity with properties (id, username, email, password_hash, role, created_at, etc.)
- Password hashing using `password_hash()` with `PASSWORD_BCRYPT` or `PASSWORD_ARGON2ID`
- Email validation
- Username uniqueness enforcement

**User Properties**:
```php
class User
{
    private ?int $id;
    private string $username;
    private string $email;
    private string $passwordHash;
    private string $role;  // 'admin', 'editor', 'author', 'subscriber'
    private string $status; // 'active', 'inactive', 'suspended'
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastLoginAt;
    private ?string $rememberToken;
}
```

#### 1.2 User Repository/Storage

**Create User Repository**

```
cms/src/Cms/Repositories/UserRepository.php
cms/src/Cms/Repositories/IUserRepository.php
```

**Methods**:
- `findById(int $id): ?User`
- `findByUsername(string $username): ?User`
- `findByEmail(string $email): ?User`
- `findByRememberToken(string $token): ?User`
- `create(User $user): User`
- `update(User $user): bool`
- `delete(int $id): bool`
- `all(): array`

**Storage Options**:
1. **File-based** (JSON/YAML) - Simple, no dependencies
2. **Database** (PDO) - Scalable, relational
3. **Redis** (phpredis) - Fast, session-like

**Recommendation**: Start with **file-based** for simplicity, provide interface for future database adapter.

#### 1.3 Authentication Manager

**Create Auth Manager**

```
cms/src/Cms/Auth/AuthManager.php
```

**Responsibilities**:
- User authentication (username/password verification)
- Session creation and management
- Remember me token generation
- Login attempt tracking (brute force prevention)
- Logout functionality

**Methods**:
```php
class AuthManager
{
    public function attempt(string $username, string $password, bool $remember = false): bool
    public function login(User $user, bool $remember = false): void
    public function logout(): void
    public function check(): bool
    public function user(): ?User
    public function id(): ?int
    public function loginUsingRememberToken(string $token): bool
    public function validateCredentials(User $user, string $password): bool
}
```

#### 1.4 Password Management

**Create Password Hasher**

```
cms/src/Cms/Auth/PasswordHasher.php
```

**Features**:
- Hash passwords using `password_hash(PASSWORD_ARGON2ID)`
- Verify passwords using `password_verify()`
- Check if rehash needed (algorithm upgrade)
- Password strength validation

**Password Policy**:
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (optional, configurable)

#### 1.5 Session Manager

**Create Session Manager**

```
cms/src/Cms/Auth/SessionManager.php
```

**Features**:
- Session initialization
- Session regeneration (prevent fixation attacks)
- Flash messages (success, error, info)
- Session timeout handling
- Secure session configuration (httponly, secure, samesite)

### Phase 2: Admin Panel with Session-Based Authentication

**Estimated Time**: 2-3 weeks

#### 2.1 Authentication Controllers

**Login Controller**

```
cms/src/Cms/Controllers/Auth/LoginController.php
```

**Methods**:
- `showLoginForm()` - Display login page
- `login(Request $request)` - Process login
- `logout()` - Process logout

**Login Flow**:
1. User submits username/password
2. Validate CSRF token
3. Attempt authentication via AuthManager
4. Check rate limiting (prevent brute force)
5. On success: Create session, redirect to admin
6. On failure: Show error, increment attempt counter

**Register Controller** (Optional)

```
cms/src/Cms/Controllers/Auth/RegisterController.php
```

**Methods**:
- `showRegistrationForm()` - Display registration page
- `register(Request $request)` - Process registration

**Registration Flow**:
1. Validate input (username, email, password)
2. Check username/email uniqueness
3. Hash password
4. Create user with default role ('subscriber')
5. Auto-login or redirect to login

#### 2.2 Authentication Filter

**Create Auth Filter**

```
cms/src/Cms/Auth/Filters/AuthenticationFilter.php
```

**Extends**: `Neuron\Routing\Filter`

**Functionality**:
- Check if user is authenticated (session exists)
- If not authenticated: Redirect to login page
- If authenticated: Allow request to proceed
- Store intended URL for post-login redirect

**Usage in routes.yaml**:
```yaml
routes:
  - route: /admin/dashboard
    method: GET
    controller: Neuron\Cms\Controllers\Admin\DashboardController@index
    filter: auth  # Requires authentication
```

#### 2.3 Authorization Filter (RBAC)

**Create Authorization Filter**

```
cms/src/Cms/Auth/Filters/AuthorizationFilter.php
```

**Features**:
- Role-based access control
- Permission checking
- Configurable role hierarchy

**Roles**:
1. **Admin** - Full access to everything
2. **Editor** - Manage all content (posts, pages, media)
3. **Author** - Create and edit own posts
4. **Subscriber** - Read-only access (future use)

**Permissions Matrix**:

| Action | Admin | Editor | Author | Subscriber |
|--------|-------|--------|--------|------------|
| View posts | ✓ | ✓ | ✓ | ✓ |
| Create posts | ✓ | ✓ | ✓ | ✗ |
| Edit own posts | ✓ | ✓ | ✓ | ✗ |
| Edit all posts | ✓ | ✓ | ✗ | ✗ |
| Delete own posts | ✓ | ✓ | ✓ | ✗ |
| Delete all posts | ✓ | ✓ | ✗ | ✗ |
| Manage users | ✓ | ✗ | ✗ | ✗ |
| Manage settings | ✓ | ✗ | ✗ | ✗ |

#### 2.4 Admin Controllers

**Dashboard Controller**

```
cms/src/Cms/Controllers/Admin/DashboardController.php
```

**Methods**:
- `index()` - Show admin dashboard with stats
- Display: Total posts, recent activity, quick actions

**Post Management Controller**

```
cms/src/Cms/Controllers/Admin/PostController.php
```

**Methods**:
- `index()` - List all posts (with pagination, search, filters)
- `create()` - Show create post form
- `store(Request $request)` - Save new post
- `edit(int $id)` - Show edit post form
- `update(int $id, Request $request)` - Update post
- `destroy(int $id)` - Delete post

**User Management Controller**

```
cms/src/Cms/Controllers/Admin/UserController.php
```

**Methods**:
- `index()` - List all users
- `create()` - Show create user form
- `store(Request $request)` - Create new user
- `edit(int $id)` - Show edit user form
- `update(int $id, Request $request)` - Update user
- `destroy(int $id)` - Delete user

#### 2.5 Admin Views

**View Templates**

```
cms/resources/views/admin/
├── layouts/
│   └── admin.php                   # Admin layout with nav, sidebar
├── auth/
│   ├── login.php                   # Login form
│   └── register.php                # Registration form (optional)
├── dashboard/
│   └── index.php                   # Admin dashboard
├── posts/
│   ├── index.php                   # List posts
│   ├── create.php                  # Create post form
│   └── edit.php                    # Edit post form
└── users/
    ├── index.php                   # List users
    ├── create.php                  # Create user form
    └── edit.php                    # Edit user form
```

#### 2.6 CSRF Protection

**Create CSRF Token Manager**

```
cms/src/Cms/Auth/CsrfTokenManager.php
```

**Features**:
- Generate random CSRF tokens
- Store in session
- Validate on form submission
- Automatic token regeneration

**CSRF Filter**

```
cms/src/Cms/Auth/Filters/CsrfFilter.php
```

**Apply to**:
- All POST, PUT, DELETE requests
- Skip for API routes (use API key authentication instead)

**Usage in Views**:
```php
<form method="POST" action="/admin/posts">
    <?= csrf_field() ?>
    <!-- form fields -->
</form>
```

### Phase 3: API Authentication

**Estimated Time**: 1-2 weeks

#### 3.1 API Key Management

**Create API Key Model**

```
cms/src/Cms/Models/ApiKey.php
```

**Properties**:
```php
class ApiKey
{
    private ?int $id;
    private int $userId;  // Owner of this key
    private string $key;  // The actual API key (hashed)
    private string $name; // Friendly name ("Production Server", "Mobile App")
    private string $tier; // 'free', 'paid', 'premium', 'enterprise'
    private array $scopes; // ['read', 'write', 'delete']
    private bool $isActive;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastUsedAt;
    private ?\DateTimeImmutable $expiresAt; // Optional expiration
}
```

**Create API Key Repository**

```
cms/src/Cms/Repositories/ApiKeyRepository.php
```

**Methods**:
- `findByKey(string $key): ?ApiKey`
- `findByUserId(int $userId): array`
- `create(ApiKey $key): ApiKey`
- `update(ApiKey $key): bool`
- `delete(int $id): bool`
- `revoke(string $key): bool`

#### 3.2 API Key Manager

**Create API Key Manager**

```
cms/src/Cms/Auth/ApiKeyManager.php
```

**Methods**:
```php
class ApiKeyManager
{
    public function generate(int $userId, string $name, string $tier = 'free'): ApiKey
    public function validate(string $key): bool
    public function revoke(string $key): bool
    public function getUser(string $key): ?User
    public function recordUsage(string $key): void
    public function isExpired(ApiKey $key): bool
    public function getTier(string $key): string
}
```

**Key Generation**:
- Use `random_bytes(32)` for crypto-secure randomness
- Prefix with identifier (e.g., `nph_` for "neuron-php")
- Format: `nph_dev_aBcDeFgHiJkLmNoPqRsTuVwXyZ123456`
- Store hash in database (using `password_hash()`)

#### 3.3 API Authentication Filter

**Create API Key Filter**

```
cms/src/Cms/Auth/Filters/ApiKeyFilter.php
```

**Functionality**:
1. Extract API key from `X-API-Key` header or `api_key` query parameter
2. Validate key exists and is active
3. Check expiration
4. Record usage (last_used_at)
5. Set user context in Registry
6. If invalid: Return 401 Unauthorized with JSON response

**Response Format** (401):
```json
{
    "error": "unauthorized",
    "message": "Invalid or missing API key",
    "status": 401
}
```

#### 3.4 API Rate Limiting Integration

**Configure Tiered Rate Limits**

```yaml
# config/config.yaml
api_limit_free:
  enabled: true
  storage: redis
  requests: 100
  window: 3600  # 1 hour
  redis_host: ${REDISHOST}
  redis_auth: ${REDISPASSWORD}
  redis_port: ${REDISPORT}
  redis_database: 0

api_limit_paid:
  enabled: true
  storage: redis
  requests: 1000
  window: 3600

api_limit_premium:
  enabled: true
  storage: redis
  requests: 10000
  window: 3600

api_limit_enterprise:
  enabled: true
  storage: redis
  requests: 100000
  window: 3600
```

**Custom Rate Limit Filter**

```
cms/src/Cms/Auth/Filters/TieredRateLimitFilter.php
```

**Extends**: `Neuron\Routing\Filters\RateLimitFilter`

**Override `getLimit()` and `getWindow()` to apply tier-based limits**:

```php
protected function getLimit(string $key): int
{
    $user = $this->getUserFromKey($key);
    $tier = $user->getApiKey()->getTier();

    return match($tier) {
        'free' => 100,
        'paid' => 1000,
        'premium' => 10000,
        'enterprise' => PHP_INT_MAX,
        default => 100
    };
}
```

#### 3.5 API Key Management UI

**API Key Controller**

```
cms/src/Cms/Controllers/Admin/ApiKeyController.php
```

**Methods**:
- `index()` - List user's API keys
- `create()` - Show create API key form
- `store(Request $request)` - Generate new API key
- `revoke(int $id)` - Revoke/delete API key

**Views**:

```
cms/resources/views/admin/api-keys/
├── index.php   # List keys with usage stats
├── create.php  # Create new key form
└── show.php    # Display newly created key (show once!)
```

**Security Note**: Display the full API key **only once** upon creation. After that, show only a masked version (e.g., `nph_dev_****...***123456`).

### Phase 4: Advanced Features

**Estimated Time**: 2-4 weeks

#### 4.1 Two-Factor Authentication (2FA)

**TOTP-Based 2FA**

```
cms/src/Cms/Auth/TwoFactor/
├── TotpManager.php          # Generate/verify TOTP codes
├── QrCodeGenerator.php      # Generate QR codes for authenticator apps
└── BackupCodeManager.php    # Generate/verify backup codes
```

**Dependencies**: Consider using `spomky-labs/otphp` for TOTP implementation

**Features**:
- Enable/disable 2FA per user
- QR code generation for Google Authenticator, Authy, etc.
- Backup codes (10 single-use codes)
- Recovery mechanism if device lost

#### 4.2 Password Reset

**Password Reset Flow**

```
cms/src/Cms/Controllers/Auth/PasswordResetController.php
```

**Methods**:
- `showResetRequestForm()` - Email input form
- `sendResetLink(Request $request)` - Generate token, send email
- `showResetForm(string $token)` - New password form
- `reset(Request $request)` - Update password

**Password Reset Token**:
- Generate secure random token
- Store in database with expiration (1 hour)
- Send via email with reset link
- One-time use, expires after reset or timeout

#### 4.3 OAuth Integration

**OAuth Provider Support**

```
cms/src/Cms/Auth/OAuth/
├── Providers/
│   ├── GitHubProvider.php
│   ├── GoogleProvider.php
│   └── FacebookProvider.php
└── OAuthManager.php
```

**Consider using**: `league/oauth2-client` library

**Features**:
- "Sign in with Google/GitHub/Facebook"
- Link OAuth account to existing user
- Auto-create user from OAuth profile

#### 4.4 Audit Logging

**Audit Log System**

```
cms/src/Cms/Audit/
├── AuditLogger.php
├── AuditEntry.php
└── AuditRepository.php
```

**Log Events**:
- User login/logout
- Failed login attempts
- User CRUD operations
- Post CRUD operations
- Settings changes
- API key usage

**Audit Entry Properties**:
```php
class AuditEntry
{
    private int $id;
    private ?int $userId;
    private string $action;  // 'user.login', 'post.create', etc.
    private string $entity;  // 'User', 'Post', 'ApiKey'
    private ?int $entityId;
    private array $changes;  // Before/after values
    private string $ipAddress;
    private string $userAgent;
    private \DateTimeImmutable $createdAt;
}
```

#### 4.5 Account Lockout (Brute Force Prevention)

**Login Throttling**

```
cms/src/Cms/Auth/LoginThrottle.php
```

**Features**:
- Track failed login attempts per IP and username
- Lockout after X failed attempts (default: 5)
- Exponential backoff (1 min, 5 min, 15 min, 1 hour)
- CAPTCHA after 3 failed attempts
- Email notification on lockout

#### 4.6 Email Verification

**Email Verification**

```
cms/src/Cms/Auth/EmailVerification/
├── EmailVerificationManager.php
├── VerificationToken.php
└── Controllers/
    └── EmailVerificationController.php
```

**Flow**:
1. User registers
2. Generate verification token
3. Send email with verification link
4. User clicks link
5. Token validated, user account marked as verified
6. Redirect to dashboard with success message

#### 4.7 "Remember Me" Functionality

**Remember Me Token**

**Features**:
- Generate secure random token on login (if "remember me" checked)
- Store hashed token in database
- Set long-lived cookie (default: 30 days)
- Validate token on subsequent visits
- Auto-login if token valid
- Rotate token after each use

**Security**:
- Use separate token (not session ID)
- Hash token before storing
- Tie to user agent + IP (optional, but less user-friendly)
- Expire after X days or manual logout

## Directory Structure

### Complete File Tree

```
cms/
├── specs/
│   ├── authentication-implementation-plan.md  (this document)
│   └── api-specification.md                    (future)
├── src/
│   └── Cms/
│       ├── Auth/
│       │   ├── AuthManager.php
│       │   ├── PasswordHasher.php
│       │   ├── SessionManager.php
│       │   ├── ApiKeyManager.php
│       │   ├── CsrfTokenManager.php
│       │   ├── LoginThrottle.php
│       │   ├── Filters/
│       │   │   ├── AuthenticationFilter.php
│       │   │   ├── AuthorizationFilter.php
│       │   │   ├── ApiKeyFilter.php
│       │   │   ├── TieredRateLimitFilter.php
│       │   │   └── CsrfFilter.php
│       │   ├── TwoFactor/
│       │   │   ├── TotpManager.php
│       │   │   ├── QrCodeGenerator.php
│       │   │   └── BackupCodeManager.php
│       │   ├── OAuth/
│       │   │   ├── OAuthManager.php
│       │   │   └── Providers/
│       │   │       ├── GitHubProvider.php
│       │   │       ├── GoogleProvider.php
│       │   │       └── FacebookProvider.php
│       │   └── EmailVerification/
│       │       ├── EmailVerificationManager.php
│       │       └── VerificationToken.php
│       ├── Controllers/
│       │   ├── Auth/
│       │   │   ├── LoginController.php
│       │   │   ├── RegisterController.php
│       │   │   ├── PasswordResetController.php
│       │   │   └── EmailVerificationController.php
│       │   ├── Admin/
│       │   │   ├── DashboardController.php
│       │   │   ├── PostController.php
│       │   │   ├── UserController.php
│       │   │   ├── ApiKeyController.php
│       │   │   └── SettingsController.php
│       │   ├── Blog.php  (existing)
│       │   └── Content.php  (existing)
│       ├── Models/
│       │   ├── User.php
│       │   ├── ApiKey.php
│       │   ├── Role.php
│       │   ├── Permission.php
│       │   └── AuditEntry.php
│       ├── Repositories/
│       │   ├── IUserRepository.php
│       │   ├── UserRepository.php
│       │   ├── IApiKeyRepository.php
│       │   ├── ApiKeyRepository.php
│       │   └── AuditRepository.php
│       ├── Audit/
│       │   ├── AuditLogger.php
│       │   └── AuditEntry.php
│       └── Middleware/  (alias for Filters)
│           └── ... (same as Auth/Filters/)
├── resources/
│   └── views/
│       └── admin/
│           ├── layouts/
│           │   ├── admin.php
│           │   └── auth.php
│           ├── auth/
│           │   ├── login.php
│           │   ├── register.php
│           │   ├── forgot-password.php
│           │   └── reset-password.php
│           ├── dashboard/
│           │   └── index.php
│           ├── posts/
│           │   ├── index.php
│           │   ├── create.php
│           │   └── edit.php
│           ├── users/
│           │   ├── index.php
│           │   ├── create.php
│           │   └── edit.php
│           └── api-keys/
│               ├── index.php
│               ├── create.php
│               └── show.php
├── config/
│   ├── auth.yaml       (new - auth configuration)
│   └── config.yaml     (existing - add auth settings)
├── storage/
│   └── users/
│       └── users.json  (file-based user storage)
└── tests/
    └── Cms/
        └── Auth/
            ├── AuthManagerTest.php
            ├── PasswordHasherTest.php
            ├── ApiKeyManagerTest.php
            └── Filters/
                ├── AuthenticationFilterTest.php
                └── ApiKeyFilterTest.php
```

## Database Schema

### Users Table

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'subscriber',  -- admin, editor, author, subscriber
    status VARCHAR(20) DEFAULT 'active',    -- active, inactive, suspended
    email_verified BOOLEAN DEFAULT 0,
    remember_token VARCHAR(100),
    two_factor_secret VARCHAR(255),
    two_factor_recovery_codes TEXT,  -- JSON array
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    last_login_at DATETIME
);

CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_remember_token ON users(remember_token);
```

### API Keys Table

```sql
CREATE TABLE api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    key_hash VARCHAR(255) NOT NULL UNIQUE,
    key_prefix VARCHAR(20) NOT NULL,  -- First few chars for display
    tier VARCHAR(20) DEFAULT 'free',  -- free, paid, premium, enterprise
    scopes TEXT,  -- JSON array ['read', 'write', 'delete']
    is_active BOOLEAN DEFAULT 1,
    last_used_at DATETIME,
    created_at DATETIME NOT NULL,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_api_keys_user_id ON api_keys(user_id);
CREATE INDEX idx_api_keys_key_hash ON api_keys(key_hash);
```

### Password Reset Tokens Table

```sql
CREATE TABLE password_reset_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL
);

CREATE INDEX idx_password_reset_tokens_email ON password_reset_tokens(email);
CREATE INDEX idx_password_reset_tokens_token ON password_reset_tokens(token);
```

### Email Verification Tokens Table

```sql
CREATE TABLE email_verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_email_verification_tokens_user_id ON email_verification_tokens(user_id);
CREATE INDEX idx_email_verification_tokens_token ON email_verification_tokens(token);
```

### Audit Log Table

```sql
CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,  -- NULL for anonymous/system actions
    action VARCHAR(100) NOT NULL,  -- user.login, post.create, etc.
    entity VARCHAR(50),  -- User, Post, ApiKey, etc.
    entity_id INTEGER,
    changes TEXT,  -- JSON: {"before": {...}, "after": {...}}
    ip_address VARCHAR(45),  -- IPv6 compatible
    user_agent TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX idx_audit_log_action ON audit_log(action);
CREATE INDEX idx_audit_log_entity ON audit_log(entity, entity_id);
CREATE INDEX idx_audit_log_created_at ON audit_log(created_at);
```

### Roles and Permissions Tables (Optional - for granular RBAC)

```sql
CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,  -- posts.create, users.delete, etc.
    description TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE role_permissions (
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE user_permissions (
    user_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

## Configuration

### Authentication Configuration

**File**: `config/auth.yaml`

```yaml
auth:
  # Authentication driver (session, api_key, jwt)
  default: session

  # Session configuration
  session:
    lifetime: 120  # minutes
    expire_on_close: false
    encrypt: false
    cookie_name: neuron_session
    cookie_httponly: true
    cookie_secure: true  # HTTPS only
    cookie_samesite: lax

  # Remember me configuration
  remember:
    enabled: true
    lifetime: 43200  # 30 days in minutes

  # Password configuration
  passwords:
    min_length: 8
    require_uppercase: true
    require_lowercase: true
    require_numbers: true
    require_special_chars: false
    hash_algorithm: argon2id  # argon2id or bcrypt

  # Login throttling
  throttle:
    enabled: true
    max_attempts: 5
    decay_minutes: 1
    lockout_duration: 15  # minutes

  # Two-factor authentication
  two_factor:
    enabled: false
    issuer: "Neuron CMS"

  # API key configuration
  api_keys:
    enabled: true
    header_name: X-API-Key
    query_param_name: api_key
    prefix: nph_  # API key prefix
    hash_algorithm: bcrypt

  # Email verification
  email_verification:
    enabled: false
    token_lifetime: 60  # minutes

  # Audit logging
  audit:
    enabled: true
    log_successful_logins: true
    log_failed_logins: true
    log_api_requests: false  # Can be noisy
```

### Rate Limiting Configuration

**File**: `config/config.yaml` (add to existing)

```yaml
# API Rate Limiting by Tier
api_limit_free:
  enabled: true
  storage: redis
  requests: 100
  window: 3600
  redis_host: ${REDISHOST}
  redis_auth: ${REDISPASSWORD}
  redis_port: ${REDISPORT}
  redis_database: 0

api_limit_paid:
  enabled: true
  storage: redis
  requests: 1000
  window: 3600
  redis_host: ${REDISHOST}
  redis_auth: ${REDISPASSWORD}
  redis_port: ${REDISPORT}
  redis_database: 0

api_limit_premium:
  enabled: true
  storage: redis
  requests: 10000
  window: 3600
  redis_host: ${REDISHOST}
  redis_auth: ${REDISPASSWORD}
  redis_port: ${REDISPORT}
  redis_database: 0
```

### Routes Configuration

**File**: `config/routes.yaml` (examples)

```yaml
routes:
  # Public blog routes (no auth required)
  - route: /blog
    method: GET
    controller: Neuron\Cms\Controllers\Blog@index

  - route: /blog/article/:title
    method: GET
    controller: Neuron\Cms\Controllers\Blog@show

  # Authentication routes
  - route: /login
    method: GET
    controller: Neuron\Cms\Controllers\Auth\LoginController@showLoginForm

  - route: /login
    method: POST
    controller: Neuron\Cms\Controllers\Auth\LoginController@login
    filter: csrf

  - route: /logout
    method: POST
    controller: Neuron\Cms\Controllers\Auth\LoginController@logout
    filter: auth,csrf

  # Admin routes (require authentication)
  - route: /admin/dashboard
    method: GET
    controller: Neuron\Cms\Controllers\Admin\DashboardController@index
    filter: auth

  - route: /admin/posts
    method: GET
    controller: Neuron\Cms\Controllers\Admin\PostController@index
    filter: auth

  - route: /admin/posts/create
    method: GET
    controller: Neuron\Cms\Controllers\Admin\PostController@create
    filter: auth

  - route: /admin/posts
    method: POST
    controller: Neuron\Cms\Controllers\Admin\PostController@store
    filter: auth,csrf

  - route: /admin/users
    method: GET
    controller: Neuron\Cms\Controllers\Admin\UserController@index
    filter: auth,authorize:admin  # Admin role required

  # API routes (require API key)
  - route: /api/v1/posts
    method: GET
    controller: Neuron\Cms\Controllers\Api\PostController@index
    filter: api_key,api_limit_free

  - route: /api/v1/posts/:id
    method: GET
    controller: Neuron\Cms\Controllers\Api\PostController@show
    filter: api_key,api_limit_free
```

## Security Considerations

### 1. Password Security

**Best Practices**:
- ✅ Use `password_hash()` with `PASSWORD_ARGON2ID` (more secure than bcrypt)
- ✅ Never store plain-text passwords
- ✅ Implement password strength requirements
- ✅ Use `password_verify()` for comparison (timing-attack safe)
- ✅ Rehash passwords if algorithm changes (`password_needs_rehash()`)

**Avoid**:
- ❌ MD5 or SHA1 hashing (broken)
- ❌ Custom encryption schemes
- ❌ Predictable salts

### 2. Session Security

**Best Practices**:
- ✅ Regenerate session ID on login (`session_regenerate_id(true)`)
- ✅ Use `httponly` flag (prevent XSS access to cookies)
- ✅ Use `secure` flag for HTTPS
- ✅ Set `samesite` to `lax` or `strict` (CSRF protection)
- ✅ Implement session timeout
- ✅ Destroy session on logout

**Session Configuration**:
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // HTTPS only
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
session_set_cookie_params([
    'lifetime' => 7200,  // 2 hours
    'path' => '/',
    'domain' => '.example.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

### 3. CSRF Protection

**Best Practices**:
- ✅ Generate unique token per session
- ✅ Validate token on all state-changing requests (POST, PUT, DELETE)
- ✅ Use double-submit cookie pattern or synchronizer token pattern
- ✅ Expire tokens after use (optional, depends on UX)

**Token Generation**:
```php
function generateCsrfToken(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function validateCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}
```

### 4. SQL Injection Prevention

**Best Practices**:
- ✅ Use prepared statements with parameterized queries
- ✅ Never concatenate user input into SQL
- ✅ Use ORM or query builder when possible

**Example** (PDO):
```php
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username');
$stmt->execute(['username' => $username]);
```

### 5. XSS Prevention

**Best Practices**:
- ✅ Escape all user-generated content in views
- ✅ Use `htmlspecialchars()` with `ENT_QUOTES` and `UTF-8`
- ✅ Set `Content-Security-Policy` header
- ✅ Sanitize HTML if rich text is allowed (use library like HTML Purifier)

**Example**:
```php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// In view:
<p><?= e($userInput) ?></p>
```

### 6. API Key Security

**Best Practices**:
- ✅ Generate keys using `random_bytes()` (cryptographically secure)
- ✅ Store hashed keys (use `password_hash()`)
- ✅ Use HTTPS for all API requests
- ✅ Implement rate limiting
- ✅ Allow key rotation
- ✅ Log API key usage

**Key Format**:
```
nph_dev_aBcDeFgHiJkLmNoPqRsTuVwXyZ123456
│    │   └────────────┬────────────────────┘
│    │                └─ Random 32-byte string (hex)
│    └─ Environment (dev, prod, test)
└─ Prefix (neuron-php)
```

### 7. Brute Force Protection

**Best Practices**:
- ✅ Implement login throttling (max X attempts per Y minutes)
- ✅ Exponential backoff on failed attempts
- ✅ CAPTCHA after N failed attempts
- ✅ Account lockout (temporary)
- ✅ Email notification on lockout
- ✅ Log all failed login attempts

**Rate Limiting**:
```php
// Track failed attempts by IP + username
$key = "login_attempts:{$ip}:{$username}";
$attempts = Redis::incr($key);
Redis::expire($key, 900);  // 15 minutes

if ($attempts > 5) {
    throw new TooManyAttemptsException();
}
```

### 8. Headers Security

**Recommended Headers**:
```php
// Prevent XSS
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';");

// HTTPS enforcement
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Referrer policy
header("Referrer-Policy: strict-origin-when-cross-origin");
```

### 9. Input Validation

**Best Practices**:
- ✅ Validate all user input (whitelist approach)
- ✅ Use type hints and strict types in PHP 8.4
- ✅ Sanitize input before processing
- ✅ Validate on both client and server side
- ✅ Return meaningful error messages (but don't leak sensitive info)

**Example**:
```php
function validateUsername(string $username): bool
{
    // Allow only alphanumeric, underscore, hyphen (3-20 chars)
    return (bool)preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username);
}
```

### 10. Error Handling

**Best Practices**:
- ✅ Never display stack traces in production
- ✅ Log errors securely (without sensitive data)
- ✅ Return generic error messages to users
- ✅ Implement custom error pages (404, 500, etc.)

**Example**:
```php
// Production
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    set_error_handler('customErrorHandler');
}
```

## Testing Strategy

### Unit Tests

**Test Coverage Requirements**: 80%+

**Priority Areas**:
1. **Authentication Logic** - `AuthManager`, `PasswordHasher`
2. **Password Hashing** - Verify `password_hash()` and `password_verify()`
3. **CSRF Token Generation/Validation**
4. **API Key Generation/Validation**
5. **Session Management** - Session creation, regeneration, destruction
6. **Input Validation** - Username, email, password validation
7. **Rate Limiting** - Throttle logic

**Example Test**:
```php
class PasswordHasherTest extends TestCase
{
    public function testHashPassword()
    {
        $hasher = new PasswordHasher();
        $password = 'SecurePassword123!';

        $hash = $hasher->hash($password);

        $this->assertNotEquals($password, $hash);
        $this->assertTrue($hasher->verify($password, $hash));
    }

    public function testPasswordStrengthValidation()
    {
        $hasher = new PasswordHasher();

        $this->assertTrue($hasher->meetsRequirements('StrongPass1!'));
        $this->assertFalse($hasher->meetsRequirements('weak'));
        $this->assertFalse($hasher->meetsRequirements('NoNumbers!'));
    }
}
```

### Integration Tests

**Test Scenarios**:
1. **Login Flow** - Submit credentials, verify session created
2. **Logout Flow** - Logout, verify session destroyed
3. **Registration Flow** - Create user, verify stored correctly
4. **API Authentication** - Send request with API key, verify accepted
5. **Rate Limiting** - Exceed limit, verify 429 response
6. **CSRF Protection** - Submit form without token, verify rejected

### Functional Tests

**Test User Journeys**:
1. User registers → verifies email → logs in → creates post → logs out
2. Admin logs in → manages users → creates API key → tests API
3. User exceeds rate limit → receives 429 → waits → retry succeeds

### Security Tests

**Penetration Testing**:
1. **SQL Injection** - Attempt SQL injection in login form
2. **XSS** - Submit malicious scripts in content
3. **CSRF** - Submit form from different domain
4. **Brute Force** - Attempt rapid login attempts
5. **Session Fixation** - Try to reuse old session IDs
6. **Password Cracking** - Verify strong hashing (can't be reversed)

**Tools**:
- OWASP ZAP (automated security scanning)
- Burp Suite (manual penetration testing)
- `sqlmap` (SQL injection testing)

## Migration Path

### For New Installations

1. Install CMS component: `composer require neuron-php/cms:^0.9`
2. Run database migrations: `php neuron migrate`
3. Create admin user: `php neuron user:create admin --admin`
4. Configure authentication in `config/auth.yaml`
5. Access admin panel at `/login`

### For Existing Installations (Upgrade from 0.8.x)

**Step 1: Backup**
```bash
# Backup database
cp storage/database.db storage/database.db.backup

# Backup configuration
cp -r config config.backup
```

**Step 2: Update Dependencies**
```bash
composer update neuron-php/cms
```

**Step 3: Run Migrations**
```bash
php neuron migrate
```

**Step 4: Create Admin User**
```bash
php neuron user:create admin --role=admin
```

**Step 5: Update Configuration**
```bash
# Add auth configuration to config/config.yaml
cat >> config/config.yaml << 'EOF'

# Authentication
auth:
  session:
    lifetime: 120
  passwords:
    min_length: 8
EOF
```

**Step 6: Update Routes**
```yaml
# Add to config/routes.yaml
routes:
  # ... existing routes ...

  # Authentication routes
  - route: /login
    method: GET
    controller: Neuron\Cms\Controllers\Auth\LoginController@showLoginForm

  - route: /login
    method: POST
    controller: Neuron\Cms\Controllers\Auth\LoginController@login
```

**Step 7: Test**
```bash
# Start dev server
php -S localhost:8000 -t public

# Test login at http://localhost:8000/login
```

## Development Roadmap

### Version 0.9.0 (Phase 1 + 2)
- ✅ User management system
- ✅ Session-based authentication
- ✅ Admin panel with login/logout
- ✅ Password hashing and validation
- ✅ CSRF protection
- ✅ Basic authorization (roles: admin, editor, author)
- ✅ Post management (CRUD) in admin panel
- ✅ User management in admin panel

**Release Date**: Q1 2026

### Version 0.10.0 (Phase 3)
- ✅ API key authentication
- ✅ API key management UI
- ✅ Tiered rate limiting for APIs
- ✅ API endpoints for posts (CRUD)
- ✅ API documentation

**Release Date**: Q2 2026

### Version 0.11.0 (Phase 4 - Part 1)
- ✅ Password reset functionality
- ✅ Email verification
- ✅ Account lockout (brute force prevention)
- ✅ Audit logging
- ✅ "Remember me" functionality

**Release Date**: Q3 2026

### Version 0.12.0 (Phase 4 - Part 2)
- ✅ Two-factor authentication (TOTP)
- ✅ OAuth integration (Google, GitHub, Facebook)
- ✅ Granular permissions system
- ✅ Advanced audit reports

**Release Date**: Q4 2026

### Version 1.0.0 (Stable Release)
- ✅ Complete documentation
- ✅ Security audit
- ✅ Performance optimization
- ✅ Production-ready

**Release Date**: Q1 2027

## Success Criteria

### Must Have (Version 0.9.0)
- [x] Users can register and login
- [x] Admin panel is protected by authentication
- [x] Passwords are securely hashed
- [x] CSRF protection works on all forms
- [x] Sessions expire after configured timeout
- [x] Admin can create/edit/delete posts via UI
- [x] Admin can manage users (create, edit, delete)
- [x] Role-based access control (admin vs editor)

### Should Have (Version 0.10.0)
- [ ] API authentication via API keys
- [ ] API keys can be created/revoked via admin panel
- [ ] Rate limiting works per API key tier
- [ ] API documentation is available

### Nice to Have (Version 0.11.0+)
- [ ] Password reset via email
- [ ] Email verification on registration
- [ ] Two-factor authentication
- [ ] OAuth login (Google, GitHub)
- [ ] Audit logging for all actions

## References and Resources

### Neuron Framework Documentation
- Routing: http://neuronphp.com/routing
- MVC: http://neuronphp.com/mvc
- Patterns: http://neuronphp.com/patterns

### Security Best Practices
- OWASP Top 10: https://owasp.org/www-project-top-ten/
- PHP Security Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html
- Password Storage Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html

### Libraries to Consider
- Password Hashing: Native PHP `password_hash()` (Argon2id)
- CSRF Protection: Custom implementation
- 2FA: `spomky-labs/otphp`
- OAuth: `league/oauth2-client`
- Email: `symfony/mailer` or `phpmailer/phpmailer`

### Testing Resources
- PHPUnit: https://phpunit.de/
- OWASP ZAP: https://www.zaproxy.org/
- Burp Suite: https://portswigger.net/burp

## Appendix

### A. Example User Registration Flow

```php
// RegisterController.php
public function register(Request $request): string
{
    // 1. Validate input
    $username = $request->getParameter('username')->getValue();
    $email = $request->getParameter('email')->getValue();
    $password = $request->getParameter('password')->getValue();

    // 2. Check uniqueness
    if ($this->userRepository->findByUsername($username)) {
        throw new ValidationException('Username already taken');
    }

    // 3. Validate password strength
    if (!$this->passwordHasher->meetsRequirements($password)) {
        throw new ValidationException('Password does not meet requirements');
    }

    // 4. Create user
    $user = new User();
    $user->setUsername($username);
    $user->setEmail($email);
    $user->setPasswordHash($this->passwordHasher->hash($password));
    $user->setRole('subscriber');
    $user->setStatus('active');

    $this->userRepository->create($user);

    // 5. Send verification email (if enabled)
    if ($this->config->get('email_verification', 'enabled')) {
        $this->emailVerification->sendVerificationEmail($user);
    }

    // 6. Auto-login or redirect
    $this->authManager->login($user);

    return $this->redirect('/dashboard');
}
```

### B. Example API Key Authentication

```php
// ApiKeyFilter.php
public function pre(RouteMap $route): void
{
    // 1. Extract API key from header
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

    if (!$apiKey) {
        $this->respondUnauthorized('Missing API key');
    }

    // 2. Validate key
    if (!$this->apiKeyManager->validate($apiKey)) {
        $this->respondUnauthorized('Invalid API key');
    }

    // 3. Check expiration
    $key = $this->apiKeyRepository->findByKey($apiKey);
    if ($this->apiKeyManager->isExpired($key)) {
        $this->respondUnauthorized('API key expired');
    }

    // 4. Check active status
    if (!$key->isActive()) {
        $this->respondUnauthorized('API key inactive');
    }

    // 5. Record usage
    $this->apiKeyManager->recordUsage($apiKey);

    // 6. Set user context in Registry
    $user = $this->userRepository->findById($key->getUserId());
    Registry::getInstance()->set('User.Id', $user->getId());
    Registry::getInstance()->set('User.Tier', $key->getTier());
}

private function respondUnauthorized(string $message): void
{
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'error' => 'unauthorized',
        'message' => $message,
        'status' => 401
    ]);
    exit;
}
```

### C. Example Rate Limiting by Tier

```php
// TieredRateLimitFilter.php
protected function getLimit(string $key): int
{
    // Extract tier from Registry (set by API key filter)
    $tier = Registry::getInstance()->get('User.Tier') ?? 'free';

    return match($tier) {
        'free' => 100,
        'paid' => 1000,
        'premium' => 10000,
        'enterprise' => PHP_INT_MAX,
        default => 100
    };
}

protected function getWindow(string $key): int
{
    // 1 hour window for all tiers
    return 3600;
}
```

---

**Document Version**: 1.0
**Last Updated**: 2025-11-05
**Author**: Lee Jones
**Status**: Draft / Approved / In Progress / Implemented
