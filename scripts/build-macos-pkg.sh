#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
export COPYFILE_DISABLE=1
VERSION=${AML_VERSION:-$(awk -F'"' '/"version"/ {print $4; exit}' "$ROOT/phpaml.json")}
SOURCE="$ROOT/dist/aml-macos-arm64"
STAGING="$ROOT/packaging/macos-arm64/root"
INSTALLATION="$STAGING/usr/local/lib/aml"
COMMANDS="$STAGING/usr/local/bin"
OUTPUT="$ROOT/dist/phpaml-$VERSION-macos-arm64.pkg"

if [ "$(uname -s)" != Darwin ] || [ "$(uname -m)" != arm64 ]; then
    echo "Cette construction exige macOS ARM64." >&2
    exit 1
fi

if [ ! -x "$SOURCE/bin/aml" ] || [ ! -x "$SOURCE/runtime/php/bin/php" ]; then
    echo "Le paquet autonome est absent. Lancez d’abord ./scripts/build-macos-arm64.sh." >&2
    exit 1
fi

rm -rf "$ROOT/packaging/macos-arm64"
mkdir -p "$INSTALLATION/bin" "$INSTALLATION/runtime/php/bin" \
    "$INSTALLATION/runtime/composer" "$INSTALLATION/runtime/bin" "$COMMANDS"
cp "$SOURCE/bin/aml" "$INSTALLATION/bin/aml"
cp "$SOURCE/runtime/php/bin/php" "$INSTALLATION/runtime/php/bin/php"
cp "$SOURCE/runtime/composer/composer.phar" "$INSTALLATION/runtime/composer/composer.phar"
cp "$SOURCE/runtime/bin/aml.php" "$INSTALLATION/runtime/bin/aml.php"
cp "$SOURCE/runtime/bin/ai-debug.php" "$INSTALLATION/runtime/bin/ai-debug.php"
cp "$SOURCE/runtime/bin/deploy.php" "$INSTALLATION/runtime/bin/deploy.php"
cp "$SOURCE/phpaml.json" "$INSTALLATION/phpaml.json"
ln -s ../lib/aml/bin/aml "$COMMANDS/aml"
xattr -cr "$STAGING"

pkgbuild \
    --root "$STAGING" \
    --identifier com.phpaml.cli \
    --version "$VERSION" \
    --install-location / \
    "$OUTPUT"

(cd "$ROOT/dist" && shasum -a 256 "$(basename "$OUTPUT")" > "$(basename "$OUTPUT").sha256")
echo "Installateur créé : $OUTPUT"
