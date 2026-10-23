<?php
declare(strict_types=1);

define('PHPAML_CLI_LIBRARY_MODE', true);
require dirname(__DIR__) . '/cli/aml.php';

if (($argv[1] ?? '') === '--child') {
    writeNewFile($argv[2], $argv[3], 'synthetic');
    exit(0);
}

$fixture = sys_get_temp_dir() . '/phpaml-file-boundary-' . bin2hex(random_bytes(8));
mkdir($fixture . '/project', 0700, true);
mkdir($fixture . '/outside', 0700);
$checks = 0;
$run = static function (string $relative, bool $allowed) use ($fixture, &$checks): void {
    $process = proc_open([PHP_BINARY, __FILE__, '--child', $fixture . '/project', $relative],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
        env_vars: array_merge(getenv(), ['AML_LANG' => 'en']));
    fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process); $checks++;
    if (($status === 0) !== $allowed) throw new RuntimeException('New-file boundary check failed: ' . $checks);
};
try {
    $run('src/Ordinary.php', true);
    $run('src/Ordinary.php', true); // Existing files remain unchanged.
    foreach (['../outside/escaped.txt', '/absolute.txt', 'C:/absolute.txt', 'file:stream', "bad\nname", './ambiguous.txt', 'nested/.. /outside.txt', 'trailing.', 'CON', 'NUL.txt'] as $path) $run($path, false);
    if (PHP_OS_FAMILY !== 'Windows') {
        symlink($fixture . '/outside', $fixture . '/project/linked');
        symlink($fixture . '/outside/leaf.txt', $fixture . '/project/leaf.txt');
        $run('linked/escaped.txt', false);
        $run('leaf.txt', false);
    }
    if (glob($fixture . '/outside/*') !== []) throw new RuntimeException('A file was created outside the project.');
    echo "New-file security: {$checks} checks passed.\n";
} finally {
    removeGeneratedPath($fixture);
}
