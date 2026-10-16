<?php

declare(strict_types=1);

$repository = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/phpaml-console-import-' . bin2hex(random_bytes(6));
$installation = $fixture . '/installation';
$project = $fixture . '/contacts André';
mkdir($installation . '/runtime/bin', 0755, true);
foreach (glob($repository . '/cli/*.php') ?: [] as $source) copy($source, $installation . '/runtime/bin/' . basename($source));
copy($repository . '/phpaml.json', $installation . '/phpaml.json');
$cli = $installation . '/runtime/bin/aml.php';
$environment = array_merge(getenv(), ['AML_LANG' => 'en', 'AML_CACHE_HOME' => $fixture . '/cache', 'AML_TMPDIR' => $fixture]);
$assertions = 0;
$expect = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$run = static function (array $arguments, string $cwd, string $language = 'en') use ($environment): array {
    $out = tmpfile();
    $err = tmpfile();
    $process = proc_open([PHP_BINARY, '-d', 'error_reporting=-1', ...$arguments], [0 => ['pipe', 'r'], 1 => $out, 2 => $err], $pipes, $cwd, array_merge($environment, ['AML_LANG' => $language]), ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot run the console import example.');
    fclose($pipes[0]);
    $code = proc_close($process);
    rewind($out);
    rewind($err);
    $result = ['code' => $code, 'out' => (string) stream_get_contents($out), 'err' => (string) stream_get_contents($err)];
    fclose($out);
    fclose($err);
    return $result;
};
$import = static fn (array $arguments, string $language = 'en'): array => $run(['examples/import-csv.php', ...$arguments], $project, $language);
$json = static fn (string $path): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$clean = static function (array $result) use ($expect): void {
    $combined = $result['out'] . $result['err'];
    $expect(!str_contains($combined, 'Stack trace') && !str_contains($combined, 'Fatal error') && !str_contains($combined, 'Deprecated:') && !str_contains($combined, 'Warning:'), 'No PHP internals in diagnostics: ' . $combined);
    $expect(!str_contains($combined, "\033"), 'Redirected output contains no terminal control codes');
};

try {
    $created = $run([$cli, 'create-console', basename($project)], $fixture);
    $expect($created['code'] === 0 && $created['err'] === '', 'Generate a console with the CSV example');
    $sample = $project . '/examples/contacts.csv';
    $before = file_get_contents($sample);
    $result = $import(['--help']);
    $expect($result['code'] === 0 && $result['err'] === '' && str_contains($result['out'], '--dry-run') && str_contains($result['out'], '--output') && str_contains($result['out'], '--delimiter'), 'Help describes the usable example without a required file');
    foreach ([[], ['--unknown'], ['examples/contacts.csv', '--delimiter=|']] as $arguments) {
        $result = $import($arguments);
        $expect($result['code'] === 2 && $result['out'] === '' && $result['err'] !== '', 'Reject invalid command arguments');
        $clean($result);
    }

    $result = $import(['examples/contacts.csv', '--dry-run']);
    $expect($result['code'] === 0 && str_contains($result['err'], 'Dry run complete') && !file_exists($project . '/contacts.json'), 'Dry run validates without creating the default output');
    $expect(preg_match('/\| Saved\s*\| 0\s*\|/', $result['out']) === 1, 'Dry-run summary reports zero saved contacts');
    $expect(str_contains($result['err'], '0/3 (0%)') && str_contains($result['err'], '3/3 (100%)'), 'Progress reports validation of all three contacts');
    $clean($result);
    $result = $import(['examples/contacts.csv', '-o', 'imported contacts.json']);
    $expect($result['code'] === 0 && str_contains($result['err'], '[OK]'), 'Import to a new file succeeds');
    $expect($json($project . '/imported contacts.json') === [
        ['name' => 'André', 'email' => 'andre@example.com'],
        ['name' => 'Martin, Alice', 'email' => 'alice@example.com'],
        ['name' => 'Marie', 'email' => 'marie@example.com'],
    ], 'Save the expected contacts, preserving Unicode and quoted commas');
    $expect(preg_match('/\| Saved\s*\| 3\s*\|/', $result['out']) === 1, 'Successful summary reports three saved contacts');
    $expect(file_get_contents($sample) === $before, 'Import never modifies its CSV source');
    $clean($result);

    file_put_contents($project . '/windows.csv', "\xEF\xBB\xBFemail,name\r\n\r\n alice@example.com ,\"Alice \"\"A\"\"\"\r\nmarie@example.com, Marie \r\n");
    $result = $import(['windows.csv', '-o', 'windows.json']);
    $expect($result['code'] === 0, 'BOM, CRLF, blank lines, reordered columns and escaped quotes are accepted: ' . $result['err']);
    $expect($json($project . '/windows.json') === [['name' => 'Alice "A"', 'email' => 'alice@example.com'], ['name' => 'Marie', 'email' => 'marie@example.com']], 'Reordered fields are saved in the canonical schema');
    file_put_contents($project . '/semicolon.csv', "name;email\n\"Martin; Alice\";alice@example.com\n");
    $result = $import(['semicolon.csv', '--delimiter=;', '-o', 'semicolon.json']);
    $expect($result['code'] === 0 && $json($project . '/semicolon.json')[0]['name'] === 'Martin; Alice', 'A chosen semicolon delimiter supports quoted separators');

    $invalidCases = [
        ['', 'empty'],
        ["name,email\n", 'no contacts'],
        ["name\nAlice\n", 'Line 1:'],
        ["name,email,age\nAlice,alice@example.com,42\n", 'Line 1:'],
        ["name,name\nAlice,Alice\n", 'Line 1:'],
        ["name\x00,email\nAlice,alice@example.com\n", 'Line 1:'],
        ["name,email\nAlice\n", 'Line 2:'],
        ["name,email\nAlice,alice@example.com,extra\n", 'Line 2:'],
        ["name,email\n,alice@example.com\n", 'Line 2:'],
        ["name,email\nAlice,not-an-email\n", 'Line 2:'],
        ["name,email\nAlice,alice@example.com\nALICE,ALICE@example.com\n", 'Line 3:'],
        ["name,email\n\nAlice,invalid\n", 'Line 3:'],
        ["name,email\n\"Alice\n", 'Line 2:'],
        ["name,email\nAli\"ce,alice@example.com\n", 'Line 2:'],
        ["name,email\n\"Alice\"junk,alice@example.com\n", 'Line 2:'],
        ["name,email\nAndr\xE9,andre@example.com\n", 'Line 2:'],
        ["name,email\nAlice\x00,alice@example.com\n", 'Line 2:'],
        ["name,email\n\"Alice\nSmith\",alice@example.com\nBob,invalid\n", 'Line 4:'],
    ];
    foreach ($invalidCases as $index => [$csv, $diagnostic]) {
        file_put_contents($project . '/invalid.csv', $csv);
        $result = $import(['invalid.csv', '-o', 'invalid.json']);
        $expect($result['code'] === 2 && str_contains($result['err'], $diagnostic), "Reject invalid CSV {$index} with its diagnostic: " . json_encode($result));
        $expect(!file_exists($project . '/invalid.json') && !str_contains($result['err'], '[OK]'), 'Invalid data never creates an output or reports success');
        $clean($result);
    }
    file_put_contents($project . '/mixed.csv', "name,email\nAlice,alice@example.com\nBob,invalid\nCarol,carol@example.com\n");
    $result = $import(['mixed.csv', '-o', 'mixed.json']);
    $expect($result['code'] === 2 && !file_exists($project . '/mixed.json'), 'No partial import when a row is invalid');
    $expect(preg_match('/\| Valid\s*\| 2\s*\|/', $result['out']) === 1 && preg_match('/\| Invalid\s*\| 1\s*\|/', $result['out']) === 1 && preg_match('/\| Saved\s*\| 0\s*\|/', $result['out']) === 1, 'Failure summary distinguishes valid, invalid and saved rows');
    $result = $import(['mixed.csv', '--dry-run'], 'fr');
    $expect($result['code'] === 2 && str_contains($result['err'], 'Ligne 3') && str_contains($result['out'], 'Enregistrés'), 'French errors and summary include the source line');
    file_put_contents($project . '/many-errors.csv', "name,email\n" . str_repeat("Alice,invalid\n", 25));
    $result = $import(['many-errors.csv', '--dry-run']);
    $expect($result['code'] === 2 && substr_count($result['err'], '[ERROR] Line ') === 20 && str_contains($result['err'], 'first 20'), 'Limit diagnostics without hiding the total invalid count');
    $expect(preg_match('/\| Invalid\s*\| 25\s*\|/', $result['out']) === 1, 'Count errors beyond the display limit');

    foreach (['missing.csv', 'examples', 'https://example.com/contacts.csv'] as $path) {
        $result = $import([$path]);
        $expect($result['code'] === 2 && !file_exists($project . '/contacts.json'), 'Require an existing local file');
        $clean($result);
    }
    $saved = file_get_contents($project . '/imported contacts.json');
    $result = $import(['examples/contacts.csv', '-o', 'imported contacts.json']);
    $expect($result['code'] === 1 && file_get_contents($project . '/imported contacts.json') === $saved, 'Refuse to overwrite an existing output');
    $result = $import(['examples/contacts.csv', '-o', 'examples/contacts.csv']);
    $expect($result['code'] === 1 && file_get_contents($sample) === $before, 'Refuse to overwrite the input itself');
    foreach (['absent/contacts.json', 'examples'] as $path) {
        $result = $import(['examples/contacts.csv', '-o', $path]);
        $expect($result['code'] === 1 && !str_contains($result['err'], '[OK]'), 'Report an output failure with exit code one');
        $clean($result);
    }
    $expect(!is_dir($project . '/absent'), 'An invalid output does not create parent directories');
    foreach (['', 'php://stdout'] as $path) {
        $result = $import(['examples/contacts.csv', '--output=' . $path]);
        $expect($result['code'] === 2, 'Output must be a nonempty local path');
    }
    $result = $import(['examples/contacts.csv', '--dry-run', '-o', 'absent/ignored.json']);
    $expect($result['code'] === 0 && !is_dir($project . '/absent'), 'Dry run validates data without touching the destination');
    if (PHP_OS_FAMILY !== 'Windows') {
        symlink($project . '/imported contacts.json', $project . '/linked.json');
        $result = $import(['examples/contacts.csv', '-o', 'linked.json']);
        $expect($result['code'] === 1 && is_link($project . '/linked.json') && file_get_contents($project . '/imported contacts.json') === $saved, 'Do not follow or replace an existing output symlink');
    }

    file_put_contents($project . '/large.csv', str_repeat('x', 10 * 1024 * 1024 + 1));
    $result = $import(['large.csv', '--dry-run']);
    $expect($result['code'] === 2 && str_contains($result['err'], '10 MiB'), 'Bound snapshot size');
    file_put_contents($project . '/many.csv', "name,email\n" . str_repeat("Alice,alice@example.com\n", 10_001));
    $result = $import(['many.csv', '--dry-run']);
    $expect($result['code'] === 2 && str_contains($result['err'], '10000 contacts'), 'Bound the number of records');

    $manifest = $json($project . '/phpaml.json');
    $manifest['application']['commands']['import-contacts'] = 'App\\Examples\\ImportCsv';
    file_put_contents($project . '/phpaml.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $result = $run([$cli, 'run', 'import-contacts', 'examples/contacts.csv', '--dry-run'], $project);
    $expect($result['code'] === 0 && str_contains($result['err'], 'Dry run complete'), 'The example class can also be registered as an AML command');
    $result = $run([$cli, 'run', 'import-contacts', 'mixed.csv', '--dry-run'], $project);
    $expect($result['code'] === 2, 'Named command dispatch preserves validation failure');

    $conflict = $fixture . '/existing example';
    mkdir($conflict . '/examples', 0755, true);
    file_put_contents($conflict . '/examples/import-csv.php', 'keep this custom example');
    $result = $run([$cli, 'create-console', '.'], $conflict);
    $expect($result['code'] === 1 && file_get_contents($conflict . '/examples/import-csv.php') === 'keep this custom example' && !file_exists($conflict . '/phpaml.json'), 'New example paths participate in creation conflict checks');
    unlink($installation . '/runtime/bin/console-import-example.php');
    $result = $run([$cli, 'create-console', 'missing-example'], $fixture);
    $expect($result['code'] === 1 && str_contains($result['err'], 'import example is missing') && !file_exists($fixture . '/missing-example'), 'Missing packaged template fails before creating a project');
    echo "Console CSV import tests passed ({$assertions} assertions).\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($fixture);
}
