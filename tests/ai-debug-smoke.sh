#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/phpaml-ai-test.XXXXXX")"
trap 'rm -rf "$fixture"' EXIT HUP INT TERM
mkdir -p "$fixture/home" "$fixture/runtime/aml_env/bin"
cp "$root/cli/aml.php" "$root/cli/ai-debug.php" "$fixture/runtime/aml_env/bin/"
cp "$root/info.json" "$fixture/runtime/info.json"

HOME="$fixture/home" AML_LANG=en php "$fixture/runtime/aml_env/bin/aml.php" \
  ai:configure deepseek --key smoke-secret --model deepseek-v4-pro
show="$(HOME="$fixture/home" AML_LANG=en php "$fixture/runtime/aml_env/bin/aml.php" ai:show)"
grep -q 'Provider : deepseek' <<<"$show"
grep -q 'API key  : \*\*\*\*\*\*\*\*' <<<"$show"
if grep -q 'smoke-secret' <<<"$show"; then
  echo 'The AI key was exposed.' >&2
  exit 1
fi
test "$(stat -f '%Lp' "$fixture/home/.phpaml/ai.json" 2>/dev/null || stat -c '%a' "$fixture/home/.phpaml/ai.json")" = 600
echo 'AI debug smoke: OK'
