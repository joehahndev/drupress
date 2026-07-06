# Drupress

**A WordPress-style admin experience for Drupal 11.**

Drupress makes the Drupal administration and content editing experience look and
feel like vanilla WordPress (wp-admin), so people who only know WordPress can
manage a Drupal site without feeling lost.

## The facade principle

Drupress is a **presentation layer** over standard Drupal workflows:

- Disabling or uninstalling any Drupress module never affects your content or fields.
- Drupress may *create* ordinary, site-owned Drupal structures (fields,
  vocabularies, views) but never owns content storage.
- Where a compromise is unavoidable it is documented, and uninstall warns you
  explicitly. Known compromise: the Gutenberg editor stores block-comment
  markup (`<!-- wp:paragraph -->`) inside the standard body field. Content
  remains a normal HTML body and stays fully editable after uninstall.

## Packages

| Path | Package | What it is |
|---|---|---|
| `modules/drupress` | `drupress/drupress` | Main module + sub-modules (menu, dashboard, content, media, editor) |
| `themes/drupress_admin` | `drupress/drupress_admin` | WP-look admin theme (Claro subtheme) |
| `recipes/drupress_starter` | `drupress/drupress_starter` | One-command setup recipe |

## Quick start

```bash
composer require drupress/drupress_starter
php web/core/scripts/drupal recipe web/recipes/drupress_starter
drush cr
```

Or enable modules individually — each feature group is an optional sub-module.

## Development

Dev site runs in DDEV (see `docs/DEVELOPMENT.md`, forthcoming). Lint/analyze:

```bash
composer install
vendor/bin/phpcs
vendor/bin/phpstan analyse   # run inside the DDEV web container
```
