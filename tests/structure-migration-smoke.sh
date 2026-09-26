#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
FIXTURE=$(mktemp -d "${TMPDIR:-/tmp}/phpaml-structure-test.XXXXXX")
trap 'rm -rf "$FIXTURE"' EXIT HUP INT TERM

mkdir -p "$FIXTURE/cli/runtime/bin" "$FIXTURE/project/public" "$FIXTURE/project/aml_env/framework" "$FIXTURE/project/configs" "$FIXTURE/project/app/View/Pages" "$FIXTURE/project/app/Controllers" "$FIXTURE/project/app/Models" "$FIXTURE/project/app/views" "$FIXTURE/project/routes" "$FIXTURE/project/database/migrations" "$FIXTURE/project/database/seeders"
cp "$ROOT/cli/aml.php" "$FIXTURE/cli/runtime/bin/aml.php"
cp "$ROOT/cli/ai-debug.php" "$FIXTURE/cli/runtime/bin/ai-debug.php"
cp "$ROOT/cli/deploy.php" "$FIXTURE/cli/runtime/bin/deploy.php"
cp "$ROOT/phpaml.json" "$FIXTURE/cli/phpaml.json"
touch "$FIXTURE/project/public/index.php"
printf '%s\n' '<?php namespace App\View\Pages; final class HomePage {}' > "$FIXTURE/project/app/View/Pages/HomePage.php"
printf '%s\n' '<?php namespace App\Controllers; final class HomeController {}' > "$FIXTURE/project/app/Controllers/HomeController.php"
printf '%s\n' '<?php namespace App\Models; final class HomeModel {}' > "$FIXTURE/project/app/Models/HomeModel.php"
printf '%s\n' '<?php echo "home";' > "$FIXTURE/project/app/views/home.php"
printf '%s\n' '<?php namespace App\Routes; final class WebApp {}' > "$FIXTURE/project/routes/WebApp.php"
printf '%s\n' "<?php require __DIR__ . '/../app/View/page.php';" > "$FIXTURE/project/public/view-entry.php"

printf '%s\n' '{"name":"demo","aml":{"environment":"aml_env","framework":"0.1.0"},"dependencies":{"php":"^8.2"}}' > "$FIXTURE/project/info.json"
printf '%s\n' "<?php return ['runtime' => __DIR__ . '/../aml_env'];" > "$FIXTURE/project/configs/app.php"
printf '%s\n' "<?php return ['migrations_path' => dirname(__DIR__) . '/database/migrations'];" > "$FIXTURE/project/configs/data.php"
printf '%s\n' '{"autoload":{"psr-4":{"App\\Controllers\\":"app/Controllers/","App\\Models\\":"app/Models/","App\\Routes\\":"routes/"}}}' > "$FIXTURE/project/composer.json"
touch "$FIXTURE/project/database/migrations/.gitkeep" "$FIXTURE/project/database/seeders/.gitkeep"

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
test -d "$FIXTURE/project/src/View"
test -d "$FIXTURE/project/src/controllers"
test -d "$FIXTURE/project/src/models"
test -d "$FIXTURE/project/src/views"
test -d "$FIXTURE/project/src/routes"
test ! -e "$FIXTURE/project/app"
test ! -e "$FIXTURE/project/routes"
test ! -e "$FIXTURE/project/database"
test -d "$FIXTURE/project/runtime/database/migrations"
test -d "$FIXTURE/project/runtime/database/seeders"
grep -q 'namespace App\\View\\Pages' "$FIXTURE/project/src/View/Pages/HomePage.php"
grep -q '../src/View/page.php' "$FIXTURE/project/public/view-entry.php"
grep -q 'src/controllers/' "$FIXTURE/project/composer.json"
grep -q 'src/models/' "$FIXTURE/project/composer.json"
grep -q 'src/routes/' "$FIXTURE/project/composer.json"
grep -q '"directory": "runtime"' "$FIXTURE/project/phpaml.json"
grep -q '"requirements"' "$FIXTURE/project/phpaml.json"
grep -q "../runtime" "$FIXTURE/project/configs/app.php"
grep -q "runtime/database/migrations" "$FIXTURE/project/configs/data.php"
find "$FIXTURE/project/runtime/storage/migrations" -type f | grep -q .

mkdir -p "$FIXTURE/conflict/public" "$FIXTURE/conflict/aml_env" "$FIXTURE/conflict/runtime"
touch "$FIXTURE/conflict/public/index.php" "$FIXTURE/conflict/info.json"
if (cd "$FIXTURE/conflict" && php "$FIXTURE/cli/runtime/bin/aml.php" migrate:structure --apply --yes >/dev/null 2>&1); then
    echo 'A conflicting structure should be rejected.' >&2
    exit 1
fi

echo 'Structure migration tests passed.'
