#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
php_bin="${PHP_BINARY:-php}"
trap 'rm -rf "$fixture"' EXIT

mkdir -p "$fixture/runtime" "$fixture/configs" "$fixture/public"
printf '%s\n' '{"name":"data-test","version":"1.0.0","runtime":{"directory":"runtime"},"modules":{}}' > "$fixture/phpaml.json"
printf '%s\n' '<?php' > "$fixture/public/index.php"
cat > "$fixture/composer.json" <<'JSON'
{
  "name": "phpaml/data-test",
  "require": {"php": "^8.2"},
  "config": {"vendor-dir": "runtime"}
}
JSON

cat > "$fixture/composer" <<'COMPOSER'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> composer-invocations.log
mkdir -p runtime/phpaml/data/bin
cat > runtime/phpaml/data/bin/aml-data <<'RUNNER'
<?php

file_put_contents('data-invocations.log', implode(' ', array_slice($argv, 1)) . PHP_EOL, FILE_APPEND);
RUNNER
chmod +x runtime/phpaml/data/bin/aml-data
COMPOSER
chmod +x "$fixture/composer"

cd "$fixture"
AML_LANG=en AML_COMPOSER_BINARY="$fixture/composer" "$php_bin" "$root/cli/aml.php" install data --driver sqlite
grep -q 'require phpaml/data:\^0.2@alpha' composer-invocations.log
grep -q '^data:install --driver sqlite$' data-invocations.log

AML_LANG=en "$php_bin" "$root/cli/aml.php" data:status --connection main --json
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:model User
grep -q '^data:status --connection main --json$' data-invocations.log
grep -q '^data:make-model User$' data-invocations.log

: > composer-invocations.log
: > data-invocations.log
AML_LANG=en AML_COMPOSER_BINARY="$fixture/composer" "$php_bin" "$root/cli/aml.php" install data --driver mongo
grep -q 'require phpaml/data:\^0.2@alpha phpaml/data-mongodb:\^0.1@alpha' composer-invocations.log
grep -q '^data:install --driver mongodb$' data-invocations.log

echo "data smoke: OK"
