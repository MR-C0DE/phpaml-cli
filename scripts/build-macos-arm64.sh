#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPC_VERSION=2.8.5
SPC_SHA256=acf2f25d56d0cbf8e65aa82e5054fef555f7be7c5c38046c6e0819f266d83225
TOOLS="$ROOT/.tools"
PACKAGE="$ROOT/dist/aml-macos-arm64"

if [ "$(uname -s)" != Darwin ] || [ "$(uname -m)" != arm64 ]; then
    echo "Cette construction exige macOS ARM64." >&2
    exit 1
fi

mkdir -p "$TOOLS" "$PACKAGE/bin" "$PACKAGE/runtime/php/bin" \
    "$PACKAGE/runtime/composer" "$PACKAGE/aml_env/bin"

if [ ! -x "$TOOLS/spc" ]; then
    curl -fL "https://github.com/crazywhalecc/static-php-cli/releases/download/$SPC_VERSION/spc-macos-aarch64.tar.gz" \
        -o "$TOOLS/spc.tar.gz"
    echo "$SPC_SHA256  $TOOLS/spc.tar.gz" | shasum -a 256 -c -
    tar -xzf "$TOOLS/spc.tar.gz" -C "$TOOLS"
fi

cd "$ROOT"
"$TOOLS/spc" craft

curl -fL https://getcomposer.org/download/latest-stable/composer.phar \
    -o "$TOOLS/composer.phar"
curl -fL https://getcomposer.org/download/latest-stable/composer.phar.sha256 \
    -o "$TOOLS/composer.phar.sha256"
echo "$(tr -d '\n' < "$TOOLS/composer.phar.sha256")  $TOOLS/composer.phar" | shasum -a 256 -c -

GOCACHE="${TMPDIR:-/tmp}/phpaml-go-cache" go build -trimpath -ldflags "-s -w" \
    -o "$PACKAGE/bin/aml" ./cmd/aml
cp "$ROOT/buildroot/bin/php" "$PACKAGE/runtime/php/bin/php"
cp "$TOOLS/composer.phar" "$PACKAGE/runtime/composer/composer.phar"
cp "$ROOT/cli/aml.php" "$PACKAGE/aml_env/bin/aml.php"
cp "$ROOT/info.json" "$PACKAGE/info.json"
chmod 755 "$PACKAGE/bin/aml" "$PACKAGE/runtime/php/bin/php"

tar -czf "$ROOT/dist/aml-macos-arm64.tar.gz" -C "$ROOT/dist" aml-macos-arm64
(cd "$ROOT/dist" && shasum -a 256 aml-macos-arm64.tar.gz > aml-macos-arm64.tar.gz.sha256)

env -i PATH=/usr/bin:/bin HOME="${TMPDIR:-/tmp}" "$PACKAGE/bin/aml" version
"$ROOT/scripts/build-macos-pkg.sh"
echo "Paquet créé : $ROOT/dist/aml-macos-arm64.tar.gz"
