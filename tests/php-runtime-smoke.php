<?php

declare(strict_types=1);

define('PHPAML_CLI_LIBRARY_MODE', true);
require dirname(__DIR__) . '/cli/aml.php';

function runtimeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Runtime test failed: {$message}\n");
        exit(1);
    }
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpaml-runtime-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0777, true) && !is_dir($root)) {
    throw new RuntimeException("Unable to create {$root}");
}

try {
    file_put_contents($root . '/composer.json', json_encode([
        'require' => [
            'php' => '^8.2',
            'ext-pdo' => '*',
            'ext-pdo_sqlite' => '*',
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($root . '/composer.lock', json_encode([
        'platform' => ['ext-json' => '*'],
        'packages' => [[
            'name' => 'phpaml/data-mongodb',
            'require' => ['ext-mongodb' => '^2.0'],
        ]],
        'packages-dev' => [[
            'name' => 'phpaml/test-support',
            'require' => ['ext-dom' => '*'],
        ]],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    runtimeAssert(
        projectRequiredPhpExtensions($root) === ['dom', 'json', 'mongodb', 'pdo', 'pdo_sqlite'],
        'Composer and lock requirements must be merged and sorted.'
    );

    $missing = [];
    runtimeAssert(phpRuntimeSupports(PHP_BINARY, [], $missing), 'A project without extensions must use current PHP.');
    runtimeAssert($missing === [], 'No requirements should be missing for the empty profile.');

    foreach (['pdo', 'pdo_sqlite'] as $extension) {
        runtimeAssert(extension_loaded($extension), "CI must provide ext-{$extension}.");
    }
    runtimeAssert(
        phpRuntimeSupports(PHP_BINARY, ['pdo', 'pdo_sqlite'], $missing),
        'The SQLite profile must accept the configured CI runtime.'
    );

    if (extension_loaded('mongodb')) {
        runtimeAssert(
            phpRuntimeSupports(PHP_BINARY, ['mongodb'], $missing),
            'The MongoDB profile must accept a runtime providing ext-mongodb.'
        );
    } else {
        runtimeAssert(
            !phpRuntimeSupports(PHP_BINARY, ['mongodb'], $missing) && in_array('mongodb', $missing, true),
            'A runtime without ext-mongodb must be rejected explicitly.'
        );
    }

    putenv('AML_PHP_BINARY=' . PHP_BINARY);
    runtimeAssert(phpRuntimeCandidates()[0] === PHP_BINARY, 'AML_PHP_BINARY must have priority.');
    runtimeAssert(compatibleProjectPhp(['pdo', 'pdo_sqlite']) === PHP_BINARY, 'The compatible runtime must be selected.');
    putenv('AML_PHP_BINARY');

    runtimeAssert(
        !phpRuntimeSupports(PHP_BINARY, ['phpaml_extension_that_does_not_exist'], $missing)
            && $missing === ['phpaml_extension_that_does_not_exist'],
        'Missing extensions must be reported by name.'
    );
} finally {
    @unlink($root . '/composer.json');
    @unlink($root . '/composer.lock');
    @rmdir($root);
}

echo "PHP runtime selection passed.\n";
