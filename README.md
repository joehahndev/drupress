# Drupress

[![CI](https://github.com/joehahndev/drupress/actions/workflows/ci.yml/badge.svg)](https://github.com/joehahndev/drupress/actions/workflows/ci.yml)

**A WordPress-style admin experience for Drupal 11.**

Drupress makes the Drupal administration and content-editing experience look
and feel like vanilla WordPress (wp-admin), so people who only know WordPress
can manage a Drupal site without feeling lost — a dark left sidebar (Dashboard,
Posts, Media, Pages, Comments, Appearance, Plugins, Users, Tools, Settings), WP
list tables with hover row actions, a WordPress-style dashboard, and the
Gutenberg block editor with Featured image / Categories / Tags document panels.

## The facade principle

Drupress is a **presentation layer** over standard Drupal workflows, and that
is a hard guarantee, not an aspiration:

> **Disabling or uninstalling any Drupress module never affects your content or
> fields.**

- Drupress may *create* ordinary, **site-owned** Drupal structures (fields,
  vocabularies, views) — via the recipe or the "create missing structures"
  action — but it never owns content storage, never defines a content field
  type, and never migrates data into module-owned tables.
- Modules ship only *presentation* config (views, the admin menu, settings),
  which is removed cleanly on uninstall with no cascade into your data.
- This is enforced by an automated test,
  [`FacadeUninstallTest`](modules/drupress/tests/src/Kernel/FacadeUninstallTest.php):
  install the suite → create content → uninstall everything → assert all
  nodes, terms, fields and field data survive while Drupress config is gone.
- It is also proven end to end by installing the recipe on a genuinely fresh
  Drupal 11 site (no manual steps) and confirming every screen works.

### Known compromises (documented, never silent)

| Area | Compromise | Impact |
|---|---|---|
| Gutenberg editor | Block-comment markup (`<!-- wp:paragraph -->`) is stored inside the standard `body` field. | Content stays valid, renderable HTML, editable in CKEditor after uninstall. `drupress_editor`'s uninstall prints a warning about the residual comments. |
| Navigation sidebar | `drupress_menu` swaps the core Navigation block layout on install. | The previous layout is backed up to state and **restored exactly** on uninstall (verified). |
| Recipe | Drupal recipes apply **once**; site-owned config they create is intentionally left in place. | Re-running setup after changes uses the settings form's "create missing structures" action instead. |
| Recipe config ordering | A newly-installed module's own `config/install/*.yml` is not reliably imported by Drupal when it depends on config the recipe itself creates in the same apply (observed for the Posts/Pages views and Gutenberg's text format). | The recipe ships those specific files directly (see the note atop `recipe.yml`); `drupress_menu` additionally creates its menu programmatically as a defensive fallback. Verified via a from-scratch fresh install. |

## Packages

| Path | Package | What it is |
|---|---|---|
| [`modules/drupress`](modules/drupress) | `drupress/drupress` | Main module + sub-modules (menu, dashboard, content, media, editor) |
| [`themes/drupress_admin`](themes/drupress_admin) | `drupress/drupress_admin` | WP-look admin theme (Claro subtheme) |
| [`recipes/drupress_starter`](recipes/drupress_starter) | `drupress/drupress_starter` | One-command setup recipe |

Each feature group is an **optional sub-module** — enable only what you want.

## Quick start

```bash
composer require drupress/drupress_starter
php web/core/scripts/dr recipe web/recipes/drupress_starter
drush cr
```

The recipe installs the modules + admin theme, sets up Posts/Pages/Categories/
Tags/Featured-image as site-owned config, enables the Gutenberg editor for those
types, and creates an "Editor" role with the WordPress-appropriate permissions.

> **Gutenberg on Drupal 11.4+**: Gutenberg 3.0.6 needs a one-line routing patch
> (`methods:` must be an array) — see
> [drupal.org#3607961](https://www.drupal.org/project/gutenberg/issues/3607961).
> The `docs/DEVELOPMENT.md` guide shows how the dev site applies it.

## Configuration

Everything WordPress-shaped maps onto whatever your site actually has, at
`/admin/config/drupress`:

- **Posts** → a node type (default `article`)
- **Pages** → a node type (default `page`)
- **Categories** / **Tags** → taxonomy vocabularies
- **Featured image** → a media/image field on the Posts type

Unmapped items simply hide their UI — nothing breaks.

## Development & testing

See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) for the DDEV + WSL setup. In
short, from the dev site:

```bash
# Coding standards + static analysis
vendor/bin/phpcs                 # Drupal + DrupalPractice
vendor/bin/phpstan analyse       # level 6, mglaman/phpstan-drupal

# Tests (kernel)
SIMPLETEST_DB=mysql://db:db@db/db \
  vendor/bin/phpunit -c web/core web/modules/contrib/drupress/tests/src/Kernel/
```

## License

GPL-2.0-or-later. The bundled sidebar icons are original SVGs (not Dashicons)
to avoid trademark-adjacent concerns.
