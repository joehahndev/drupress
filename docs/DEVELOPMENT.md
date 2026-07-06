# Drupress development

This repository holds only the **product** (modules + theme + recipe). The
runnable Drupal 11 dev site lives separately, in the WSL2 filesystem, for DDEV
performance.

## Layout

```
C:\dev\drupress-drupal-module-suite   <- this repo (edited from Windows)
~/drupress-dev  (WSL Ubuntu)          <- DDEV Drupal 11 site, consumes the repo
```

The repo is bind-mounted into the DDEV web container at its Windows path and
consumed through Composer **path repositories** with symlinks, so edits made
from Windows are live in the container immediately.

## One-time setup

```bash
# In WSL Ubuntu:
mkdir -p ~/drupress-dev && cd ~/drupress-dev
ddev config --project-type=drupal11 --docroot=web --php-version=8.3 \
  --project-name=drupress-dev
ddev start
ddev composer create-project drupal/recommended-project:^11.1 .
ddev composer require drush/drush

# Bind-mount the Windows repo into the web container at the same path
# (.ddev/docker-compose.drupress.yaml):
#   services: { web: { volumes: [
#     "/mnt/c/dev/drupress-drupal-module-suite:/mnt/c/dev/drupress-drupal-module-suite:cached" ] } }
ddev restart

# Composer path repos (symlink -> live-editable from Windows):
ddev composer config repositories.drupress-module \
  '{"type":"path","url":"/mnt/c/dev/drupress-drupal-module-suite/modules/drupress","options":{"symlink":true}}'
ddev composer config repositories.drupress-theme \
  '{"type":"path","url":"/mnt/c/dev/drupress-drupal-module-suite/themes/drupress_admin","options":{"symlink":true}}'
ddev composer require drupress/drupress:@dev drupress/drupress_admin:@dev \
  cweagans/composer-patches:^1.7

# Dev-only tooling:
ddev composer require --dev phpunit/phpunit drupal/core-dev \
  --with-all-dependencies

ddev drush site:install standard --account-name=admin --account-pass=admin -y
```

### Gutenberg 11.4 patch

Gutenberg 3.0.6 declares route `methods:` as strings, which Drupal 11.4's
route discovery rejects. The dev site carries a local patch
(`patches/gutenberg-routing-methods-array.patch`) wired via
`cweagans/composer-patches` in the dev site's `composer.json`. Track
[drupal.org#3607961](https://www.drupal.org/project/gutenberg/issues/3607961)
for an upstream release.

## Applying the recipe

`drush` has no `recipe` command; core's script is the current entry point
(the older `core/scripts/drupal` is deprecated — use `dr`):

```bash
ddev exec "vendor/bin/dr recipe /mnt/c/dev/drupress-drupal-module-suite/recipes/drupress_starter"
ddev drush cr
```

Recipes apply **once**. Re-applying after the site-owned config has drifted
(e.g. the `category` vocabulary already exists) errors by design — that config
is meant to persist. To re-establish structures on an existing site, use the
"create missing structures" action on `/admin/config/drupress` rather than
re-running the recipe.

## Quality gates

```bash
# phpcs from the repo root inside the container:
ddev exec "cd /mnt/c/dev/drupress-drupal-module-suite && vendor/bin/phpcs"

# phpstan must run with the Drupal root as cwd:
ddev exec "cd /var/www/html && \
  /mnt/c/dev/drupress-drupal-module-suite/vendor/bin/phpstan analyse \
  -c /mnt/c/dev/drupress-drupal-module-suite/phpstan.neon"

# Kernel tests:
ddev exec "SIMPLETEST_DB=mysql://db:db@db/db \
  vendor/bin/phpunit -c web/core web/modules/contrib/drupress/tests/src/Kernel/"
```

## Helper scripts (`tools/`)

| Script | Purpose |
|---|---|
| `build_views.php` | Regenerates the Posts/Pages list-table views from core's content view. |
| `sample_content.php` | Creates sample posts, pages, and terms for manual testing. |
| `facade_check.sh` | Manual facade proof: uninstalls the suite and checks content survives. |
| `fetch_page.sh` / `fetch_edit_form.sh` | Authenticated curl fetch of a page / node edit form. |
| `check_edit_form.php` | Reports where the WP-model fields render in a saved edit-form HTML file. |

## Browser testing

The project ships a `.mcp.json` configuring a Playwright MCP server that drives
a **portable Chromium** (`C:\dev\chrome-win\chrome.exe`), pinned to the
`drupress-dev.ddev.site` origin, so automated UI checks never touch a personal
browser. Approve the `playwright` server once (recorded in
`.claude/settings.local.json`).
