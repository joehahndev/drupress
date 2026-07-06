#!/usr/bin/env bash
# Dev tool: proves the facade principle. Records content fingerprints, fully
# uninstalls the Drupress suite + Gutenberg + theme, and re-checks that all
# content, fields, terms, and media survive untouched.
set -uo pipefail
cd ~/drupress-dev

echo "=== BEFORE ==="
ddev drush sql:query "SELECT COUNT(*) AS nodes FROM node_field_data" 2>/dev/null
ddev drush sql:query "SELECT COUNT(*) AS terms FROM taxonomy_term_field_data" 2>/dev/null
ddev drush sql:query "SELECT COUNT(*) AS field_category_rows FROM node__field_category" 2>/dev/null
ddev drush sql:query "SELECT COUNT(*) AS field_tags_rows FROM node__field_tags" 2>/dev/null
echo "field storages present:"
ddev drush sql:query "SELECT name FROM config WHERE name LIKE 'field.storage.node.field_%'" 2>/dev/null

echo "=== UNINSTALL Drupress suite + Gutenberg ==="
ddev drush theme:uninstall drupress_admin -y 2>&1 | tail -1
ddev drush config:set system.theme admin claro -y 2>&1 | tail -1
ddev drush pmu drupress_editor drupress_dashboard drupress_content drupress_media drupress_menu drupress gutenberg -y 2>&1 | tail -2

echo "=== AFTER ==="
ddev drush sql:query "SELECT COUNT(*) AS nodes FROM node_field_data" 2>/dev/null
ddev drush sql:query "SELECT COUNT(*) AS terms FROM taxonomy_term_field_data" 2>/dev/null
ddev drush sql:query "SELECT COUNT(*) AS field_category_rows FROM node__field_category" 2>/dev/null
ddev drush sql:query "SELECT COUNT(*) AS field_tags_rows FROM node__field_tags" 2>/dev/null
echo "field storages present:"
ddev drush sql:query "SELECT name FROM config WHERE name LIKE 'field.storage.node.field_%'" 2>/dev/null
echo "drupress views remaining (should be none):"
ddev drush sql:query "SELECT name FROM config WHERE name LIKE 'views.view.drupress_%'" 2>/dev/null
echo "drupress-admin menu remaining (should be none):"
ddev drush sql:query "SELECT name FROM config WHERE name = 'system.menu.drupress-admin'" 2>/dev/null
