#!/usr/bin/env bash
# Dev tool: logs into the dev site and saves an authenticated page.
# Usage: fetch_page.sh <path> [outfile]
set -euo pipefail

cd ~/drupress-dev
PAGE_PATH="${1:-/admin/dashboard}"
OUT="${2:-/tmp/page.html}"

ULI=$(ddev drush uli --name=admin --uri=https://drupress-dev.ddev.site 2>&1 | grep -oE 'https://[^[:space:]]+/login' | head -1)
curl -sk -c /tmp/dp-cookies.txt -L "$ULI" -o /dev/null -w "login: %{http_code}\n"
curl -sk -b /tmp/dp-cookies.txt "https://drupress-dev.ddev.site${PAGE_PATH}" -o "$OUT" -w "page ${PAGE_PATH}: %{http_code} size:%{size_download}\n"
