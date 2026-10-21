<?php
declare(strict_types=1);

// Check the actual installed/extracted distribution against the CI checkout.
// Native runtime execution is tested by console-package.php and the launcher.
$package = isset($argv[1]) ? realpath($argv[1]) : false;
if ($package === false || !is_dir($package)) {
    fwrite(STDERR, "Usage: php tests/package-integrity.php <package-directory>\n");
    exit(2);
}
$root = dirname(__DIR__);
$files = ['phpaml.json' => 'phpaml.json'];
foreach (glob($root . '/cli/*.php') ?: [] as $source) {
    $files['runtime/bin/' . basename($source)] = 'cli/' . basename($source);
}
foreach ($files as $shipped => $source) {
    $path = $package . '/' . $shipped;
    if (!is_file($path) || hash_file('sha256', $path) !== hash_file('sha256', $root . '/' . $source)) {
        throw new RuntimeException("Package content differs from the validated checkout: {$shipped}");
    }
}
$metadata = json_decode((string) file_get_contents($package . '/phpaml.json'), true, 512, JSON_THROW_ON_ERROR);
$version = $metadata['version'];
$expectedPhp = $metadata['runtime']['php_version'] ?? '';
if (!is_string($expectedPhp) || preg_match('/^\d+\.\d+\.\d+$/D', $expectedPhp) !== 1) {
    throw new RuntimeException('The distribution must pin an exact PHP release.');
}
foreach (['craft.yml', 'craft-windows.yml'] as $config) {
    if (preg_match('/^php-version:\s*[\'"]?' . preg_quote($expectedPhp, '/') . '[\'"]?\s*$/m', (string) file_get_contents($root . '/' . $config)) !== 1) {
        throw new RuntimeException("The PHP build configuration is not pinned to {$expectedPhp}: {$config}");
    }
}
$binary = realpath($package . (PHP_OS_FAMILY === 'Windows' ? '/runtime/php/php.exe' : '/runtime/php/bin/php'));
$running = realpath(PHP_BINARY);
if ($binary === false || $running === false || (PHP_OS_FAMILY === 'Windows' ? strcasecmp($binary, $running) !== 0 : $binary !== $running)) {
    throw new RuntimeException('Run this verification with the PHP executable inside the package.');
}
if (PHP_VERSION !== $expectedPhp) {
    throw new RuntimeException('Bundled PHP version mismatch: expected ' . $expectedPhp . ', got ' . PHP_VERSION . '.');
}
echo 'Package integrity passed: ' . count($files) . " exact files; CLI {$version}; PHP " . PHP_VERSION . ".\n";
