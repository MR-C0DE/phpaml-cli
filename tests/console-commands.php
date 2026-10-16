<?php

declare(strict_types=1);

$repository = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/phpaml-commands-' . bin2hex(random_bytes(6));
$installation = $fixture . '/installation';
$project = $fixture . '/console app';
mkdir($installation . '/runtime/bin', 0755, true);
foreach (glob($repository . '/cli/*.php') ?: [] as $source) copy($source, $installation . '/runtime/bin/' . basename($source));
copy($repository . '/phpaml.json', $installation . '/phpaml.json');
$cli = $installation . '/runtime/bin/aml.php';
$environment = array_merge(getenv(), ['AML_LANG' => 'fr', 'AML_CACHE_HOME' => $fixture . '/cache', 'TMPDIR' => $fixture]);
$assertions = 0;
$expect = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$runPhp = static function (array $arguments, string $cwd, string $input = '', string $language = 'fr') use ($environment): array {
    $out = tmpfile();
    $err = tmpfile();
    $process = proc_open([PHP_BINARY, ...$arguments], [0 => ['pipe', 'r'], 1 => $out, 2 => $err], $pipes, $cwd, array_merge($environment, ['AML_LANG' => $language]), ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Unable to start PHP.');
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
$run = static fn (array $arguments, string $input = '', string $language = 'fr'): array => $runPhp([$cli, ...$arguments], $project, $input, $language);
$success = static function (array $result, string $message) use ($expect): void {
    $expect($result['code'] === 0 && $result['err'] === '', $message . ': ' . json_encode($result));
};
$failure = static function (array $result, int $code, string $message) use ($expect): void {
    $expect($result['code'] === $code && $result['out'] === '' && $result['err'] !== '', $message . ': ' . json_encode($result));
    $expect(!str_contains($result['err'], 'Fatal error') && !str_contains($result['err'], 'Stack trace') && !str_contains($result['err'], 'Warning'), $message . ': internal diagnostic');
};
$manifestPath = $project . '/phpaml.json';
$setManifest = static fn (array $manifest): int|false => file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
$commandPath = $project . '/src/Commands/ImportUsers.php';
$writeCommand = static function (string $body, string $signature = 'public static function main(array $arguments): int') use ($commandPath): void {
    file_put_contents($commandPath, "<?php\ndeclare(strict_types=1);\nnamespace App\\Commands;\nfinal class ImportUsers { {$signature} { {$body} } }\n");
};

try {
    $success($runPhp([$cli, 'create-console', 'console app'], $fixture), 'Create a console project');
    $programBefore = file_get_contents($project . '/src/Program.php');
    $bootstrapBefore = file_get_contents($project . '/bootstrap.php');
    $libraryBefore = file_get_contents($project . '/lib/console.php');
    $oldInvocation = $run(['run', 'André']);
    $success($oldInvocation, 'Preserve bare arguments before registering commands');
    $expect(str_contains($oldInvocation['out'], 'Bonjour, André !'), 'Old invocation reaches Program');
    $list = $run(['run', 'list']);
    $success($list, 'List an empty command registry');
    $expect(str_contains($list['out'], 'make:command') && !str_contains($list['out'], 'Bonjour'), 'Empty list guides command creation');
    $success($run(['make:command', '--help']), 'Generator help');
    $failure($run(['make:command']), 2, 'Missing class name');
    $failure($run(['make:command', 'Ignored', '--unknown']), 2, 'Reject extra generator arguments');

    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['custom'] = ['preserve' => 'André', 'empty' => new stdClass()];
    $setManifest($manifest);
    $success($run(['make:command', 'ImportUsers']), 'Generate and register a command');
    $expect(is_file($commandPath), 'Command class exists under src/Commands');
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $expect($manifest['application']['commands']['import-users']['class'] === 'App\\Commands\\ImportUsers', 'CamelCase maps to kebab-case');
    $document = json_decode((string) file_get_contents($manifestPath));
    $expect($document->custom->preserve === 'André' && $document->custom->empty instanceof stdClass, 'Preserve unrelated manifest fields and object types');
    $expect(file_get_contents($project . '/src/Program.php') === $programBefore && file_get_contents($project . '/bootstrap.php') === $bootstrapBefore && file_get_contents($project . '/lib/console.php') === $libraryBefore, 'Generation preserves the program, bootstrap and existing library');
    $success($run(['run', 'import-users']), 'Execute the generated command');
    $help = $run(['run', 'import-users', '--help']);
    $success($help, 'Command-specific help');
    $expect(str_contains($help['out'], 'aml run import-users [options]') && !str_contains($help['out'], 'ajoutez votre logique'), 'Help uses the command name and does not execute its action');
    $success($run(['run', 'import-users', '-h']), 'Short command help');
    $success($runPhp(['-r', 'require "bootstrap.php"; exit(\App\Commands\ImportUsers::main(["--help"]));'], $project), 'Command can be tested directly through the standalone bootstrap');

    $writeCommand(<<<'PHP'
        $input = \AML\Console\Input::define('Importer des utilisateurs.', usage: 'aml run import-users')
            ->argument('file', required: true)
            ->option('limit', type: 'int', default: 100, min: 1)
            ->parse($arguments);
        if ($input->helpRequested()) { \AML\Console\Console::line($input->help()); return 0; }
        echo json_encode([$input->argument('file'), $input->option('limit')]);
        return 0;
PHP);
    $import = $run(['run', 'import-users', 'clients.csv', '--limit=100']);
    $success($import, 'Parse a command-specific positional argument and option');
    $expect(json_decode($import['out'], true) === ['clients.csv', 100], 'Dispatch removes only the command name');
    $success($run(['run', 'import-users', '--help']), 'Help does not require command arguments');
    $failure($run(['run', 'import-users']), 2, 'Missing required command argument');
    $failure($run(['run', 'import-users', 'clients.csv', '--limit=0']), 2, 'Invalid command option');
    $failure($run(['run', 'improt-users']), 2, 'Unknown command does not silently run Program');
    $unknown = $run(['run', 'improt-users']);
    $expect(str_contains($unknown['err'], 'aml run list') && str_contains($unknown['err'], 'aml run --'), 'Unknown command offers both recovery paths');

    $success($run(['run']), 'Program remains the default without arguments');
    $expect(str_contains($run(['run'])['out'], 'Bonjour, PHP !'), 'Default Program behavior is preserved');
    foreach (['André', 'import-users', 'list'] as $name) {
        $program = $run(['run', '--', $name]);
        $success($program, 'Explicit Program dispatch');
        $expect(str_contains($program['out'], "Bonjour, {$name} !"), 'Delimiter avoids command/name collisions');
    }
    $expect(str_contains($run(['run', '--', '--help'])['out'], '--repeat'), 'Explicit delimiter preserves Program help');
    foreach ([['list'], ['list', '--help'], ['--help'], ['-h']] as $arguments) {
        $list = $run(['run', ...$arguments]);
        $success($list, 'Catalog and catalog help');
        $expect(str_contains($list['out'], 'import-users') && str_contains($list['out'], 'Program::main()'), 'Catalog documents commands and the default entry');
    }
    $failure($run(['run', 'list', '--unknown']), 2, 'Catalog rejects unsupported arguments');

    $beforeDuplicate = file_get_contents($manifestPath);
    $beforeClass = file_get_contents($commandPath);
    $failure($run(['make:command', 'ImportUsers']), 1, 'Duplicate name');
    $failure($run(['make:command', 'importusers']), 1, 'Case-insensitive PHP class collision');
    $expect(file_get_contents($manifestPath) === $beforeDuplicate && file_get_contents($commandPath) === $beforeClass, 'Duplicates do not overwrite manifest or command');
    foreach (['../Escape', 'Admin/../Escape', '/Absolute', 'Admin//Empty', 'Bad.Name', '123Class', 'Echo', 'Con', 'pRn', 'AUX', 'Admin/Nul', 'COM1/Import', 'Admin\\lpt9', 'com-1'] as $invalidName) {
        $failure($run(['make:command', $invalidName]), 2, 'Invalid or reserved PHP class name');
        $expect(file_get_contents($manifestPath) === $beforeDuplicate, 'Invalid names do not modify the manifest');
    }
    $failure($run(['make:command', 'List']), 1, 'Reserved catalog name');
    mkdir($project . '/src/Commands/Blocked.php');
    $failure($run(['make:command', 'Blocked']), 1, 'Existing directory is protected');
    $expect(file_get_contents($manifestPath) === $beforeDuplicate, 'Path conflict does not register a command');
    $success($run(['make:command', 'Admin/HTTPImport']), 'Generate a nested class with an acronym');
    $expect(is_file($project . '/src/Commands/Admin/HTTPImport.php'), 'Nested command path');
    $success($run(['run', 'admin:http-import', '--help']), 'Run a nested command');
    $success($run(['make:command', 'Reports\\Daily']), 'Generate a command with Windows namespace separators');
    $success($run(['run', 'reports:daily', '--help']), 'Run the backslash-separated command on every platform');
    $success($run(['make:command', 'ConsoleTools/Com10']), 'Similar nonreserved names remain available');
    $success($run(['make:command', 'export-users']), 'Accept kebab-case class input');
    $expect(is_file($project . '/src/Commands/ExportUsers.php'), 'Kebab-case maps to PascalCase');
    $success($run(['make:command', 'HealthCheck'], '', 'en'), 'Generate an English command');
    $english = $run(['run', 'health-check', '--help'], '', 'en');
    $expect(str_contains($english['out'], 'Usage: aml run health-check') && str_contains($english['out'], 'The health-check command.'), 'Localized generation and help');
    foreach (['Console', 'Input'] as $helperName) {
        $success($run(['make:command', $helperName]), 'Command classes can share a helper name');
        $success($run(['run', strtolower($helperName), '--help']), 'Generated helpers avoid class-name collisions');
    }

    // Listing must not load command code or the default entry point.
    file_put_contents($commandPath, '<?php throw new RuntimeException("must not load");');
    $success($run(['run', 'list']), 'Catalog reads metadata without loading commands');
    $success($run(['run', 'admin:http-import']), 'An unrelated broken command does not block another command');
    $failure($run(['run', 'import-users']), 1, 'Report a command loading exception');
    file_put_contents($commandPath, '<?php invalid syntax !!!');
    $failure($run(['run', 'import-users']), 1, 'Report a command syntax error without an internal trace');
    foreach (['public static function main(): int', 'public static function main(string $arguments): int', 'public function main(array $arguments): int'] as $signature) {
        $writeCommand('return 0;', $signature);
        $failure($run(['run', 'import-users']), 1, 'Reject incompatible command signatures');
    }
    $writeCommand('return 17;');
    $expect($run(['run', 'import-users'])['code'] === 17, 'Preserve command exit code');
    $writeCommand('echo trim((string) fgets(STDIN)); return 0;');
    $expect($run(['run', 'import-users'], "piped input\n")['out'] === 'piped input', 'Commands inherit stdin');
    $writeCommand('echo json_encode($arguments); return 0;');
    $literal = ['André', 'space value', '', '$HOME', '$(echo literal)', 'a"b', '%PATH%', '!value!', 'a&b|c^d', "C:\\Program Files\\", "path\\\"quoted", '--help', '--', '--flag'];
    $expect(json_decode($run(['run', 'import-users', ...$literal])['out'], true) === $literal, 'Forward literal arguments without interpreting command options');

    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['application']['commands']['alias'] = 'App\\Commands\\ImportUsers';
    $setManifest($manifest);
    $success($run(['run', 'alias']), 'Class-string registration supports explicit aliases');
    $manifestBeforeInvalid = file_get_contents($manifestPath);
    foreach ([null, 'wrong', ['list' => 'App\\Commands\\ImportUsers'], ['Bad Name' => 'App\\Commands\\ImportUsers'], ['bad' => []], ['bad' => ['class' => '../Class']], ['bad' => ['class' => 'App\\Commands\\ImportUsers', 'description' => []]]] as $invalidRegistry) {
        $broken = $manifest;
        $broken['application']['commands'] = $invalidRegistry;
        $setManifest($broken);
        $failure($run(['run', 'list']), 1, 'Validate command registry');
        $brokenBefore = file_get_contents($manifestPath);
        $failure($run(['make:command', 'NotCreated']), 1, 'Do not extend a malformed registry');
        $expect(file_get_contents($manifestPath) === $brokenBefore && !file_exists($project . '/src/Commands/NotCreated.php'), 'Malformed registry remains untouched');
    }
    file_put_contents($manifestPath, $manifestBeforeInvalid);
    unlink($commandPath);
    $failure($run(['run', 'import-users']), 1, 'Missing registered class');

    // Older console projects: add the library only when missing, preserve custom bootstraps.
    foreach ([false, true] as $withBootstrap) {
        $legacy = $fixture . '/legacy-' . (int) $withBootstrap;
        mkdir($legacy . '/src', 0755, true);
        mkdir($legacy . '/tests');
        file_put_contents($legacy . '/phpaml.json', '{"name":"legacy","application":{"type":"console","entry":"App\\\\Program"}}');
        file_put_contents($legacy . '/src/Program.php', '<?php namespace App; final class Program { public static function main(array $arguments): int { echo "legacy"; return 0; } }');
        file_put_contents($legacy . '/tests/run.php', '<?php exit(\App\Commands\Check::main(["--help"]));');
        if ($withBootstrap) file_put_contents($legacy . '/bootstrap.php', '<?php spl_autoload_register(static function ($class) { $path = __DIR__ . "/src/" . str_replace("\\\\", "/", substr($class, 4)) . ".php"; if (is_file($path)) require_once $path; });');
        $legacyBootstrap = $withBootstrap ? file_get_contents($legacy . '/bootstrap.php') : null;
        $success($runPhp([$cli, 'make:command', 'Check'], $legacy), 'Adopt commands in an old console project');
        $expect(is_file($legacy . '/lib/console.php'), 'Install missing standalone helpers');
        $expect($withBootstrap ? file_get_contents($legacy . '/bootstrap.php') === $legacyBootstrap : !file_exists($legacy . '/bootstrap.php'), 'Preserve legacy bootstrap ownership');
        $success($runPhp([$cli, 'run', 'check', '--help'], $legacy), 'Run a command through the legacy bootstrap');
        $success($runPhp([$cli, 'test'], $legacy), 'Test commands through the legacy bootstrap');
        $expect($runPhp([$cli, 'run'], $legacy)['out'] === 'legacy', 'Legacy default remains intact');
    }

    // Fail after creating the command file: roll back only files from this attempt.
    $rollback = $fixture . '/rollback';
    mkdir($rollback);
    file_put_contents($rollback . '/phpaml.json', '{"application":{"type":"console"}}');
    file_put_contents($rollback . '/lib', 'keep this file');
    $before = file_get_contents($rollback . '/phpaml.json');
    $failure($runPhp([$cli, 'make:command', 'Rollback'], $rollback), 1, 'Rollback partial generation');
    $expect(!file_exists($rollback . '/src/Commands/Rollback.php') && file_get_contents($rollback . '/phpaml.json') === $before && file_get_contents($rollback . '/lib') === 'keep this file', 'Rollback preserves existing files and manifest');

    $web = $fixture . '/web';
    mkdir($web . '/public', 0755, true);
    file_put_contents($web . '/public/index.php', '<?php');
    file_put_contents($web . '/phpaml.json', '{"scripts":{"sample":"echo web-script"}}');
    $failure($runPhp([$cli, 'make:command', 'WebCommand'], $web), 1, 'Reject Web projects');
    $expect(!file_exists($web . '/src/Commands/WebCommand.php'), 'No Web project mutation');
    $webRun = $runPhp([$cli, 'run', 'sample'], $web);
    $success($webRun, 'Web scripts retain their existing dispatch');
    $expect(str_contains($webRun['out'], 'web-script'), 'Web script executes normally');

    echo "Console command tests passed ({$assertions} assertions).\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($fixture);
}
