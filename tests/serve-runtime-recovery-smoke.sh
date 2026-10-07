#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/phpaml-serve-recovery.XXXXXX")"
php_bin="${PHP_BINARY:-php}"
server_pid=''
cleanup() {
  status=$?
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  if [[ $status -ne 0 ]]; then
    find "$fixture" -name serve.log -type f -exec sh -c 'echo "--- $1"; cat "$1"' _ {} \;
  fi
  rm -rf "$fixture"
  exit "$status"
}
trap cleanup EXIT HUP INT TERM

framework_cache="$fixture/cache/framework/0.0.0"
mkdir -p "$framework_cache"
framework_archive="$framework_cache/phpaml-framework-0.0.0.zip"
"$php_bin" -r '
$archive=$argv[1];
$zip=new ZipArchive();
$zip->open($archive, ZipArchive::CREATE|ZipArchive::OVERWRITE);
$zip->addEmptyDir("phpaml-framework-0.0.0/src");
$zip->addFromString("phpaml-framework-0.0.0/src/Autoloader.php", "<?php namespace PHPAML; final class Autoloader {}\n");
$zip->close();
' "$framework_archive"
"$php_bin" -r 'echo hash_file("sha256",$argv[1]),"  ",basename($argv[1]),PHP_EOL;' \
  "$framework_archive" > "$framework_archive.sha256"

cat > "$fixture/composer" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> composer-invocations.log
mkdir -p runtime
cat > runtime/autoload.php <<'PHP'
<?php
namespace AML\View { final class FileApplication {} }
namespace AML\Engine { final class EngineRuntime {} }
namespace PHPAML\Security { final class CspNonce {} }
PHP
SH
chmod +x "$fixture/composer"

for kind in classic view api; do
  project="$fixture/$kind"
  mkdir -p "$project/public"
  printf '%s\n' '<?php echo "runtime-recovered";' > "$project/public/index.php"
  cat > "$project/composer.json" <<'JSON'
{"name":"phpaml/runtime-recovery-test","require":{"php":"^8.2"},"config":{"vendor-dir":"runtime"}}
JSON
  case "$kind" in
    classic)
      modules='{}'
      application='"type":"web"'
      ;;
    view)
      modules='{"view":"0.1.0-beta.7","engine":"0.1.0-beta.4"}'
      application='"type":"view"'
      ;;
    api)
      modules='{"api":true}'
      application='"type":"api"'
      ;;
  esac
  printf '%s\n' "{\"name\":\"recovery-$kind\",\"runtime\":{\"framework\":\"0.0.0\"},\"application\":{$application},\"modules\":$modules}" \
    > "$project/phpaml.json"

  language=en
  if [[ "$kind" = view ]]; then
    language=fr
  fi
  port=$((18910 + RANDOM % 1000))
  (
    cd "$project"
    AML_LANG="$language" \
    AML_CACHE_HOME="$fixture/cache" \
    AML_COMPOSER_BINARY="$fixture/composer" \
      "$php_bin" "$root/cli/aml.php" serve "127.0.0.1:$port" --offline > serve.log 2>&1
  ) &
  server_pid=$!
  started=false
  for _ in $(seq 1 100); do
    if grep -Eq 'PHPAML (is listening at|écoute sur)' "$project/serve.log" 2>/dev/null; then
      started=true
      break
    fi
    if ! kill -0 "$server_pid" 2>/dev/null; then
      break
    fi
    sleep 0.05
  done
  if [[ "$started" != true ]]; then
    echo "aml serve did not start after rebuilding runtime ($kind)." >&2
    exit 1
  fi
  kill "$server_pid" 2>/dev/null || true
  wait "$server_pid" 2>/dev/null || true
  server_pid=''

  if [[ "$language" = fr ]]; then
    grep -q 'Le runtime PHPAML est absent. Reconstruction automatique' "$project/serve.log"
    grep -q 'Runtime PHPAML reconstruit' "$project/serve.log"
    grep -q 'PHPAML écoute sur' "$project/serve.log"
  else
    grep -q 'The PHPAML runtime is missing. Rebuilding the environment automatically' "$project/serve.log"
    grep -q 'PHPAML runtime rebuilt' "$project/serve.log"
    grep -q 'PHPAML is listening at' "$project/serve.log"
  fi
  grep -q 'install --no-interaction --prefer-dist' "$project/composer-invocations.log"
  test -f "$project/runtime/autoload.php"
  test -f "$project/runtime/aml-installed.json"
  test -f "$project/runtime/framework/Autoloader.php"
done

echo 'serve runtime recovery smoke: OK'
