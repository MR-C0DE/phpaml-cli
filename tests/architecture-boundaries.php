<?php

declare(strict_types=1);

/**
 * Protect the dependency direction between the public PHPAML packages.
 *
 * Repositories are supplied by CI through PHPAML_PACKAGES_ROOT. Locally the
 * script also understands the runtime/build layout used by the workspace.
 */

$root = getenv('PHPAML_PACKAGES_ROOT');
$root = is_string($root) && $root !== '' ? $root : dirname(__DIR__, 2);

$packages = [
    'framework' => ['directory' => 'phpaml-framework', 'allowed' => []],
    'engine' => ['directory' => 'phpaml-engine', 'allowed' => []],
    'view' => ['directory' => 'phpaml-view', 'allowed' => ['phpaml/engine']],
    'data' => ['directory' => 'phpaml-data', 'allowed' => []],
    'i18n' => ['directory' => 'phpaml-i18n', 'allowed' => []],
];

$packageNames = [
    'phpaml/framework',
    'phpaml/engine',
    'phpaml/view',
    'phpaml/data',
    'phpaml/data-mongodb',
    'phpaml/i18n',
];

$namespaceOwners = [
    '~(?<!PHP)AML\\\\View\\\\~' => 'view',
    '~(?<!PHP)AML\\\\Engine\\\\~' => 'engine',
    '~(?<!PHP)AML\\\\Data\\\\~' => 'data',
    '~(?<!PHP)AML\\\\I18n\\\\~' => 'i18n',
];

// Temporary, documented compatibility bridge. No other Framework file may
// acquire knowledge of Data while this bridge is being replaced by bootstrap
// registration owned by the application.
$legacySourceExceptions = [
    'framework' => ['src/WebApplication.php' => ['data']],
];

$errors = [];
$checked = 0;

foreach ($packages as $name => $policy) {
    $directory = $root . '/' . $policy['directory'];
    if (!is_dir($directory)) {
        $errors[] = "Missing package checkout: {$directory}";
        continue;
    }

    $composerPath = $directory . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);
    if (!is_array($composer)) {
        $errors[] = "Invalid composer.json: {$composerPath}";
        continue;
    }

    $requires = is_array($composer['require'] ?? null) ? $composer['require'] : [];
    foreach ($packageNames as $dependency) {
        if (!array_key_exists($dependency, $requires)) {
            continue;
        }
        if (!in_array($dependency, $policy['allowed'], true)) {
            $errors[] = "{$name} must not require {$dependency}";
        }
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory . '/src', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($directory) + 1);
        $source = (string) file_get_contents($file->getPathname());
        foreach ($namespaceOwners as $namespacePattern => $owner) {
            if ($owner === $name || preg_match($namespacePattern, $source) !== 1) {
                continue;
            }
            if ($name === 'view' && $owner === 'engine') {
                continue;
            }
            $exceptions = $legacySourceExceptions[$name][$relative] ?? [];
            if (in_array($owner, $exceptions, true)) {
                continue;
            }
            $errors[] = "{$name}/{$relative} references the {$owner} package";
        }
    }

    $checked++;
}

if ($errors !== []) {
    fwrite(STDERR, "Architecture boundary violations:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Architecture boundaries passed for {$checked} independent packages.\n";
