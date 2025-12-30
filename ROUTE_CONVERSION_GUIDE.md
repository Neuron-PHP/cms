# CMS Route Conversion Guide

## Completed Conversions

### Auth Controllers ✅
- `Auth/Login.php` - 3 routes converted
- `Auth/PasswordReset.php` - 4 routes converted

## Remaining Conversions

### Admin Controllers (needs RouteGroup)

All Admin controllers should use:
```php
use Neuron\Routing\Attributes\RouteGroup;

#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class ControllerName extends BaseController
```

**Admin/Dashboard.php** (2 routes)
- GET `/dashboard` → `index()`
- Also handle GET `/` (redirect or duplicate route)

**Admin/Users.php** (6 routes)
- GET `/users` → `index()`
- GET `/users/create` → `create()`
- POST `/users` → `store()` #[filters: ['csrf']]
- GET `/users/:id/edit` → `edit()`
- PUT `/users/:id` → `update()` #[filters: ['csrf']]
- DELETE `/users/:id` → `destroy()` #[filters: ['csrf']]

**Admin/Profile.php** (2 routes)
- GET `/profile` → `edit()`
- PUT `/profile` → `update()` #[filters: ['csrf']]

**Admin/Posts.php** (6 routes - same pattern as Users)
**Admin/Categories.php** (6 routes - same pattern as Users)
**Admin/Tags.php** (6 routes - same pattern as Users)
**Admin/Pages.php** (6 routes - same pattern as Users)

**Admin/Media.php** (3 routes)
- GET `/media` → `index()`
- POST `/upload/image` → `uploadImage()` #[filters: ['csrf']]
- POST `/upload/featured-image` → `uploadFeaturedImage()` #[filters: ['csrf']]

**Admin/Events.php** (6 routes - same pattern as Users)
**Admin/EventCategories.php** (6 routes - same pattern as Users)

### Public Controllers

**Home.php** ✅ Already done
- GET `/` → `index()`

**Blog.php** (6 routes)
- GET `/blog` → `index()`
- GET `/blog/post/:slug` → `show()`
- GET `/blog/category/:slug` → `category()`
- GET `/blog/tag/:slug` → `tag()`
- GET `/blog/author/:username` → `author()`
- GET `/rss` → `feed()`

**Pages.php** (1 route)
- GET `/pages/:slug` → `show()`

**Calendar.php** (3 routes)
- GET `/calendar` → `index()`
- GET `/calendar/event/:slug` → `show()`
- GET `/calendar/category/:slug` → `category()`

### Member Controllers (needs RouteGroup)

```php
#[RouteGroup(prefix: '/member', filters: ['member'])]
class ControllerName extends BaseController
```

**Member/Registration.php** (5 routes - NO RouteGroup, these are public)
- GET `/register` → `showRegistrationForm()`
- POST `/register` → `processRegistration()`
- GET `/verify-email` → `verify()`
- GET `/verify-email-sent` → `showVerificationSent()`
- POST `/resend-verification` → `resendVerification()` #[filters: ['csrf']]

**Member/Dashboard.php** (2 routes)
- GET `/dashboard` → `index()`
- Also handle GET `/` (use RouteGroup prefix)

**Member/Profile.php** (2 routes)
- GET `/profile` → `edit()`
- PUT `/profile` → `update()` #[filters: ['csrf']]

## Conversion Pattern

### Step 1: Add imports
```php
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup; // if needed
```

### Step 2: Add RouteGroup (if applicable)
```php
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class UsersController extends BaseController
```

### Step 3: Add route attributes to methods
```php
#[Get('/users', name: 'admin.users')]
public function index() { }

#[Post('/users', name: 'admin.users.store', filters: ['csrf'])]
public function store() { }
```

### Step 4: Handle filter composition
- RouteGroup filters apply to ALL routes
- Method-level filters ADD to group filters
- Example: RouteGroup['auth'] + Method['csrf'] = ['auth', 'csrf']

## Filter Reference

- `auth` - Admin authentication
- `member` - Member authentication
- `csrf` - CSRF protection for POST/PUT/DELETE
- Combined: `['auth', 'csrf']` for authenticated state-changing operations

## Testing

After conversion:
1. Delete `resources/config/routes.yaml`
2. Run tests: `./vendor/bin/phpunit tests`
3. Verify all routes load: Check application logs
4. Test critical flows: Login, CRUD operations, public pages
