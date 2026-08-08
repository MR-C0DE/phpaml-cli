#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
FIXTURE=$(mktemp -d "${TMPDIR:-/tmp}/phpaml-doctor-test.XXXXXX")
trap 'rm -rf "$FIXTURE"' EXIT HUP INT TERM

mkdir -p "$FIXTURE/aml_env/bin" "$FIXTURE/aml_env/tmp" \
    "$FIXTURE/aml_env/cache" "$FIXTURE/runtime/composer"
cp "$ROOT/cli/aml.php" "$FIXTURE/aml_env/bin/aml.php"
cp "$ROOT/info.json" "$FIXTURE/info.json"
touch "$FIXTURE/runtime/composer/composer.phar"

HEALTHY=$(cd "${TMPDIR:-/tmp}" && php "$FIXTURE/aml_env/bin/aml.php" doctor --offline --json)
printf '%s\n' "$HEALTHY" | grep -q '"healthy": true'
printf '%s\n' "$HEALTHY" | grep -q '"name": "Extensions PHP"'

rm -f "$FIXTURE/runtime/composer/composer.phar"
if BROKEN=$(cd "${TMPDIR:-/tmp}" && php "$FIXTURE/aml_env/bin/aml.php" doctor --offline --port 70000 --json); then
    echo "aml doctor devait signaler l'installation incomplète." >&2
    exit 1
fi
printf '%s\n' "$BROKEN" | grep -q '"healthy": false'
printf '%s\n' "$BROKEN" | grep -q 'Composer privé'
printf '%s\n' "$BROKEN" | grep -q 'le port doit être compris entre 1 et 65535'

echo "Tests aml doctor réussis."
