# Neuron CMS Upgrade Notes

This file contains version-specific upgrade information, breaking changes, and migration instructions.

## How to Upgrade

After running `composer update neuron-php/cms`, follow these steps:

1. **Run the upgrade command:**
   ```bash
   ./vendor/bin/neuron cms:upgrade
   ```

2. **Review and apply migrations:**
   The upgrade command will detect new migrations. Review them and run:
   ```bash
   ./vendor/bin/neuron db:migrate
   ```

3. **Clear caches:**
   ```bash
   ./vendor/bin/neuron cache:clear  # if applicable
   ```

4. **Test your application** to ensure compatibility with the new version.

---

## Unreleased (auth.yaml is loaded)

### Fixed

- **`config/auth.yaml` was never read.** Only `neuron.yaml`, the environment
  file and the secrets reached the settings, so `auth.throttle`,
  `auth.remember`, `auth.passwords`, `auth.admin_roles` and the `member:`
  block in `auth.yaml` had no effect. The CMS now loads `auth.yaml` at boot,
  underneath those layers: the same key in `neuron.yaml` or
  `environments/<env>.yaml` still wins.
- **`auth.throttle` is applied.** `max_attempts` and `lockout_duration` set
  the login lockout (previously fixed at 5 and 15). `enabled: false` turns
  lockout off.
- **`auth.passwords` is applied** to the web password checks, and the
  `cms:user:create` and `cms:user:reset-password` commands. The reset command
  previously read it incorrectly.
- **`auth.session` is applied.** `lifetime` (minutes) sets both the cookie
  and the server-side session lifetime (`session.gc_maxlifetime`);
  `expire_on_close`, `cookie_name`, `cookie_httponly`, `cookie_secure` and
  `cookie_samesite` are honoured. `cookie_secure` now accepts `auto` (secure
  on HTTPS requests, the previous behaviour). The top-level
  `session.lifetime` (seconds) in `neuron.yaml` is still used when
  `auth.session.lifetime` is not set.
- **`auth.passwords.hash_algorithm` is applied** (`argon2id` or `bcrypt`).
  Switching re-hashes each user's password at their next login.

### Removed

- `auth.default` and `auth.storage` were dropped from the `auth.yaml`
  template. Nothing used them; users are always stored in the database. You
  can delete them from your copy.

### Upgrade notes

- **Set `auth.session.cookie_secure` to `auto`** (or remove it) unless every
  environment, including local development, is served over HTTPS. Older
  `auth.yaml` copies say `true`, which now forces secure cookies and breaks
  login over plain `http://`. Behind a TLS-terminating proxy that doesn't set
  `HTTPS`, set `cookie_secure: true` in `environments/production.yaml`.
- The session cookie is renamed to `cookie_name` (`neuron_session` in the
  shipped file; it was PHP's `PHPSESSID`). Signed-in users are signed out
  once after the upgrade; "remember me" signs them back in.
- Review your `config/auth.yaml` before upgrading: its values now take
  effect. Sites using the shipped values see no change, because they match
  the built-in defaults. Check `member.registration_enabled` in particular if
  `neuron.yaml` has no `member:` block.

## Unreleased (homepage controller filters)

### Fixed

- **`homepage.mode: controller` skipped the target's route filters.** The
  Home controller called the configured action directly, so filters on that
  action's own route (`member`, `auth`, `admin`, ...) never ran. Pointing the
  homepage at a member-only page let logged-out visitors in. When the action
  has a GET route, the homepage now runs that route, filters included;
  logged-out visitors are redirected to login exactly as on the page's own
  URL. Actions without a route are still called directly.

### Upgrade notes

- No configuration changes. If you worked around this with a `'/'` rewrite in
  `routing.yaml`, the rewrite keeps working.

---

## Unreleased (feature switches)

### Added

- **Optional CMS areas can be switched off in `neuron.yaml`.** A new
  `features:` section takes `true`/`false` for `blog`, `calendar`, `contact`,
  `payments`, `store`, `teams`, `carousels`, `testimonials` and `faqs`:

  ```yaml
  features:
    store: false
    payments: false
  ```

  A switched-off area's public and admin URLs return 404, it disappears from
  the admin menu and dashboard, its shortcodes render nothing, and it is left
  out of the sitemap. Its tables and content are untouched. The payments
  webhook stays reachable while either `payments` or `store` is on, since
  store orders complete through it.

### Upgrade notes

- **Nothing changes unless you add the section.** Missing keys count as on.
  See `config/neuron.yaml.example` after `cms:upgrade` for the full list.
- **Customised admin layout or dashboard.** The admin Content menu is now
  built from `Neuron\Cms\Admin\Navigation` (group `content`). If your site
  publishes its own `layouts/admin.php` or `admin/dashboard/index.php`,
  switched-off areas still show there until you re-publish those views or
  apply the same change. Their URLs return 404 regardless.
- **Typos are logged.** Unknown keys under `features:` produce a warning in
  the application log at startup.

---

## Unreleased (remember me)

### Fixed

- **"Remember me" kept signing people out.** Each user had a single token in
  `users.remember_token`, so:
  - signing in with "remember me" on a second browser invalidated the first;
  - signing out on any browser cleared it on all of them;
  - the token was replaced every time the cookie restored a session. When
    several requests arrived without a session at once (after a deploy, or
    once the PHP session expired), the browser could end up holding a token
    that no longer matched the stored one.

  Tokens now live in a new `remember_tokens` table, one row per browser.
  A token is no longer replaced on use, signing out removes only that
  browser's token, and `auth.remember.lifetime` and `auth.remember.enabled`
  are honoured (the cookie was previously fixed at 30 days).

### Migration Required

- `20261031000000_create_remember_tokens_table.php`. Run `cms:upgrade` and
  then `db:migrate`. Until the migration has run, sign-in still works but
  "remember me" is skipped and an error is logged.

### Compatibility

- Existing remember cookies keep working: a token still in
  `users.remember_token` moves to `remember_tokens` the first time it is
  used. The column is left in place.
- `Authentication` takes an optional fourth constructor argument,
  `IRememberTokenRepository`. The container supplies it; code that builds
  `Authentication` by hand without it has no "remember me".
- `CleanupExpiredTokensJob` also purges expired remember tokens.

---

## Unreleased (web components)

Requires `neuron-php/mvc` and `neuron-php/jobs` releases that include
`Neuron\Mvc\Components` and `Neuron\Jobs\Schedule\ScheduleFile`.

### New Features

- **Web component discovery.** Packages that declare `extra.neuron.mvc-provider`
  in their `composer.json` are loaded by the MVC application at boot. Their
  routes are registered after the site's own, their views are appended after
  the site's, and they can add entries to the admin menu through
  `Neuron\Mvc\Components\INavigationProvider`.
- **System menu.** The admin layout gains a *System* dropdown (next to *Users*)
  listing Scheduled Jobs plus whatever installed components contribute to the
  `system` group. The dashboard's *Users & Settings* tiles come from the same
  list. Entries whose route is not registered are dropped automatically.
- **Scheduled Jobs page.** Now built on `Neuron\Jobs\Schedule\ScheduleFile`,
  flags job classes that cannot be autoloaded, and, when
  `queue.schedule_lock: database` is set, shows *Last Triggered* from the
  `schedule_locks` table.
- `components.discovery` / `components.disabled` in `neuron.yaml` (see
  `neuron.yaml.example`) turn component loading off or skip named providers.
- `routes:list` includes component routes and shows a *Source* column when
  any are present (`--source=` filters by it).

### Security Fix: admin routes now check role

Previously every `/admin` controller used the `auth` filter, which only
verifies that a session exists. **Any signed-in user, including subscribers,
could open the dashboard, Users, Payments, Orders and every other admin
screen.**

- New `Neuron\Cms\Auth\Filters\AdminAuthenticationFilter`, registered as
  `admin` by `Neuron\Cms\boot()`. Anonymous visitors are still redirected to
  login; signed-in users whose role is not in the allowed list get a 403.
- All `Controllers/Admin/*` route groups now declare `filters: ['admin']`.
- Allowed roles default to `admin`, `editor`, `author` and can be changed
  with `auth.admin_roles` (see `resources/config/auth.yaml`). Authors stay
  in because Posts, Pages and Events already scope them to their own content.
- Because the filter is registered by the package, sites do **not** need to
  edit their published `app/Initializers/AuthInitializer.php`. The published
  copy gained a comment showing how to override the filter.

If a site has its own controllers under `/admin`, switch them from `auth` to
`admin` as well. Member-facing routes keep `auth` / `member`.

### Breaking Changes

- Subscribers can no longer reach `/admin/*` (403). Any workflow that relied
  on that was relying on the bug.
- `composer.json` now requires `neuron-php/mvc >=0.9.58` and
  `neuron-php/jobs >=0.2.24`.
- Sites with a customized `layouts/admin.php` or
  `admin/dashboard/index.php` keep their copies; add the System dropdown
  from the package view if you want component menu entries.

### Action Required

1. `composer update`, then `php neuron cms:upgrade` to refresh
   `neuron.yaml.example` and `auth.yaml` examples.
2. Switch any site-specific `/admin` controllers from `filters: ['auth']`
   to `filters: ['admin']`.
3. Optional: set `auth.admin_roles` if editors or authors should be kept
   out; add a `components:` section to `neuron.yaml` if you need to disable
   a web component.

---

## Unreleased (site builder)

These features ship with the next CMS release. After `composer update`, run
`php neuron cms:upgrade` then `php neuron db:migrate`. Stock CMS views are
served from the package. `cms:upgrade` prunes unmodified published copies
and leaves edited site views alone. Do **not** use `--force-views` if you
customized layouts. Copy a single view with `php neuron cms:views:publish`.

### Database Changes

New migrations (do not edit existing ones):

- `20260915120000_create_carousels_tables.php`
- `20260915140000_create_menus_tables.php`
- `20260915150000_add_seo_to_posts.php`
- `20260915180000_create_testimonials_tables.php`
- `20260915190000_create_faqs_tables.php`
- `20260916120000_create_redirects_table.php`

### New Features

- Named carousels, testimonials, and FAQ groups (`[carousel]`, `[testimonial]`, `[faq]`)
- CMS-managed header and footer menus (`cms_menu()`)
- HTTP 301/302 redirects (Content → Redirects)
- Public breadcrumbs (`cms_breadcrumbs()`, JSON-LD)
- SEO pack: `/sitemap.xml`, `/robots.txt`, Open Graph, per-post meta
- Configurable homepage (`homepage.mode`: `blog`, `page`, `landing`)
- Paginated blog listings (`blog.posts_per_page`)
- Site-first / package-fallback view resolution. Stock admin and feature views are served from the CMS package; site copies override them. `cms:upgrade` prunes unmodified published views. `cms:views:publish` copies one view for customization.

### Action Required

1. Update `neuron-php/routing` if you want `Router::setRedirectResolver()`. Until that method is installed, keep `Neuron\Cms\Listeners\ApplyRedirectListener` on `request.received`.
2. If the admin layout is customized, add Content links for Carousels, Testimonials, FAQs, Menus, and Redirects.
3. If the public layout is customized, include `partials/navigation.php` / `partials/footer-navigation.php` (or call `cms_menu()`), and call `cms_breadcrumbs()` / `cms_breadcrumb_json_ld()`.
4. Do not rewrite `'/'` to `'/blog'` unless you intend to bypass the Home controller.

See the [Site Builder Guide](https://neuronphp.com/md/cms/guides/site-builder) and `cms/ROADMAP.md` for deferred features.

---

## Version 2025.12.5

### Database Changes
- **New Migration:** `20251205000000_add_two_factor_and_timezone_to_users.php`
  - Adds `two_factor_recovery_codes` column (TEXT, nullable) for storing 2FA backup codes
  - Adds `timezone` column (VARCHAR(50), default 'UTC') for user timezone preferences

### New Features
- Two-factor authentication recovery codes support
- Per-user timezone settings

### Breaking Changes
- None

### Action Required
1. Run `php neuron cms:upgrade` to copy new migrations to your installation
2. Run `php neuron db:migrate` to apply the schema changes
3. Existing user records will have `timezone` set to 'UTC' by default

### Migration Principles Documented
- Added comprehensive migration guidelines to prevent schema drift
- See `MIGRATIONS.md` for detailed migration best practices
- **Important:** Never modify existing migrations; always create new ones for schema changes

---

## Version 2025.11.7

### Initial Release Features
- Complete CMS installation system
- User authentication and authorization
- Post, category, and tag management
- Admin dashboard and member areas
- Email verification system
- Password reset functionality
- Maintenance mode
- Job queue integration

### Database Tables Created
- `users` - User accounts with roles and authentication
- `posts` - Blog posts and content
- `categories` - Content categorization
- `tags` - Content tagging
- `post_categories` - Many-to-many relationship
- `post_tags` - Many-to-many relationship
- `password_reset_tokens` - Password reset token tracking
- `email_verification_tokens` - Email verification tracking
- `jobs` - Job queue
- `failed_jobs` - Failed job tracking

### Installation
For new installations:
```bash
php neuron cms:install
```

---

## Upgrade Troubleshooting

### Missing Column Errors

**Error:** `SQLSTATE[HY000]: General error: 1 no such column: column_name`

**Cause:** Your database schema is out of sync with the CMS code.

**Solution:**
1. Check for new migrations: `php neuron cms:upgrade --check`
2. Copy new migrations: `php neuron cms:upgrade`
3. Run migrations: `php neuron db:migrate`

### Migration Already Exists

**Problem:** Migration file exists but wasn't run.

**Solution:**
```bash
# Check migration status
php neuron db:status

# If migration shows as pending, run it
php neuron db:migrate

# If migration isn't tracked, it may need to be marked as run
# See MIGRATIONS.md for details on using --fake flag
```

### Customized Views Being Overwritten

**Problem:** Running `cms:install` with reinstall overwrites customized views.

**Solution:**
- Use `php neuron cms:upgrade` instead. It prunes unmodified published views so stock UI comes from the package. Edited views are left alone.
- Use `php neuron cms:upgrade --skip-views` to skip view pruning entirely
- Use `php neuron cms:views:publish path/to/view.php` to copy one package view into the site
- Use `--prompt-views` to review files that differ, or merge by comparing package views with your copies

### Schema Drift After Composer Update

**Problem:** After `composer update`, application breaks with database errors.

**Cause:** New CMS code expects columns that don't exist in your database.

**Prevention:**
1. Always run `neuron cms:upgrade` after `composer update neuron-php/cms`
2. Review and apply any new migrations before deploying to production
3. Test in development/staging environment first

---

## Version History

| Version | Release Date | Key Changes |
|---------|--------------|-------------|
| 2025.12.5 | 2025-12-05 | Added 2FA recovery codes, user timezones, migration docs |
| 2025.11.7 | 2025-11-07 | Initial CMS release |

---

## Need Help?

- **Documentation:** See `README.md`, `MIGRATIONS.md`
- **Issues:** Report bugs at [GitHub Issues](https://github.com/neuron-php/cms/issues)
- **Migration Help:** See `MIGRATIONS.md` for a comprehensive migration guide
