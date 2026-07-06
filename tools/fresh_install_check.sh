#!/usr/bin/env bash
# Dev tool: validates the drupress_starter recipe on a genuinely fresh Drupal
# 11 site (no manual module steps), then tears the site down. Run from WSL.
#
# This exists because a module's own config/install/*.yml is not always
# reliably imported when that module is installed as part of a Drupal
# recipe's batch (see the note atop recipes/drupress_starter/recipe.yml) -
# bugs of this kind only show up on a from-scratch install, never on an
# already-drifted dev site. Re-run this after any recipe or module.info.yml
# change that touches config/install.
set -euo pipefail

REPO=/mnt/c/dev/drupress-drupal-module-suite
PROJECT=drupress-fresh-check
SITE=~/${PROJECT}

echo "=== Scaffolding ${PROJECT} ==="
rm -rf "$SITE"
mkdir -p "$SITE" && cd "$SITE"
ddev config --project-type=drupal11 --docroot=web --php-version=8.3 --project-name="$PROJECT"
ddev start

cat > .ddev/docker-compose.drupress.yaml <<EOF
services:
  web:
    volumes:
      - ${REPO}:${REPO}:cached
EOF
ddev restart

ddev composer create-project drupal/recommended-project:^11.1 .
ddev composer require drush/drush
ddev composer config repositories.drupress-module "{\"type\":\"path\",\"url\":\"${REPO}/modules/drupress\",\"options\":{\"symlink\":true}}"
ddev composer config repositories.drupress-theme "{\"type\":\"path\",\"url\":\"${REPO}/themes/drupress_admin\",\"options\":{\"symlink\":true}}"
ddev composer config repositories.drupress-recipe "{\"type\":\"path\",\"url\":\"${REPO}/recipes/drupress_starter\",\"options\":{\"symlink\":true}}"
ddev composer config allow-plugins.cweagans/composer-patches true
ddev composer require cweagans/composer-patches:^1.7
ddev composer config extra.patches.drupal/gutenberg "{\"Drupal 11.4 route methods must be arrays - https://www.drupal.org/project/gutenberg/issues/3607961\": \"${REPO}/patches/gutenberg-routing-methods-array.patch\"}" --json
ddev composer require drupress/drupress:@dev drupress/drupress_admin:@dev drupress/drupress_starter:@dev

echo "=== Installing site + applying recipe ==="
ddev drush site:install standard --account-name=admin --account-pass=admin --site-name="Drupress Fresh Check" -y
ddev exec "vendor/bin/dr recipe ${REPO}/recipes/drupress_starter"
ddev drush cr

echo "=== Verifying ==="
echo "-- modules --"
ddev drush pm:list --filter=drupress --fields=name,status
echo "-- theme --"
ddev drush cget system.theme admin
echo "-- gutenberg config --"
ddev drush sql:query "SELECT name FROM config WHERE name IN ('filter.format.gutenberg','editor.editor.gutenberg','gutenberg.settings')"
echo "-- drupress views + menu --"
ddev drush sql:query "SELECT name FROM config WHERE name LIKE 'views.view.drupress_%' OR name = 'system.menu.drupress-admin'"
echo "-- editor role --"
ddev drush sql:query "SELECT name FROM config WHERE name = 'user.role.drupress_editor'"

echo "=== Done. Tear down with: ==="
echo "cd $SITE && ddev delete -O -y && rm -rf $SITE"
