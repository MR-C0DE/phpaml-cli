<?php
declare(strict_types=1);

// Only the full compatibility test changes the existing integration tag. The
// default modular test serves the generated entrypoint without modification.
$root = (string) getenv('PHPAML_FRONTEND_APP');
$public = $root . '/public';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = realpath($public . $path);
if ($path !== '/' && $file !== false && str_starts_with($file, $public . '/') && is_file($file)) return false;
ob_start(static function (string $html): string {
    return getenv('PHPAML_BROWSER_RUNTIME') === 'full' && str_contains($html, ' data-aml-engine')
        ? str_replace(AML\Engine\EngineRuntime::externalScript(modular: true), AML\Engine\EngineRuntime::externalScript(), $html)
        : $html;
});
require $public . '/index.php';
