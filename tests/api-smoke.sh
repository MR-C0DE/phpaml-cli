#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
php_bin="${PHP_BINARY:-php}"
trap 'rm -rf "$fixture"' EXIT

mkdir -p "$fixture/configs" "$fixture/public"
printf '%s\n' '{"name":"api-test","runtime":{"directory":"runtime"},"modules":{}}' > "$fixture/phpaml.json"
printf '%s\n' '<?php' > "$fixture/public/index.php"
cat > "$fixture/configs/app.php" <<'PHP'
<?php
declare(strict_types=1);
return [
    'routes' => [
    ],
];
PHP

cd "$fixture"
AML_LANG=fr "$php_bin" "$root/cli/aml.php" api:install
AML_LANG=fr "$php_bin" "$root/cli/aml.php" make:api Product --auth --read-ability products.read --write-ability products.write

test -f configs/api.php
test -f configs/api-routes.php
test -f app/Controllers/Api/ProductController.php
grep -q "require __DIR__ . '/api.php'" configs/app.php
grep -q "'project_root' => dirname(__DIR__)" configs/app.php
grep -q "GET /api/v1/products" configs/api-routes.php
grep -q "DELETE /api/v1/products/{id}" configs/api-routes.php
grep -q "AbilityMiddleware::class" configs/api-routes.php
grep -q "products.write" configs/api-routes.php
"$php_bin" -l configs/app.php >/dev/null
"$php_bin" -l configs/api-routes.php >/dev/null
"$php_bin" -l app/Controllers/Api/ProductController.php >/dev/null

# Persistent resources can receive fields after their initial generation.
mkdir -p src/views
mkdir -p runtime
cat > runtime/autoload.php <<'PHP'
<?php
namespace AML\Data;
abstract class Entity {}
PHP
cat > configs/data.php <<'PHP'
<?php
return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => __DIR__ . '/../runtime/database.sqlite']]];
PHP
AML_LANG=fr "$php_bin" "$root/cli/aml.php" make:api Inventory --fields "name:string" --migration
AML_LANG=fr "$php_bin" "$root/cli/aml.php" api:add-field Inventory "sku:string?,stock:integer" --default "stock=0" --unique "sku" --index "stock"
grep -q 'public ?string $sku = null' src/models/Inventory.php
grep -q 'public int $stock = 0' src/models/Inventory.php
grep -q "'stock' => \['integer'\]" src/requests/UpdateInventoryRequest.php
grep -q "'sku', 'stock'" src/controllers/api/InventoryController.php
grep -q "string('sku')->nullable()" runtime/database/migrations/*_add_sku_stock_to_inventories_table.php
grep -q "index('sku', null, true)" runtime/database/migrations/*_add_sku_stock_to_inventories_table.php
grep -q "integer('stock')->default(0)" runtime/database/migrations/*_add_sku_stock_to_inventories_table.php
grep -q "index('stock')" runtime/database/migrations/*_add_sku_stock_to_inventories_table.php
"$php_bin" -l src/models/Inventory.php >/dev/null
"$php_bin" -l src/controllers/api/InventoryController.php >/dev/null
"$php_bin" -l runtime/database/migrations/*_add_sku_stock_to_inventories_table.php >/dev/null
AML_LANG=fr "$php_bin" "$root/cli/aml.php" api:rename-field Inventory sku code
grep -q 'public ?string $code = null' src/models/Inventory.php
grep -q "renameColumn('sku', 'code')" runtime/database/migrations/*_rename_sku_to_code_on_inventories_table.php
AML_LANG=fr "$php_bin" "$root/cli/aml.php" api:remove-field Inventory code
if grep -q '\$code' src/models/Inventory.php; then
    echo 'api:remove-field should remove the model property' >&2
    exit 1
fi
grep -q "dropColumn('code')" runtime/database/migrations/*_remove_code_from_inventories_table.php
if AML_LANG=fr "$php_bin" "$root/cli/aml.php" api:add-field Inventory "stock:integer" >/dev/null 2>&1; then
    echo 'api:add-field should refuse duplicate fields' >&2
    exit 1
fi

if AML_LANG=fr "$php_bin" "$root/cli/aml.php" make:api Product >/dev/null 2>&1; then
    echo 'make:api should refuse to overwrite an existing controller' >&2
    exit 1
fi

echo "api smoke: OK"
