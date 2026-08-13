#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/../../../.." && pwd)"
aml="$project_root/runtime/bin/aml"

cd "$project_root"

test "$(AML_LANG=fr "$aml" env:get DATABASE_DRIVER)" = "sqlite"
test "$(AML_LANG=fr "$aml" env:get DATABASE_USER)" = "root"
test "$(AML_LANG=fr "$aml" env:get DATABASE_PASSWORD)" = "root"
test -f "$project_root/runtime/storage/database.sqlite"

database_output="$(AML_LANG=fr "$aml" db:show)"
grep -q "Pilote       : sqlite" <<<"$database_output"
grep -q "Utilisateur  : root" <<<"$database_output"

env_output="$(AML_LANG=fr "$aml" env:list)"
grep -q "DATABASE_PASSWORD       \*\*\*\*\*\*\*\*" <<<"$env_output"

echo "env/database smoke: OK"
