#!/bin/sh
set -e

# Empêche les exécutions en parallèle
LOCK_FILE="/tmp/zoolyx-scraper.lock"
if [ -f "$LOCK_FILE" ]; then
  echo "$(date) - already running, skip"
  exit 0
fi
trap 'rm -f "$LOCK_FILE"' EXIT
touch "$LOCK_FILE"

cd /volume2/web/PetCare/scripts/zoolyx-scraper

export PATH=/volume2/@appstore/Node.js_v22/usr/local/bin:/usr/local/bin:/usr/bin:/bin:$PATH

/volume2/@appstore/Node.js_v22/usr/local/bin/node \
  /volume2/@appstore/Node.js_v22/usr/local/lib/node_modules/npm/bin/npm-cli.js run scrape
