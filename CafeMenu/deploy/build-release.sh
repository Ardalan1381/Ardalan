#!/usr/bin/env bash
# Builds a deployable CafeMenu release archive from a clean checkout.
#
#   deploy/build-release.sh [output-dir]
#
# The archive contains application source, production vendor/ and compiled
# public/build/ assets, so it can be uploaded to hosts without Composer or
# Node. It never contains .env, logs, sessions, uploads, backups, tests or
# node_modules. VITE_REVERB_* are compiled into the assets: pass the PUBLIC
# Reverb host/port/scheme of the target site, e.g.
#
#   REVERB_APP_KEY=... REVERB_HOST=cafe.example.com REVERB_PORT=443 REVERB_SCHEME=https deploy/build-release.sh
#
# REVERB_APP_KEY must then be copied into the target server's .env as well
# (the browser key and the server key have to match). Without REVERB_* the
# bundle is built with real-time updates disabled (everything else works;
# see docs/deployment/INSTALLATION.md) — or build on the server instead.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/../release}"
VERSION="$(date +%Y.%m.%d)-$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo local)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "Staging CafeMenu $VERSION"
# Tracked files only: untracked local files (.env, uploads) can never leak in.
# Works whether the app is the repository root or a subdirectory of it.
TOPLEVEL="$(git -C "$ROOT" rev-parse --show-toplevel)"
PREFIX="$(git -C "$ROOT" rev-parse --show-prefix)"
git -C "$TOPLEVEL" archive --format=tar "HEAD:${PREFIX%/}" | tar -x -C "$STAGE"
rm -rf "$STAGE/tests" "$STAGE/docs/engineering/screenshots" "$STAGE/CLAUDE.md" "$STAGE/phpunit.xml"

cd "$STAGE"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
php -r "file_exists('.env') || copy('.env.example', '.env');"
# Throwaway build-time .env: only the public Reverb settings matter here.
for key in REVERB_APP_KEY REVERB_HOST REVERB_PORT REVERB_SCHEME; do
    if [ -n "${!key:-}" ]; then
        sed -i "s|^${key}=.*|${key}=${!key}|" .env
    fi
done
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules .env
rm -f public/hot

mkdir -p "$OUT"
ARCHIVE="$OUT/cafemenu-$VERSION.tar.gz"
tar -czf "$ARCHIVE" -C "$STAGE" .
sha256sum "$ARCHIVE" > "$ARCHIVE.sha256"
echo "Release: $ARCHIVE"
cat "$ARCHIVE.sha256"
