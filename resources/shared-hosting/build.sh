#!/usr/bin/env bash
# Builds a DirectAdmin/cPanel upload package (no SSH needed on the server):
#   powernest-estate-<version>.zip
#     powernest/      application with vendor/ (outside the web root)
#     public_html/    web root with compiled assets and the browser installer
# Usage: resources/shared-hosting/build.sh [output-dir]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${1:-$ROOT/dist}"
VERSION="$(git -C "$ROOT" rev-parse --short HEAD)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "Exporting $VERSION ..."
mkdir -p "$WORK/src"
git -C "$ROOT" archive HEAD | tar -x -C "$WORK/src"

cd "$WORK/src"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --quiet
find vendor -type d -name .git -prune -exec rm -rf {} +
npm ci --no-audit --no-fund --silent
npm run build --silent

PKG="$WORK/pkg"
mkdir -p "$PKG/powernest" "$PKG/public_html"

# Web root: compiled assets + shared-hosting front controller and installer.
cp -a public/. "$PKG/public_html/"
rm -f "$PKG/public_html/hot"
cp -a resources/shared-hosting/public_html/. "$PKG/public_html/"

# Application, without dev/test files or local state.
tar -c --exclude=./public --exclude=./tests --exclude=./node_modules --exclude=./.env \
    --exclude=./dist --exclude=./.github --exclude=./resources/shared-hosting \
    --exclude=./phpunit.xml --exclude=./.ai --exclude=./storage/logs/*.log . | tar -x -C "$PKG/powernest"
cp resources/shared-hosting/powernest/.htaccess "$PKG/powernest/.htaccess"
echo "$VERSION" > "$PKG/powernest/RELEASE"
rm -f "$PKG/powernest/bootstrap/cache/"*.php
mkdir -p "$PKG/powernest/storage/"{app/private,app/public,fonts,logs,framework/cache/data,framework/sessions,framework/views}
cp resources/shared-hosting/README-INSTALL.txt "$PKG/README-INSTALL.txt"

find "$PKG" -type d -exec chmod 755 {} +
find "$PKG" -type f -exec chmod 644 {} +

mkdir -p "$OUT"
ZIP="$OUT/powernest-estate-$VERSION.zip"
rm -f "$ZIP"
(cd "$PKG" && zip -qr -X "$ZIP" README-INSTALL.txt powernest public_html)
echo "Built $ZIP ($(du -h "$ZIP" | cut -f1))"
