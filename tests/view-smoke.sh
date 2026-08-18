#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
php_bin="${PHP_BINARY:-php}"
trap 'rm -rf "$fixture"' EXIT

template="$fixture/template/phpaml-template-0.0.0"
mkdir -p "$template/public/img" "$template/public/css" "$template/public/js" "$template/app/views" "$template/app/Controllers" "$template/app/Models" "$template/configs" "$template/runtime/framework"
touch "$template/public/img/favicon.svg" "$template/public/css/index.css" "$template/public/js/main.js"
touch "$template/app/views/.gitkeep"
cat > "$template/app/Controllers/HomeController.php" <<'PHP'
<?php
namespace App\Controllers;
use App\Models\HomeModel;
final class HomeController {
    public function index() { return $this->view('home.php', ['model' => new HomeModel()]); }
}
PHP
printf '%s\n' '<?php namespace App\Models; final class HomeModel {}' > "$template/app/Models/HomeModel.php"
cat > "$template/phpaml.json" <<'JSON'
{"name":"template","version":"1.0.0","runtime":{"directory":"runtime"},"modules":{}}
JSON
cat > "$template/composer.json" <<'JSON'
{"name":"phpaml/view-test","require":{"php":"^8.2"},"autoload":{"psr-4":{"App\\":"app/"}},"config":{"vendor-dir":"runtime"}}
JSON
printf 'APP_ENV=local\n' > "$template/.env.example"
mkdir -p "$template/runtime/framework/Security"
touch "$template/runtime/framework/Autoloader.php" "$template/runtime/aml-installed.json"
cat > "$template/runtime/framework/Security/CspNonce.php" <<'PHP'
<?php namespace PHPAML\Security; final class CspNonce {}
PHP
cat > "$template/configs/app.php" <<'PHP'
<?php
use App\Controllers\HomeController;
return [
    'views_path' => dirname(__DIR__) . '/app/views',
    'routes' => [
        'GET /' => ['handler' => [HomeController::class, 'index'], 'name' => 'home'],
    ],
];
PHP
cat > "$template/public/index.php" <<'PHP'
<?php
$root = dirname(__DIR__);
$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (PHP_SAPI === 'cli-server' && $requestPath === '/_aml/live-reload') {
    $fingerprint = [];
    foreach ([$root . '/app', $root . '/configs', $root . '/database', __DIR__] as $watchedRoot) {
        if (!is_dir($watchedRoot)) continue;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($watchedRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) if ($file->isFile()) $fingerprint[] = $file->getPathname() . ':' . $file->getMTime();
    }
    echo json_encode(['version' => sha1(implode('|', $fingerprint))]); return;
}
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
mkdir -p runtime/phpaml/view/src runtime/phpaml/engine/src
cat > runtime/phpaml/view/src/FileApplication.php <<'PHP'
<?php namespace AML\View; final class FileApplication {}
PHP
cat > runtime/phpaml/engine/src/EngineRuntime.php <<'PHP'
<?php namespace AML\Engine; final class EngineRuntime {}
PHP
cat > runtime/autoload.php <<'PHP'
<?php
require_once __DIR__ . '/phpaml/view/src/FileApplication.php';
require_once __DIR__ . '/phpaml/engine/src/EngineRuntime.php';
require_once __DIR__ . '/framework/Security/CspNonce.php';
PHP
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

grep -q "require phpaml/view:\^0.1.0-beta.3 phpaml/engine:\^0.1@beta" composer-invocations.log
grep -q "AML View installed" ../create.log
"$php_bin" -r 'require "runtime/autoload.php"; exit(class_exists("AML\\View\\FileApplication") && class_exists("AML\\Engine\\EngineRuntime") && class_exists("PHPAML\\Security\\CspNonce") ? 0 : 1);'
test ! -d src/views/templates
test -f src/controllers/HomeController.php
test -f src/models/HomeModel.php
test ! -d src/server
test ! -d app
test -f src/views/pages/home/page.php
test -f src/views/pages/about/page.php
test -f src/views/layouts/AppLayout.php
test -f src/views/states/NotFound.php
test -f src/views/states/Loading.php
test -f src/views/states/Error.php
test -f src/views/components/Navigation.php
grep -q 'function Navigation(): Navigation' src/views/components/Navigation.php
grep -q 'Navigation(),' src/views/layouts/AppLayout.php
! grep -q 'new Navigation()' src/views/layouts/AppLayout.php
! grep -q 'new Element(' src/views/pages/home/page.php
test -d src/controllers
test -d src/models
test -d src/views
test ! -f src/ViewRegistry.php
test -f src/views/stylesheets/base.css
test -f src/views/stylesheets/pages/home.css
test -f src/views/stylesheets/components/navigation.css
test -f src/views/stylesheets/layouts/app.css
test -f src/views/stylesheets/states/route-states.css
test -f src/views/themes/light/tokens.css
test -f src/views/themes/dark/tokens.css
test -f tests/aml-view.php
test ! -f public/css/aml-view.css
test -d public/assets
test -f public/favicon.svg
test ! -d public/img
test ! -d public/css
test ! -d public/js
test "$(find public src -name '*.php' -exec grep -l '<!doctype html>' {} + | wc -l | tr -d ' ')" = "1"
grep -q '\\AML\\View\\FileApplication' public/index.php
grep -q '\$viewApp->mount(\$requestPath)' public/index.php
grep -q '\$application->handle(\$request' public/index.php
grep -q 'use (\$application, \$viewApp, \$requestPath)' public/index.php
grep -q '\$session->csrfMeta()' public/index.php
grep -q '\\PHPAML\\Http\\Response::html' public/index.php
grep -q '/_aml/styles.css' public/index.php
grep -q 'AML\\Engine\\EngineRuntime::script' public/index.php
grep -q 'EngineRuntime::script($cspNonce)' public/index.php
grep -q 'PHPAML\\Security\\CspNonce::from($viewRequest)' public/index.php
grep -q "\$root . '/src', \$root . '/configs'" public/index.php
grep -q 'meta name="aml-live-reload"' public/index.php
! grep -q '/_aml/view' public/index.php
! grep -q 'BrowserRuntime::script' public/index.php
grep -q 'ClientAction::increment' src/views/pages/home/page.php
grep -q "Shared('demo.count')" src/views/pages/home/page.php
grep -q "Persisted('local', 'phpaml.demo.count')" src/views/pages/home/page.php
grep -q "Shared('demo.count')" src/views/pages/about/page.php
grep -q "StateRef::to('count'" src/views/pages/home/page.php
grep -q "bindClient('name')" src/views/pages/home/page.php
grep -q "Api::get('/api/health')" src/views/pages/home/page.php
grep -q "Actions::sequence" src/views/pages/home/page.php
grep -q "Actions::when" src/views/pages/home/page.php
grep -q "showWhen" src/views/pages/home/page.php
grep -q "classWhen" src/views/pages/home/page.php
grep -q "disabledWhen" src/views/pages/home/page.php
grep -q "Each(StateRef::to('tasks'" src/views/pages/home/page.php
grep -q "ClientAction::append" src/views/pages/home/page.php
grep -q '/favicon.svg' public/index.php
grep -q "Image('/phpaml-logo-violet-lime.png'" src/views/pages/home/page.php
grep -q 'MainContent(Group(' src/views/pages/home/page.php
grep -q -- "->class('view-hero', 'shell')" src/views/pages/home/page.php
grep -q 'ThemeProvider(' src/views/layouts/AppLayout.php
grep -q "ThemeSwitcher('light', 'dark', 'system')" src/views/components/Navigation.php
grep -q '\$viewApp->styles()' public/index.php
grep -q '"App\\\\": "src/"' composer.json
grep -q '"App\\\\Views\\\\": "src/views/"' composer.json
grep -q '"App\\\\Views\\\\Pages\\\\": "src/views/pages/"' composer.json
grep -q '"App\\\\Views\\\\Components\\\\": "src/views/components/"' composer.json
grep -q '"App\\\\Views\\\\Layouts\\\\": "src/views/layouts/"' composer.json
grep -q '"App\\\\Views\\\\States\\\\": "src/views/states/"' composer.json
grep -q '"App\\\\Controllers\\\\": "src/controllers/"' composer.json
grep -q '"App\\\\Models\\\\": "src/models/"' composer.json
grep -q '"App\\\\Middleware\\\\": "src/middleware/"' composer.json
grep -q '"App\\\\Services\\\\": "src/services/"' composer.json
! grep -q 'App\\\\Server' composer.json
grep -q 'App\\Controllers' configs/app.php
! grep -q 'views_path' configs/app.php
grep -q 'GET /api/health' configs/app.php
grep -q '\$this->json' src/controllers/HomeController.php
! grep -q '\$this->view' src/controllers/HomeController.php
"$php_bin" -r '$m=json_decode(file_get_contents("phpaml.json"),true,512,JSON_THROW_ON_ERROR); if (($m["modules"]["view"]["package"] ?? null) !== "phpaml/view") exit(1);'
"$php_bin" -r '$m=json_decode(file_get_contents("phpaml.json"),true,512,JSON_THROW_ON_ERROR); if (($m["modules"]["view"]["mode"] ?? null) !== "frontend") exit(1);'
"$php_bin" -r '$m=json_decode(file_get_contents("phpaml.json"),true,512,JSON_THROW_ON_ERROR); if (($m["modules"]["engine"]["package"] ?? null) !== "phpaml/engine") exit(1);'

if AML_LANG=en "$php_bin" "$root/cli/aml.php" install view > retired.log 2>&1; then
  echo "aml install view should be retired" >&2
  exit 1
fi
grep -q "create-view-app" retired.log

AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-page Account
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-component UserMenu
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-layout Dashboard
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-page 'users/[id]'
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-page 'docs/[...slug]'
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-loading dashboard
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-error dashboard
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:view-not-found dashboard
AML_LANG=en "$php_bin" "$root/cli/aml.php" make:controller ApiUser
test -f src/views/pages/account/page.php
test -f src/views/components/UserMenu.php
grep -q 'function UserMenu(mixed ...\$arguments): UserMenu' src/views/components/UserMenu.php
test -f src/views/layouts/DashboardLayout.php
test -f 'src/views/pages/users/[id]/page.php'
test -f 'src/views/pages/docs/[...slug]/page.php'
test -f src/views/states/dashboard/Loading.php
test -f src/views/states/dashboard/Error.php
test -f src/views/states/dashboard/NotFound.php
test -f src/controllers/ApiUserController.php
find src -name '*.php' -print0 | xargs -0 -n1 "$php_bin" -l >/dev/null

AML_LANG=en AML_CACHE_HOME="$fixture/cache" AML_COMPOSER_BINARY="$fixture/composer" \
  "$php_bin" "$root/cli/aml.php" install i18n > i18n-install.log
grep -q 'require phpaml/i18n:\^0.1@beta' composer-invocations.log
test -f src/locales/en/common.json
test -f src/locales/fr/common.json
grep -q '^APP_LOCALE=en$' .env
grep -q '^APP_FALLBACK_LOCALE=fr$' .env
grep -q 'PHPAML i18n integration' public/index.php
grep -q 'I18n::configure' public/index.php
"$php_bin" -r '$m=json_decode(file_get_contents("phpaml.json"),true,512,JSON_THROW_ON_ERROR); if (($m["modules"]["i18n"]["package"] ?? null) !== "phpaml/i18n") exit(1);'
AML_LANG=en "$php_bin" "$root/cli/aml.php" i18n:check > i18n-check.log
grep -q 'en.*complete' i18n-check.log
AML_LANG=en "$php_bin" "$root/cli/aml.php" i18n:list > i18n-list.log
grep -q '^en$' i18n-list.log
grep -q '^fr$' i18n-list.log

echo "view smoke: OK"
