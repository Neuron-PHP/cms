# CMS site-builder roadmap

The CMS now covers the marketing-site basics that were blocking real sites:
pages, blog, events, contact, teams, media, menus, carousels, testimonials,
FAQs, redirects, SEO, pagination, a configurable homepage, and public
breadcrumbs.

This file is the remaining list. Build these in the `cms` package when a
site actually needs them. They are useful, but not required for the CMS to
feel well-rounded.

## Deferred

### Site search
A public search box over published pages, posts, and events. Start with a
simple `[search]` form and a results page. Full-text ranking and filters can
wait until a site outgrows `LIKE` queries.

### Newsletter
Email signup, not the CMS `subscriber` role. A `[newsletter]` shortcode that
hands the address to Mailchimp, ConvertKit, Buttondown, or another provider
is enough. A built-in campaign editor and blast sender is a separate product.

### Comments
Threaded comments on posts (and optionally pages). Needs spam protection,
moderation in admin, and a clear guest-vs-member policy. Most small sites
are fine without it; those that want discussion often already use a third
party.

### Cookie consent / analytics snippet
A config-driven analytics script tag plus an optional consent banner.
Keep it a snippet manager, not a full CMP. Sites that need GDPR-grade
consent should keep using their existing vendor.

### Page hierarchy
Parent/child pages and URL nesting (`/about/staff`). Breadcrumbs will pick
this up once pages have parents. Until then, public crumbs are
Home → page title.

### Visual form builder
Admin-designed forms beyond the current contact YAML. High effort, high
surface area. Contact shortcodes and event registration already cover the
common cases.

### Internationalization (i18n)
Translated content, locale-prefixed URLs, and language switchers. This
touches models, routes, menus, and the admin. Do it as its own project when
a bilingual site is on the calendar.

## Already shipped (this wave)

| Feature | Where |
|---------|--------|
| Image carousels | Content → Carousels, `[carousel]` |
| CMS menus | Content → Menus, `cms_menu()` |
| SEO pack | sitemap, robots, Open Graph, per-post meta |
| Blog pagination | `/blog?page=` |
| Configurable homepage | `homepage.mode` in `neuron.yaml` |
| Testimonials | Content → Testimonials, `[testimonial]` |
| FAQ | Content → FAQs, `[faq]` |
| Redirect manager | Content → Redirects |
| Public breadcrumbs | default layout, `cms_breadcrumbs()` |
