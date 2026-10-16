<?php

declare(strict_types=1);

use AML\Console\Console;
use AML\Console\Input;
use AML\Console\InputException;

require dirname(__DIR__) . '/cli/console.php';

if (($argv[1] ?? '') === '--probe') {
    try {
        $result = match ($argv[2]) {
            'ask' => Console::ask('Name'),
            'default' => Console::ask('Name', 'André'),
            'confirm' => Console::confirm('Continue'),
            'confirm-yes' => Console::confirm('Continue', true),
            'select' => Console::select('Environment', ['dev' => 'Development', 'prod' => 'Production']),
            'select-default' => Console::select('Environment', [10 => 'Development', 20 => 'Production'], 20),
            'secret' => Console::secret('Password'),
        };
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (InputException $error) {
        Console::error($error->getMessage());
        exit(2);
    }
    exit(0);
}

$assertions = 0;
$expect = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$invalid = static function (callable $callback, string $message, bool $schema = false) use ($expect): void {
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        $expect($schema ? !$error instanceof InputException : $error instanceof InputException, $message . ': wrong error category');
        if (!$schema) $expect(str_contains($error->getMessage(), '--help'), $message . ': missing help hint');
        return;
    }
    $expect(false, $message . ': expected failure');
};
$schema = Input::define('Example')
    ->argument('name', default: 'PHP')
    ->option('count', type: 'int', default: 1, shortcut: 'c', min: -5, max: 10)
    ->option('format', default: 'text', shortcut: 'f')
    ->flag('verbose', shortcut: 'v');
$defaults = $schema->parse([]);
$expect($defaults->argument('name') === 'PHP' && $defaults->option('count') === 1 && $defaults->option('verbose') === false, 'Typed defaults');
$parsed = $schema->parse(['André', '--count=2', '-v', '-f', 'json']);
$expect($parsed->argument('name') === 'André' && $parsed->option('count') === 2 && $parsed->option('verbose') === true && $parsed->option('format') === 'json', 'Mix positional, long, and short options');
$expect($schema->parse(['-c', '-3'])->option('count') === -3, 'Negative option values');
$expect($schema->parse(['-c=0'])->option('count') === 0, 'Zero is an integer, not an error');
$expect($schema->parse(['--format='])->option('format') === '', 'Explicit empty string');
$expect($schema->parse(['--format=--help'])->option('format') === '--help', 'Option-like string via equals');
$expect($schema->parse(['--format=a=b'])->option('format') === 'a=b', 'Preserve equals inside values');
$expect($schema->parse(['--', '--help'])->argument('name') === '--help' && !$schema->parse(['--', '--help'])->helpRequested(), 'Literal delimiter');
$expect($schema->parse([''])->argument('name') === '', 'Empty positional string');
$expect($schema->parse(['-'])->argument('name') === '-', 'Single dash positional');
$expect($schema->parse([])->option('count') === 1, 'Parsing is reusable without previous state');
$expect(Input::define()->argument('number', type: 'int')->parse(['-2'])->argument('number') === -2, 'Typed positional integer');
$expect(Input::define()->option('unset')->parse([])->option('unset') === null, 'Absent optional input');
$required = Input::define()->argument('file', required: true)->option('token', required: true);
$expect($required->parse(['-h'])->helpRequested(), 'Help bypasses required inputs');
$expect($required->parse(['--help'])->helpRequested(), 'Long help');
$expect($required->parse(['file.txt', '--token=abc'])->option('token') === 'abc', 'Required inputs provided');

foreach ([['--unknown'], ['-z'], ['--count'], ['--count', '--verbose'], ['--format', '--help'], ['--count=1.5'], ['--count=01'], ['--count='], ['--count=999999999999999999999'], ['--count=-6'], ['--count=11'], ['--verbose=false'], ['--verbose', '-v'], ['--count=1', '-c=2'], ['one', 'two'], ['-vc']] as $tokens) {
    $invalid(static fn () => $schema->parse($tokens), 'Reject ' . json_encode($tokens));
}
$invalid(static fn () => $required->parse([]), 'Required argument');
$invalid(static fn () => $required->parse(['file.txt']), 'Required option');
$invalid(static fn () => Input::define()->argument('age', type: 'int')->parse(['old']), 'Typed positional validation');
foreach ([
    static fn () => Input::define()->flag('help'),
    static fn () => Input::define()->flag('verbose', shortcut: 'h'),
    static fn () => Input::define()->flag('a')->flag('a'),
    static fn () => Input::define()->flag('a', shortcut: 'v')->option('b', shortcut: 'v'),
    static fn () => Input::define()->argument('a')->argument('a'),
    static fn () => Input::define()->argument('a')->argument('b', required: true),
    static fn () => Input::define()->option('count', type: 'float'),
    static fn () => Input::define()->option('count', type: 'int', default: '1'),
    static fn () => Input::define()->option('count', type: 'int', default: 0, min: 1),
    static fn () => Input::define()->option('count', type: 'int', min: 2, max: 1),
    static fn () => Input::define()->option('word', min: 1),
    static fn () => Input::define()->option('word', required: true, default: 'text'),
    static fn () => Input::define()->argument('bad name'),
    static fn () => $schema->parse([1]),
    static fn () => $defaults->option('missing'),
    static fn () => $defaults->argument('missing'),
    static fn () => Console::select('Choose', []),
    static fn () => Console::select('Choose', ['a' => 'A'], 'b'),
] as $callback) $invalid($callback, 'Invalid schema or API usage', true);

$environment = getenv('AML_LANG');
putenv('AML_LANG=en');
$expect(str_contains($schema->help(), 'Usage: aml run --') && str_contains($schema->help(), '--count <int>') && str_contains($schema->help(), '[default: 1]') && str_contains($schema->help(), '[min: -5]'), 'English help includes schema metadata');
putenv('AML_LANG=fr');
$expect(str_contains($schema->help(), 'Utilisation :') && str_contains($schema->help(), '[défaut : 1]'), 'French help');
try { $schema->parse(['--inconnu']); } catch (InputException $error) {
    $expect(str_contains($error->getMessage(), 'Option inconnue'), 'French diagnostic');
}
$expect(!str_contains(Console::plain("\x1b[31mred\x1b[0m"), "\x1b"), 'No terminal escape bytes in output helpers');
if ($environment === false) putenv('AML_LANG'); else putenv('AML_LANG=' . $environment);

// Real subprocesses verify stdout/stderr separation and EOF without a test-only API.
$probe = static function (string $mode, string $input = ''): array {
    $out = tmpfile();
    $err = tmpfile();
    $process = proc_open([PHP_BINARY, __FILE__, '--probe', $mode], [0 => ['pipe', 'r'], 1 => $out, 2 => $err], $pipes, null, array_merge(getenv(), ['AML_LANG' => 'en']), ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot launch console probe.');
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $code = proc_close($process);
    rewind($out);
    rewind($err);
    $result = ['code' => $code, 'out' => stream_get_contents($out), 'err' => stream_get_contents($err)];
    fclose($out);
    fclose($err);
    return $result;
};
foreach ([
    ['ask', "André\n", 'André'],
    ['ask', "\nAlice\r\n", 'Alice'],
    ['default', "\n", 'André'],
    ['default', '', 'André'],
    ['confirm', '', false],
    ['confirm', "\n", false],
    ['confirm', "maybe\noui\n", true],
    ['confirm-yes', "non\n", false],
    ['confirm-yes', '', true],
    ['select', "2\n", 'prod'],
    ['select', "0\n9\n1\n", 'dev'],
    ['select-default', "\n", 20],
    ['select-default', '', 20],
    ['select-default', "1\n", 10],
] as [$mode, $input, $expected]) {
    $result = $probe($mode, $input);
    $expect($result['code'] === 0 && json_decode($result['out'], true) === $expected, $mode . ': ' . json_encode($result));
    $expect($result['err'] !== '', 'Prompts use stderr');
}
foreach (['ask', 'select', 'secret'] as $mode) {
    $result = $probe($mode);
    $expect($result['code'] === 2 && $result['out'] === '' && $result['err'] !== '', $mode . ' fails safely without interactive input');
}
$result = $probe('secret', "never-print-this\n");
$expect(!str_contains($result['out'] . $result['err'], 'never-print-this'), 'Piped secrets must never be echoed');

echo "Console input tests passed ({$assertions} assertions).\n";
