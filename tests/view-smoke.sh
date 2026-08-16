#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
php_bin="${PHP_BINARY:-php}"
trap 'rm -rf "$fixture"' EXIT

mkdir -p "$fixture/project/public" "$fixture/project/app" "$fixture/project/configs"
cat > "$fixture/project/phpaml.json" <<'JSON'
{"name":"view-test","version":"1.0.0","runtime":{"directory":"runtime"},"modules":{}}
JSON
cat > "$fixture/project/composer.json" <<'JSON'
{"name":"phpaml/view-test","require":{"php":"^8.2"},"autoload":{"psr-4":{"App\\":"app/"}},"config":{"vendor-dir":"runtime"}}
JSON
printf 'APP_ENV=local\n' > "$fixture/project/.env.example"
cp "$fixture/project/.env.example" "$fixture/project/.env"
cat > "$fixture/project/public/index.php" <<'PHP'
<?php
$root = dirname(__DIR__);
$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
require_once $root . '/runtime/autoload.php';
\PHPAML\Config\Env::load($root . '/.env');
$config = require $root . '/configs/app.php';
PHP
cat > "$fixture/composer" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> composer-invocations.log
mkdir -p runtime
touch runtime/autoload.php
SH
chmod +x "$fixture/composer"

cd "$fixture/project"
AML_LANG=en AML_COMPOSER_BINARY="$fixture/composer" "$php_bin" "$root/cli/aml.php" install view > first-install.log

grep -q "require phpaml/view:\^0.1@beta" composer-invocations.log
grep -q "AML View installed" first-install.log
test -f app/View/Pages/HomeViewPage.php
test -f app/View/Layouts/AppViewLayout.php
test -f app/View/ViewRegistry.php
test -f app/View/page.php
test -f app/View/interaction.php
grep -q "'/_aml/view'" public/index.php
grep -q "'/aml-view'" public/index.php
test "$(grep -c 'AML View integration' public/index.php)" -eq 1
grep -Eq '^AML_VIEW_SECRET=[a-f0-9]{64}$' .env
grep -q '^AML_VIEW_SECRET=$' .env.example
"$php_bin" -r '$m=json_decode(file_get_contents("phpaml.json"),true,512,JSON_THROW_ON_ERROR); if (($m["modules"]["view"]["package"] ?? null) !== "phpaml/view") exit(1);'

secret_before="$(grep '^AML_VIEW_SECRET=' .env)"
AML_LANG=en AML_COMPOSER_BINARY="$fixture/composer" "$php_bin" "$root/cli/aml.php" install view > second-install.log
test "$(grep -c 'AML View integration' public/index.php)" -eq 1
test "$secret_before" = "$(grep '^AML_VIEW_SECRET=' .env)"

AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-page Account
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-component Navigation
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-layout Dashboard
test -f app/View/Pages/AccountPage.php
test -f app/View/Components/NavigationComponent.php
test -f app/View/Layouts/DashboardLayout.php
find app/View -name '*.php' -print0 | xargs -0 -n1 "$php_bin" -l >/dev/null

echo "view smoke: OK"
