<?php

declare(strict_types=1);

$repository = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/phpaml-console-' . bin2hex(random_bytes(6));
$installation = $fixture . '/installation AML';
$project = $fixture . '/console app André';
$assertions = 0;
foreach (['runtime/bin', 'runtime/cache', 'runtime/tmp', 'runtime/composer'] as $directory) {
    mkdir($installation . '/' . $directory, 0755, true);
}
foreach (glob($repository . '/cli/*.php') ?: [] as $source) {
    copy($source, $installation . '/runtime/bin/' . basename($source));
}
copy($repository . '/phpaml.json', $installation . '/phpaml.json');
touch($installation . '/runtime/composer/composer.phar');
$cli = $installation . '/runtime/bin/aml.php';
$environment = array_merge(getenv(), [
    'AML_LANG' => 'fr',
    'AML_CACHE_HOME' => $installation . '/runtime/cache',
    'TMPDIR' => $installation . '/runtime/tmp',
]);

$expect = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

/** @return array{code: int, out: string, err: string} */
$runPhp = static function (array $arguments, string $cwd, string $input = '') use ($fixture, $environment): array {
    $out = tempnam($fixture, 'out-');
    $err = tempnam($fixture, 'err-');
    $process = proc_open(
        [PHP_BINARY, ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']],
        $pipes,
        $cwd,
        $environment,
        ['bypass_shell' => true],
    );
    if (!is_resource($process)) throw new RuntimeException('Unable to launch PHP.');
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    return ['code' => proc_close($process), 'out' => (string) file_get_contents($out), 'err' => (string) file_get_contents($err)];
};
$run = static fn (array $arguments, string $input = ''): array => $runPhp([$cli, ...$arguments], $project, $input);
$succeeded = static function (array $result, string $label) use ($expect): void {
    $expect($result['code'] === 0 && $result['err'] === '', $label . ': ' . json_encode($result));
};
$program = static function (string $body, string $signature = 'public static function main(array $arguments): int') use ($project): void {
    file_put_contents($project . '/src/Program.php', "<?php\ndeclare(strict_types=1);\nnamespace App;\nfinal class Program { {$signature} { {$body} } }\n");
};

try {
    $succeeded($runPhp([$cli, 'create-console', basename($project)], $fixture), 'Create console project in a path with spaces and Unicode');
    foreach (['phpaml.json', 'composer.json', 'bootstrap.php', 'lib/console.php', 'src/Program.php', 'src/Examples/ImportCsv.php', 'tests/run.php', 'examples/display.php', 'examples/import-csv.php', 'examples/contacts.csv', '.gitignore'] as $file) {
        $expect(is_file($project . '/' . $file), "Missing generated file: {$file}");
    }
    $expect(!is_dir($project . '/public') && !is_dir($project . '/runtime'), 'Console creation must remain independent of the Web framework.');
    $succeeded($run(['run']), 'Run the generated program');
    $hello = $run(['run', '--', 'André']);
    $succeeded($hello, 'Run with Unicode');
    $expect(str_contains($hello['out'], 'Bonjour, André !'), 'Unicode argument must reach main().');
    $repeat = $run(['run', '--', 'André', '-r', '2']);
    $succeeded($repeat, 'Run with a typed option');
    $expect(substr_count($repeat['out'], 'Bonjour, André !') === 2, 'Generated program uses the parsed repeat count.');
    $help = $run(['run', '--', '--help']);
    $succeeded($help, 'Show generated help');
    $expect(str_contains($help['out'], '--repeat <int>') && str_contains($help['out'], 'Utilisation :') && !str_contains($help['out'], 'Bonjour,'), 'Show localized program help instead of running it.');
    $interactive = $run(['run', '--', '-i'], "Marie\n");
    $expect($interactive['code'] === 0 && trim($interactive['out']) === 'Bonjour, Marie !' && str_contains($interactive['err'], 'Votre nom'), 'Prompts stay on stderr and answers reach the program.');
    foreach ([['--repeat=0'], ['--repeat=many'], ['--unknown']] as $arguments) {
        $invalid = $run(['run', '--', ...$arguments]);
        $expect($invalid['code'] === 2 && $invalid['out'] === '' && str_contains($invalid['err'], '--help'), 'Invalid program input returns 2 with an actionable stderr diagnostic.');
        $expect(!str_contains($invalid['err'], 'Stack trace') && !str_contains($invalid['err'], 'Fatal error'), 'Input errors do not expose stack traces.');
    }
    $succeeded($run(['test']), 'Run the generated tests');
    $succeeded($runPhp(['tests/run.php'], $project), 'Run generated standalone tests without Composer');
    $display = $runPhp(['examples/display.php'], $project);
    $expect($display['code'] === 0 && str_contains($display['out'], 'clients.csv') && str_contains($display['err'], '[OK]') && str_contains($display['err'], '3/3 (100%)'), 'Run the generated terminal display example');
    $expect(!str_contains($display['out'] . $display['err'], "\033"), 'Redirected generated output contains no terminal sequences');
    $succeeded($runPhp(['examples/display.php', '--help'], $project), 'Display example help');
    $succeeded($runPhp(['examples/import-csv.php', '--help'], $project), 'CSV import example help');
    $display = $runPhp(['examples/display.php', '--fail'], $project);
    $expect($display['code'] === 2 && str_contains($display['err'], '1/3 (33%)') && !str_contains($display['err'], '100%') && !str_contains($display['err'], '[OK]'), 'Generated failure example preserves incomplete progress');
    $display = $runPhp(['examples/display.php', '--unknown'], $project);
    $expect($display['code'] === 2 && $display['out'] === '' && str_contains($display['err'], '--help') && !str_contains($display['err'], 'Stack trace'), 'Standalone example handles invalid arguments clearly');

    $succeeded($run(['make:class', 'Domain/User']), 'Generate a nested application class');
    $program('echo get_class(new \\App\\Domain\\User()) . PHP_EOL; return 0;');
    $succeeded($run(['run']), 'Load a generated class in aml run');
    $succeeded($run(['test']), 'Load a generated class in aml test');
    $succeeded($runPhp(['tests/run.php'], $project), 'Load a generated class in standalone PHP tests');

    // Exercise the Composer entry point without fetching any third-party package.
    mkdir($project . '/vendor');
    file_put_contents($project . '/vendor/autoload.php', '<?php namespace ConsoleFixture; final class Dependency { public static function value(): string { return "vendor-loaded"; } }');
    $program('echo \\ConsoleFixture\\Dependency::value() . PHP_EOL; return 0;');
    $dependency = $run(['run']);
    $succeeded($dependency, 'Load the default Composer autoloader');
    $expect(str_contains($dependency['out'], 'vendor-loaded'), 'The dependency must be available.');
    $succeeded($run(['test']), 'Load Composer dependencies in aml test');
    $succeeded($runPhp(['tests/run.php'], $project), 'Load Composer dependencies in standalone PHP tests');

    $composer = json_decode((string) file_get_contents($project . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    $composer['config']['vendor-dir'] = 'runtime';
    file_put_contents($project . '/composer.json', json_encode($composer, JSON_THROW_ON_ERROR));
    mkdir($project . '/runtime');
    file_put_contents($project . '/runtime/autoload.php', '<?php namespace ConsoleFixture; final class Dependency { public static function value(): string { return "runtime-loaded"; } }');
    $dependency = $run(['run']);
    $succeeded($dependency, 'Load a configured Composer vendor directory');
    $expect(str_contains($dependency['out'], 'runtime-loaded'), 'vendor-dir must take precedence over the default vendor directory.');
    $succeeded($run(['test']), 'Use configured vendor-dir in aml test');
    $succeeded($runPhp(['tests/run.php'], $project), 'Use configured vendor-dir in standalone PHP tests');

    $absoluteVendor = $fixture . '/shared dependencies André';
    mkdir($absoluteVendor);
    file_put_contents($absoluteVendor . '/autoload.php', '<?php namespace ConsoleFixture; final class Dependency { public static function value(): string { return "absolute-loaded"; } }');
    $composer['config']['vendor-dir'] = str_replace('/', DIRECTORY_SEPARATOR, $absoluteVendor);
    file_put_contents($project . '/composer.json', json_encode($composer, JSON_THROW_ON_ERROR));
    $dependency = $run(['run']);
    $succeeded($dependency, 'Load an absolute vendor-dir using native path separators');
    $expect(str_contains($dependency['out'], 'absolute-loaded'), 'Absolute vendor-dir must not be prefixed with the project directory');
    $succeeded($runPhp(['tests/run.php'], $project), 'Absolute vendor-dir works in standalone tests');
    $composer['config']['vendor-dir'] = 'runtime';
    file_put_contents($project . '/composer.json', json_encode($composer, JSON_THROW_ON_ERROR));

    // Older generated applications have neither bootstrap.php nor a test autoloader.
    unlink($project . '/bootstrap.php');
    unlink($project . '/lib/console.php');
    file_put_contents($project . '/tests/run.php', "<?php\nrequire dirname(__DIR__) . '/src/Program.php';\nexit(\\App\\Program::main([]));\n");
    $program('new \\App\\Domain\\User(); echo \\ConsoleFixture\\Dependency::value(); return 0;');
    $succeeded($run(['run']), 'Load classes and dependencies in an existing console project');
    $succeeded($run(['test']), 'Bootstrap tests in an existing console project');
    $expect(!is_file($project . '/bootstrap.php'), 'Running an old project must not rewrite its files.');

    $program('echo json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); return 0;');
    $arguments = ['space value', "O'Reilly", 'a"b', '$HOME', '$(echo literal)', '%PATH%', '!value!', 'a&b|c^d', "C:\\Program Files\\", "path\\\"quoted", '--flag', '', 'André'];
    $result = $run(['run', '--', ...$arguments]);
    $succeeded($result, 'Preserve argument boundaries');
    $expect(json_decode($result['out'], true) === $arguments, 'Arguments must remain literal and preserve empty values.');
    $program('echo trim((string) fgets(STDIN)); return 0;');
    $result = $run(['run'], "input from pipe\n");
    $succeeded($result, 'Read standard input');
    $expect($result['out'] === 'input from pipe', 'The child process must inherit stdin.');
    $result = $run(['run'], "Windows input\r\n");
    $succeeded($result, 'Read CRLF standard input');
    $expect($result['out'] === 'Windows input', 'Windows line endings must not become part of the answer.');
    $program('return 17;');
    $expect($run(['run'])['code'] === 17, 'Preserve the application exit code.');
    $program('throw new \\RuntimeException("controlled failure");');
    $result = $run(['run']);
    $expect($result['code'] === 1 && str_contains($result['err'], 'RuntimeException: controlled failure'), 'Report application exceptions on stderr.');
    $expect($result['out'] === '', 'Exception diagnostics must not contaminate stdout.');

    foreach (['public static function main(): int', 'public static function main(string $arguments): int'] as $signature) {
        $program('return 0;', $signature);
        $result = $run(['run']);
        $expect($result['code'] === 1 && str_contains($result['err'], 'doit respecter main(array $arguments): int'), 'Reject an invalid main signature with an actionable message.');
        $expect($result['out'] === '' && !str_contains($result['err'], 'Fatal error') && !str_contains($result['err'], 'Warning'), 'Invalid signatures must not trigger internal PHP errors.');
    }
    $program('return 0;');
    foreach ([[], ['--production']] as $flags) {
        $result = $run(['doctor', '--offline', '--json', ...$flags]);
        $succeeded($result, 'Diagnose a console project');
        $report = json_decode($result['out'], true, 512, JSON_THROW_ON_ERROR);
        $expect($report['healthy'] === true && $report['warnings'] === 0, 'A console project does not need Web environment, storage or production settings.');
        $checks = array_column($report['checks'], null, 'name');
        $expect(($checks['Projet']['status'] ?? null) === 'ok', 'Doctor must recognize console projects.');
        foreach (['Port de développement', 'HTTPS', 'Moteur du projet'] as $name) {
            $expect(!isset($checks[$name]), "Do not diagnose Web requirements for a console project: {$name}");
        }
    }
    $expect($run(['doctor', '--offline', '--port', '70000', '--json'])['code'] === 1, 'An explicitly requested port must still be validated.');

    $before = file_get_contents($project . '/src/Program.php');
    $expect($run(['create-console', '.'])['code'] === 1, 'Refuse to overwrite an existing application.');
    $expect(file_get_contents($project . '/src/Program.php') === $before, 'Preserve existing source on creation conflicts.');

    $absoluteProject = $fixture . '/absolute console';
    $succeeded($runPhp([$cli, 'create-console', str_replace('/', DIRECTORY_SEPARATOR, $absoluteProject)], $fixture), 'Create using an absolute native path');
    $movedProject = $fixture . '/moved console André';
    $expect(rename($absoluteProject, $movedProject), 'Move the generated project');
    $succeeded($runPhp([$cli, 'run'], $movedProject), 'Run a relocated console project');
    $succeeded($runPhp(['tests/run.php'], $movedProject), 'Standalone bootstrap remains relocatable');
    echo "Console regression tests passed ({$assertions} assertions).\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($fixture);
}
