<?php
declare(strict_types=1);
define('PHPAML_CLI_LIBRARY_MODE', true);
require dirname(__DIR__) . '/cli/aml.php';
require dirname(__DIR__) . '/cli/lucide.php';

$root = sys_get_temp_dir() . '/aml-lucide-test-' . bin2hex(random_bytes(8));
mkdir($root . '/public', 0700, true); mkdir($root . '/src', 0700); mkdir($root . '/cache', 0700);
file_put_contents($root . '/public/index.php', '<?php');
file_put_contents($root . '/phpaml.json', '{"name":"icons","modules":{"other":{"version":"1"}}}');
$checks = 0;
$check = static function (bool $ok, string $reason) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($reason); };
$run = static function (array $args, bool $success) use ($root, $check): string {
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/cli/aml.php', ...$args], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root,
        array_merge(getenv(), ['AML_LANG' => 'en', 'AML_CACHE_HOME' => $root . '/cache']));
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); fclose($pipes[1]); $out .= stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process); $check(($status === 0) === $success, 'Unexpected command result: ' . $out); return $out;
};
try {
    $valid = '<svg viewBox="0 0 24 24"><path d="M0 0h24v24z" /><circle cx="12" cy="12" r="1" fill="currentColor" /></svg>';
    $check(count(lucideGeometry($valid)) === 2, 'Geometry parser lost shapes.');
    foreach (['<svg><script /></svg>', '<svg><path d="M0 0" onload="bad" /></svg>', '<svg><use href="https://example.com" /></svg>', '<!DOCTYPE svg><svg><path d="M0 0" /></svg>', '<svg><circle fill="url(#x)" /></svg>'] as $invalid) {
        try { lucideGeometry($invalid); $check(false, 'Unsafe SVG accepted.'); } catch (RuntimeException $error) { $check($error->getMessage() !== 'Unsafe SVG accepted.', 'Unsafe SVG accepted.'); }
    }
    $run(['install', 'lucide', '--offline'], false);
    $run(['install', 'lucide', '--version', '../bad'], false);
    $run(['uninstall', 'unknown'], false);
    $archiveIndex = array_search('--archive', $argv, true);
    if ($archiveIndex !== false) {
        copy($argv[$archiveIndex + 1], $root . '/cache/lucide-static-' . AML_LUCIDE_VERSION . '.tgz');
        $run(['install', 'Lucide', '--offline'], true);
        $run(['install', 'lucide', '--offline'], true);
        $check(count(lucideManagedFiles($root, AML_LUCIDE_VERSION)) > 2000, 'Official catalogue incomplete.');
        $heart = $root . '/' . lucideRelativePath(AML_LUCIDE_VERSION) . '/heart.json';
        unlink($heart); $run(['install', 'lucide', '--offline'], true); $check(is_file($heart), 'Missing asset not repaired.');
    } else {
        lucideWriteCatalogue($root, ['LICENSE' => 'Synthetic test geometry', 'heart.json' => json_encode(lucideGeometry($valid))]);
        $manifest = projectInfo($root); $manifest['modules']['lucide'] = ['version' => AML_LUCIDE_VERSION]; writeProjectManifest($root, $manifest);
    }
    $directory = $root . '/' . lucideRelativePath(AML_LUCIDE_VERSION);
    file_put_contents($root . '/src/page.php', '<?php use function AML\\View\\Icon as Glyph; Glyph("heart");');
    $run(['uninstall', 'lucide'], false);
    $check(is_file($directory . '/heart.json'), 'Refused removal changed assets.');
    file_put_contents($directory . '/personal.svg', '<svg/>');
    file_put_contents($directory . '/heart.json', 'modified by user');
    if ($archiveIndex !== false) $run(['install', 'lucide', '--offline'], false);
    $run(['uninstall', 'lucide', '--force'], true);
    $check(file_get_contents($directory . '/heart.json') === 'modified by user' && is_file($directory . '/personal.svg'), 'User content was removed.');
    $manifest = projectInfo($root);
    $check(!isset($manifest['modules']['lucide']) && isset($manifest['modules']['other']), 'Uninstall damaged other dependencies.');
    $run(['uninstall', 'lucide'], true);
    if (PHP_OS_FAMILY !== 'Windows') {
        unlink($directory . '/heart.json'); unlink($directory . '/personal.svg'); rmdir($directory);
        mkdir($root . '/outside'); symlink($root . '/outside', $directory);
        $manifest['modules']['lucide'] = ['version' => AML_LUCIDE_VERSION]; writeProjectManifest($root, $manifest);
        $run(['uninstall', 'lucide', '--force'], false);
        $check(scandir($root . '/outside') === ['.', '..'], 'Symlink escaped the project.');
        unlink($directory);
    }
    file_put_contents($root . '/cache/lucide-static-' . AML_LUCIDE_VERSION . '.tgz', 'damaged archive');
    $run(['install', 'lucide', '--offline'], false);
    echo "Lucide CLI: {$checks} checks passed.\n";
} finally { removeGeneratedPath($root); }
