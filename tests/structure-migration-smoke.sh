#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
FIXTURE=$(mktemp -d "${TMPDIR:-/tmp}/phpaml-structure-test.XXXXXX")
trap 'rm -rf "$FIXTURE"' EXIT HUP INT TERM

mkdir -p "$FIXTURE/cli/runtime/bin" "$FIXTURE/project/public" "$FIXTURE/project/aml_env/framework" "$FIXTURE/project/configs"
cp "$ROOT/cli/aml.php" "$FIXTURE/cli/runtime/bin/aml.php"
cp "$ROOT/cli/ai-debug.php" "$FIXTURE/cli/runtime/bin/ai-debug.php"
cp "$ROOT/cli/deploy.php" "$FIXTURE/cli/runtime/bin/deploy.php"
cp "$ROOT/phpaml.json" "$FIXTURE/cli/phpaml.json"
touch "$FIXTURE/project/public/index.php"

printf '%s\n' '{"name":"demo","aml":{"environment":"aml_env","framework":"0.1.0"},"dependencies":{"php":"^8.2"}}' > "$FIXTURE/project/info.json"
printf '%s\n' "<?php return ['runtime' => __DIR__ . '/../aml_env'];" > "$FIXTURE/project/configs/app.php"

PREVIEW=$(cd "$FIXTURE/project" && AML_LANG=en php "$FIXTURE/cli/runtime/bin/aml.php" migrate:structure)
printf '%s\n' "$PREVIEW" | grep -q 'Preview only'
test -f "$FIXTURE/project/info.json"
test -d "$FIXTURE/project/aml_env"

APPLIED=$(cd "$FIXTURE/project" && AML_LANG=en php "$FIXTURE/cli/runtime/bin/aml.php" migrate:structure --apply --yes)
printf '%s\n' "$APPLIED" | grep -q '/runtime/storage/migrations/structure-'
test -f "$FIXTURE/project/phpaml.json"
test ! -e "$FIXTURE/project/info.json"
test -d "$FIXTURE/project/runtime"
test ! -e "$FIXTURE/project/aml_env"
grep -q '"directory": "runtime"' "$FIXTURE/project/phpaml.json"
grep -q '"requirements"' "$FIXTURE/project/phpaml.json"
grep -q "../runtime" "$FIXTURE/project/configs/app.php"
find "$FIXTURE/project/runtime/storage/migrations" -type f | grep -q .

mkdir -p "$FIXTURE/conflict/public" "$FIXTURE/conflict/aml_env" "$FIXTURE/conflict/runtime"
touch "$FIXTURE/conflict/public/index.php" "$FIXTURE/conflict/info.json"
if (cd "$FIXTURE/conflict" && php "$FIXTURE/cli/runtime/bin/aml.php" migrate:structure --apply --yes >/dev/null 2>&1); then
    echo 'A conflicting structure should be rejected.' >&2
    exit 1
fi

echo 'Structure migration tests passed.'
