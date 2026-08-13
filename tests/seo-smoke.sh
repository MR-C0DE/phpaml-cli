#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/phpaml-seo-test.XXXXXX")"
cleanup() {
    rm -rf "$fixture"
}
trap cleanup EXIT HUP INT TERM

mkdir -p "$fixture/runtime/bin" "$fixture/project/public" \
    "$fixture/project/configs" "$fixture/project/runtime"
cp "$root/cli/aml.php" "$fixture/runtime/bin/aml.php"
cp "$root/cli/ai-debug.php" "$fixture/runtime/bin/ai-debug.php"
cp "$root/cli/deploy.php" "$fixture/runtime/bin/deploy.php"
cp "$root/phpaml.json" "$fixture/phpaml.json"
cp "$root/phpaml.json" "$fixture/project/phpaml.json"

printf '%s\n' 'APP_URL=http://localhost:18765' > "$fixture/project/.env"
printf '%s\n' '<?php namespace PHPAML\Config; final class Env { public static function load(string $path): void {} }' > "$fixture/project/runtime/autoload.php"
printf '%s\n' '<?php return ["routes" => ["GET /" => [], "GET /about" => [], "GET /admin" => [], "GET /admin/users" => [], "GET /admin/public" => [], "GET /post/{id}" => [], "POST /contact" => []]];' > "$fixture/project/configs/app.php"
printf '%s\n' '<!doctype html><html lang="en"><head><title>PHPAML SEO test</title><meta name="viewport" content="width=device-width"><meta name="description" content="A complete PHPAML SEO description created to validate the command line audit reliably."><link rel="canonical" href="http://localhost:18765/"><meta property="og:title" content="PHPAML SEO test"><meta name="twitter:card" content="summary_large_image"><script type="application/ld+json">{"@type":"WebSite"}</script></head><body><h1>PHPAML</h1><img src="logo.png" alt="PHPAML logo"></body></html>' > "$fixture/project/public/index.php"

cd "$fixture/project"
AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:init
AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:set title "PHPAML SEO test"
AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:set description "A complete PHPAML SEO description created to validate metadata from the command line."
AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:disallow /admin
AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:allow /admin/public

show_output="$(AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:show --json)"
grep -q '"title": "PHPAML SEO test"' <<<"$show_output"
grep -q '"type": "WebSite"' <<<"$show_output"

AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:generate
grep -q 'http://localhost:18765/about' public/sitemap.xml
if grep -q '<loc>http://localhost:18765/admin</loc>' public/sitemap.xml || grep -q 'http://localhost:18765/admin/users' public/sitemap.xml; then
    echo "Disallowed routes must not be written to the sitemap." >&2
    exit 1
fi
grep -q 'http://localhost:18765/admin/public' public/sitemap.xml
if grep -q 'post/{id}' public/sitemap.xml; then
    echo "Dynamic routes must not be written to the sitemap." >&2
    exit 1
fi
grep -q 'Sitemap: http://localhost:18765/sitemap.xml' public/robots.txt
grep -q 'Disallow: /admin' public/robots.txt
grep -q 'Allow: /admin/public' public/robots.txt

AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:remove allow /admin/public
if AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:show --json | grep -q '/admin/public'; then
    echo "The removed SEO rule is still configured." >&2
    exit 1
fi

audit_output="$(AML_LANG=en php "$fixture/runtime/bin/aml.php" seo:audit http://127.0.0.1:18765 --file public/index.php --json)"
grep -q '"healthy": true' <<<"$audit_output"
grep -q '"name": "Canonical"' <<<"$audit_output"

echo "SEO smoke: OK"
