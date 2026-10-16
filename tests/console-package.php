<?php

declare(strict_types=1);

// Exercise a built or installed package through its real AML launcher.
// The test itself never installs a package or modifies its files.
$package = in_array(count($argv), [2, 3], true) ? realpath($argv[1]) : false;
if ($package === false || !is_dir($package)) {
    fwrite(STDERR, "Usage: php tests/console-package.php <package-directory> [installed-launcher-path]\n");
    exit(2);
}
$php = $package . (PHP_OS_FAMILY === 'Windows' ? '/runtime/php/php.exe' : '/runtime/php/bin/php');
$cli = $package . '/runtime/bin/aml.php';
$library = $package . '/runtime/bin/console.php';
$importExample = $package . '/runtime/bin/console-import-example.php';
$packagedLauncher = $package . (PHP_OS_FAMILY === 'Windows' ? '/bin/aml.exe' : '/bin/aml');
$launcher = $argv[2] ?? $packagedLauncher;
foreach ([$php, $cli, $library, $importExample, $packagedLauncher, $launcher, $package . '/phpaml.json'] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing console package file: {$file}\n");
        exit(1);
    }
}
if (PHP_OS_FAMILY === 'Windows'
    ? strcasecmp((string) realpath($launcher), (string) realpath($packagedLauncher)) !== 0
    : realpath($launcher) !== realpath($packagedLauncher)) {
    fwrite(STDERR, "The selected launcher does not belong to this package.\n");
    exit(2);
}
if (hash_file('sha256', $cli) !== hash_file('sha256', dirname(__DIR__) . '/cli/aml.php')) {
    fwrite(STDERR, "The package does not contain the current AML console generator.\n");
    exit(1);
}
if (hash_file('sha256', $library) !== hash_file('sha256', dirname(__DIR__) . '/cli/console.php')) {
    fwrite(STDERR, "The package does not contain the current console library.\n");
    exit(1);
}
if (hash_file('sha256', $importExample) !== hash_file('sha256', dirname(__DIR__) . '/cli/console-import-example.php')) {
    fwrite(STDERR, "The package does not contain the current console import example.\n");
    exit(1);
}

$fixture = sys_get_temp_dir() . '/phpaml-console-package-' . bin2hex(random_bytes(6));
$project = $fixture . '/console app André';
mkdir($fixture, 0700, true);
$environment = array_merge(getenv(), [
    'AML_LANG' => 'en', 'AML_CACHE_HOME' => $fixture . '/cache',
    'AML_TMPDIR' => $fixture, 'TMPDIR' => $fixture, 'TEMP' => $fixture, 'TMP' => $fixture,
    'COMPOSER_HOME' => $fixture . '/composer',
]);
$assertions = 0;
$expect = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$run = static function (array $arguments, string $cwd, string $input = '') use ($php, $cli, $launcher, $environment): array {
    $out = tmpfile();
    $err = tmpfile();
    $command = ($arguments[0] ?? null) === $cli
        ? [$launcher, ...array_slice($arguments, 1)]
        : [$php, ...$arguments];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => $out, 2 => $err], $pipes, $cwd, $environment, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot launch AML or its bundled PHP runtime.');
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $code = proc_close($process);
    rewind($out);
    rewind($err);
    $result = ['code' => $code, 'out' => (string) stream_get_contents($out), 'err' => (string) stream_get_contents($err)];
    fclose($out);
    fclose($err);
    return $result;
};
$success = static function (array $result, string $message) use ($expect): void {
    $expect($result['code'] === 0 && $result['err'] === '', $message . ': ' . json_encode($result));
};

try {
    $success($run([$cli, 'create-console', basename($project)], $fixture), 'Create a console from the packaged runtime');
    $expect(hash_file('sha256', $project . '/lib/console.php') === hash_file('sha256', $library), 'Generated projects receive the bundled library');
    $expect(hash_file('sha256', $project . '/src/Examples/ImportCsv.php') === hash_file('sha256', $importExample), 'Generated projects receive the bundled CSV example');
    $expect(!is_dir($project . '/public') && !is_dir($project . '/runtime'), 'Console generation does not install a Web framework');
    $result = $run([$cli, 'run', '--', 'André', '--repeat=2'], $project);
    $success($result, 'Run a generated program');
    $expect(substr_count($result['out'], 'André') === 2, 'Forward Unicode arguments and options');
    $result = $run([$cli, 'run', '--', '-i'], $project, "Marie\r\n");
    $expect($result['code'] === 0 && str_contains($result['out'], 'Marie') && $result['err'] !== '', 'Interactive input remains separated from program output');
    $result = $run([$cli, 'run', '--', '--repeat=0'], $project);
    $expect($result['code'] === 2 && $result['out'] === '', 'Launcher preserves input errors and stderr separation');
    $success($run([$cli, 'make:command', 'Admin/ImportUsers'], $project), 'Generate a nested console command');
    $success($run([$cli, 'run', 'admin:import-users', '--help'], $project), 'Run command-specific help');
    $success($run([$cli, 'test'], $project), 'Run tests through AML');
    $success($run(['tests/run.php'], $project), 'Run standalone tests with bundled PHP');
    $result = $run(['examples/display.php'], $project);
    $expect($result['code'] === 0 && str_contains($result['out'], 'clients.csv') && str_contains($result['err'], '3/3 (100%)'), 'Tables and progression work with bundled PHP');
    $expect(!str_contains($result['out'] . $result['err'], "\033"), 'Redirected package output has no terminal control sequences');
    $result = $run(['examples/display.php', '--fail'], $project);
    $expect($result['code'] === 2 && str_contains($result['err'], '1/3 (33%)') && !str_contains($result['err'], '100%'), 'Package preserves failure status and incomplete progress');
    $result = $run(['examples/import-csv.php', 'examples/contacts.csv', '--dry-run'], $project);
    $expect($result['code'] === 0 && !file_exists($project . '/contacts.json'), 'CSV dry run does not save a file');
    $result = $run(['examples/import-csv.php', 'examples/contacts.csv'], $project);
    $expect($result['code'] === 0 && is_file($project . '/contacts.json'), 'CSV import works with bundled PHP');
    $contacts = json_decode((string) file_get_contents($project . '/contacts.json'), true, 512, JSON_THROW_ON_ERROR);
    $expect(count($contacts) === 3 && $contacts[0]['name'] === 'André', 'Bundled CSV import preserves contacts');
    $result = $run(['examples/import-csv.php', 'examples/contacts.csv'], $project);
    $expect($result['code'] === 1, 'Bundled CSV import refuses to overwrite an output');
    file_put_contents($project . '/src/Program.php', '<?php namespace App; final class Program { public static function main(array $arguments): int { echo json_encode($arguments, JSON_THROW_ON_ERROR); return 17; } }');
    $literal = ['André', 'space value', '', 'a"b', "C:\\Program Files\\", '%PATH%', '!value!', 'a&b|c^d'];
    $result = $run([$cli, 'run', '--', ...$literal], $project);
    $expect($result['code'] === 17 && $result['err'] === '', 'Launcher preserves custom application exit codes');
    $expect(json_decode($result['out'], true, 512, JSON_THROW_ON_ERROR) === $literal, 'Launcher preserves literal argument boundaries');
    echo "Packaged console tests passed ({$assertions} assertions).\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($fixture);
}
