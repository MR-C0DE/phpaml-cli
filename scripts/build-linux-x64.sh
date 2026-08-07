#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
AML_VERSION=${AML_VERSION:-1.0.0}
SPC_VERSION=2.8.5
SPC_SHA256=523ba4279c54c7a377156c0dd3a36adf92ee64b01e9a7f5e9e2ec084b8e458e5
TOOLS="$ROOT/.tools"
PACKAGE="$ROOT/dist/aml-linux-x64"
DEB_ROOT="$ROOT/packaging/linux/deb-root"

if [ "$(uname -s)" != Linux ] || [ "$(uname -m)" != x86_64 ]; then
    echo "Cette construction exige Linux x64." >&2
    exit 1
fi

rm -rf "$PACKAGE" "$DEB_ROOT"
mkdir -p "$TOOLS" "$PACKAGE/bin" "$PACKAGE/runtime/php/bin" \
    "$PACKAGE/runtime/composer" "$PACKAGE/aml_env/bin" \
    "$PACKAGE/aml_env/cache/composer" "$PACKAGE/aml_env/tmp"

if [ ! -x "$TOOLS/spc" ]; then
    curl -fL "https://github.com/crazywhalecc/static-php-cli/releases/download/$SPC_VERSION/spc-linux-x86_64.tar.gz" \
        -o "$TOOLS/spc-linux-x86_64.tar.gz"
    echo "$SPC_SHA256  $TOOLS/spc-linux-x86_64.tar.gz" | sha256sum -c -
    tar -xzf "$TOOLS/spc-linux-x86_64.tar.gz" -C "$TOOLS"
fi

cd "$ROOT"
"$TOOLS/spc" craft

curl -fL https://getcomposer.org/download/latest-stable/composer.phar \
    -o "$TOOLS/composer.phar"
curl -fL https://getcomposer.org/download/latest-stable/composer.phar.sha256 \
    -o "$TOOLS/composer.phar.sha256"
echo "$(tr -d '\r\n' < "$TOOLS/composer.phar.sha256")  $TOOLS/composer.phar" | sha256sum -c -

GOCACHE="${TMPDIR:-/tmp}/phpaml-go-cache" go build -trimpath -ldflags "-s -w" \
    -o "$PACKAGE/bin/aml" ./cmd/aml
cp "$ROOT/buildroot/bin/php" "$PACKAGE/runtime/php/bin/php"
cp "$TOOLS/composer.phar" "$PACKAGE/runtime/composer/composer.phar"
cp "$ROOT/cli/aml.php" "$PACKAGE/aml_env/bin/aml.php"
cp "$ROOT/info.json" "$PACKAGE/info.json"
chmod 755 "$PACKAGE/bin/aml" "$PACKAGE/runtime/php/bin/php"

env -i PATH=/usr/bin:/bin HOME="${TMPDIR:-/tmp}" "$PACKAGE/bin/aml" version

tar -czf "$ROOT/dist/aml-linux-x64.tar.gz" -C "$ROOT/dist" aml-linux-x64
sha256sum "$ROOT/dist/aml-linux-x64.tar.gz" > "$ROOT/dist/aml-linux-x64.tar.gz.sha256"

mkdir -p "$DEB_ROOT/DEBIAN" "$DEB_ROOT/opt/phpaml" "$DEB_ROOT/usr/local/bin"
cp -R "$PACKAGE/." "$DEB_ROOT/opt/phpaml/"
ln -s /opt/phpaml/bin/aml "$DEB_ROOT/usr/local/bin/aml"
cat > "$DEB_ROOT/DEBIAN/control" <<EOF
Package: phpaml
Version: $AML_VERSION
Section: devel
Priority: optional
Architecture: amd64
Maintainer: PHPAML <noreply@phpaml.dev>
Description: Environnement autonome du mini-framework PHPAML
 Inclut la commande AML, PHP et Composer sans dépendance système.
EOF

dpkg-deb --root-owner-group --build "$DEB_ROOT" \
    "$ROOT/dist/phpaml-$AML_VERSION-linux-x64.deb"
sha256sum "$ROOT/dist/phpaml-$AML_VERSION-linux-x64.deb" \
    > "$ROOT/dist/phpaml-$AML_VERSION-linux-x64.deb.sha256"

echo "Paquets créés dans $ROOT/dist"
