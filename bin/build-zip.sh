#!/usr/bin/env bash
# Builds the distributable plugin ZIP (production files only) in ./dist.
set -euo pipefail

cd "$(dirname "$0")/.."

SLUG=woocommerce-product-unpublisher
VERSION=$(grep -m1 -E '^ \* Version:' "$SLUG.php" | sed -E 's/.*Version:[[:space:]]*//')
STAGE=$(mktemp -d)
DEST="$STAGE/$SLUG"
mkdir -p "$DEST" dist

cp -R "$SLUG.php" uninstall.php readme.txt src templates assets "$DEST/"

rm -f "dist/$SLUG-$VERSION.zip"
(cd "$STAGE" && zip -qr "$OLDPWD/dist/$SLUG-$VERSION.zip" "$SLUG")
rm -rf "$STAGE"

echo "dist/$SLUG-$VERSION.zip"
unzip -l "dist/$SLUG-$VERSION.zip"
