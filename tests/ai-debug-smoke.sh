#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/phpaml-ai-test.XXXXXX")"
trap 'rm -rf "$fixture"' EXIT HUP INT TERM
mkdir -p "$fixture/home" "$fixture/runtime/runtime/bin"
cp "$root/cli/aml.php" "$root/cli/ai-debug.php" "$fixture/runtime/runtime/bin/"
cp "$root/cli/deploy.php" "$fixture/runtime/runtime/bin/deploy.php"
cp "$root/phpaml.json" "$fixture/runtime/phpaml.json"

HOME="$fixture/home" AML_LANG=en php "$fixture/runtime/runtime/bin/aml.php" \
  ai:configure deepseek --key smoke-secret --model deepseek-v4-pro
show="$(HOME="$fixture/home" AML_LANG=en php "$fixture/runtime/runtime/bin/aml.php" ai:show)"
grep -q 'Provider : deepseek' <<<"$show"
grep -q 'API key  : \*\*\*\*\*\*\*\*' <<<"$show"
if grep -q 'smoke-secret' <<<"$show"; then
  echo 'The AI key was exposed.' >&2
  exit 1
fi
if [[ "$(uname -s)" == Darwin ]]; then
  permissions="$(stat -f '%Lp' "$fixture/home/.phpaml/ai.json")"
else
  permissions="$(stat -c '%a' "$fixture/home/.phpaml/ai.json")"
fi
test "$permissions" = 600

mkdir -p "$fixture/runtime/public" \
  "$fixture/runtime/runtime/storage/debug-reports" \
  "$fixture/runtime/runtime/storage/debug-backups/20260812-120000-a1b2c3/public"
printf '%s\n' '<?php echo "current";' > "$fixture/runtime/public/index.php"
printf '%s\n' '<?php echo "original";' > "$fixture/runtime/runtime/storage/debug-backups/20260812-120000-a1b2c3/public/index.php"
cat > "$fixture/runtime/runtime/storage/debug-reports/20260812-120000-a1b2c3.json" <<'JSON'
{
  "id": "20260812-120000-a1b2c3",
  "created_at": "2026-08-12T12:00:00-04:00",
  "status": "fixed",
  "provider": "deepseek",
  "model": "deepseek-v4-pro",
  "diagnosis": "Smoke diagnosis",
  "summary": "Smoke correction",
  "changes": [{"path":"public/index.php","status":"applied","existed":true,"backup":"public/index.php"}],
  "commands": []
}
JSON

history="$(cd "$fixture/runtime" && HOME="$fixture/home" AML_LANG=en php runtime/bin/aml.php debug:history)"
grep -q '20260812-120000-a1b2c3' <<<"$history"
details="$(cd "$fixture/runtime" && HOME="$fixture/home" AML_LANG=en php runtime/bin/aml.php debug:show 20260812-120000-a1b2c3)"
grep -q 'Smoke diagnosis' <<<"$details"
(cd "$fixture/runtime" && HOME="$fixture/home" AML_LANG=en php runtime/bin/aml.php \
  debug:rollback 20260812-120000-a1b2c3 --yes >/dev/null)
grep -q 'original' "$fixture/runtime/public/index.php"
grep -q '"status": "rolled_back"' "$fixture/runtime/runtime/storage/debug-reports/20260812-120000-a1b2c3.json"
echo 'AI debug smoke: OK'
