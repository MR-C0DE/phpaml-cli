#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
php_bin="${PHP_BINARY:-php}"
trap 'rm -rf "$fixture"' EXIT

mkdir -p "$fixture/runtime/storage" "$fixture/configs" "$fixture/public"
printf '%s\n' '{"name":"env-test","version":"1.0.0","runtime":{"directory":"runtime"},"modules":{}}' > "$fixture/phpaml.json"
cat > "$fixture/.env" <<'ENV'
DATABASE_DRIVER=sqlite
DATABASE_DSN=sqlite:runtime/storage/database.sqlite
DATABASE_USER=root
DATABASE_PASSWORD=root
ENV
touch "$fixture/runtime/storage/database.sqlite"
printf '%s\n' '<?php' > "$fixture/public/index.php"

cd "$fixture"

test "$(AML_LANG=fr "$php_bin" "$root/cli/aml.php" env:get DATABASE_DRIVER)" = "sqlite"
test "$(AML_LANG=fr "$php_bin" "$root/cli/aml.php" env:get DATABASE_USER)" = "root"
test "$(AML_LANG=fr "$php_bin" "$root/cli/aml.php" env:get DATABASE_PASSWORD)" = "root"
test -f "$fixture/runtime/storage/database.sqlite"

database_output="$(AML_LANG=fr "$php_bin" "$root/cli/aml.php" db:show)"
grep -q "Pilote       : sqlite" <<<"$database_output"
grep -q "Utilisateur  : root" <<<"$database_output"

env_output="$(AML_LANG=fr "$php_bin" "$root/cli/aml.php" env:list)"
grep -q "DATABASE_PASSWORD       \*\*\*\*\*\*\*\*" <<<"$env_output"

echo "env/database smoke: OK"
