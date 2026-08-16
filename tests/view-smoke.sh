#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
php_bin="${PHP_BINARY:-php}"
trap 'rm -rf "$fixture"' EXIT

template="$fixture/template/phpaml-template-0.0.0"
mkdir -p "$template/public" "$template/app/views" "$template/configs" "$template/runtime/framework"
touch "$template/app/views/.gitkeep"
cat > "$template/phpaml.json" <<'JSON'
{"name":"template","version":"1.0.0","runtime":{"directory":"runtime"},"modules":{}}
JSON
cat > "$template/composer.json" <<'JSON'
{"name":"phpaml/view-test","require":{"php":"^8.2"},"autoload":{"psr-4":{"App\\":"app/"}},"config":{"vendor-dir":"runtime"}}
JSON
printf 'APP_ENV=local\n' > "$template/.env.example"
touch "$template/runtime/framework/Autoloader.php" "$template/runtime/aml-installed.json"
cat > "$template/configs/app.php" <<'PHP'
<?php return [];
PHP
cat > "$template/public/index.php" <<'PHP'
<?php
$root = dirname(__DIR__);
$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
require_once $root . '/runtime/autoload.php';
\PHPAML\Config\Env::load($root . '/.env');
$config = require $root . '/configs/app.php';
PHP

cache="$fixture/cache/templates/0.0.0"
mkdir -p "$cache"
archive="$cache/phpaml-template-0.0.0.zip"
"$php_bin" -r '
$source=$argv[1]; $archive=$argv[2]; $zip=new ZipArchive();
$zip->open($archive, ZipArchive::CREATE|ZipArchive::OVERWRITE);
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
foreach($it as $file){$relative=substr($file->getPathname(),strlen($source)+1);$zip->addFile($file->getPathname(),"phpaml-template-0.0.0/".str_replace("\\","/",$relative));}
$zip->close();
' "$template" "$archive"
"$php_bin" -r 'echo hash_file("sha256",$argv[1]),"  ",basename($argv[1]),PHP_EOL;' "$archive" > "$archive.sha256"

cat > "$fixture/composer" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> composer-invocations.log
mkdir -p runtime
touch runtime/autoload.php
SH
chmod +x "$fixture/composer"

mkdir -p "$fixture/work"
cd "$fixture/work"
AML_LANG=en AML_CACHE_HOME="$fixture/cache" AML_COMPOSER_BINARY="$fixture/composer" \
  "$php_bin" "$root/cli/aml.php" create-view-app generated --offline --version 0.0.0 > create.log

AML_LANG=en AML_CACHE_HOME="$fixture/cache" \
  "$php_bin" "$root/cli/aml.php" create "$fixture/absolute-project" --offline --version 0.0.0 > absolute-create.log
test -f "$fixture/absolute-project/phpaml.json"
test ! -e "$fixture/work/$fixture/absolute-project"
test ! -d "$fixture/absolute-project/app/UI"

cd generated

grep -q "require phpaml/view:\^0.1@beta" composer-invocations.log
grep -q "AML View installed" ../create.log
test -d app/views
test -f app/UI/Pages/HomeViewPage.php
test -f app/UI/Layouts/AppViewLayout.php
test -f app/UI/ViewRegistry.php
test -f app/UI/page.php
test -f app/UI/interaction.php
grep -q "'/app/UI/interaction.php'" public/index.php
grep -q "'/app/UI/page.php'" public/index.php
grep -Eq '^AML_VIEW_SECRET=[a-f0-9]{64}$' .env
grep -q '^AML_VIEW_SECRET=$' .env.example
"$php_bin" -r '$m=json_decode(file_get_contents("phpaml.json"),true,512,JSON_THROW_ON_ERROR); if (($m["modules"]["view"]["package"] ?? null) !== "phpaml/view") exit(1);'

if AML_LANG=en "$php_bin" "$root/cli/aml.php" install view > retired.log 2>&1; then
  echo "aml install view should be retired" >&2
  exit 1
fi
grep -q "create-view-app" retired.log

AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-page Account
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-component Navigation
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-layout Dashboard
test -f app/UI/Pages/AccountPage.php
test -f app/UI/Components/NavigationComponent.php
test -f app/UI/Layouts/DashboardLayout.php
find app/UI -name '*.php' -print0 | xargs -0 -n1 "$php_bin" -l >/dev/null

echo "view smoke: OK"
