#!/usr/bin/env bash
# Dev tool: logs into the dev site via drush uli and saves a node edit form
# to /tmp/edit.html, then reports where the WP-model fields render.
set -euo pipefail

cd ~/drupress-dev
NID="${1:-1}"

ULI=$(ddev drush uli --name=admin --uri=https://drupress-dev.ddev.site 2>&1 | grep -oE 'https://[^[:space:]]+/login' | head -1)
echo "uli: ${ULI:0:60}..."

curl -sk -c /tmp/dp-cookies.txt -L "$ULI" -o /dev/null -w "login: %{http_code}\n"
curl -sk -b /tmp/dp-cookies.txt "https://drupress-dev.ddev.site/node/${NID}/edit" -o /tmp/edit.html -w "edit: %{http_code} size:%{size_download}\n"
php /mnt/c/dev/drupress-drupal-module-suite/tools/check_edit_form.php /tmp/edit.html
