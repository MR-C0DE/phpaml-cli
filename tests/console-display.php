<?php

declare(strict_types=1);

use AML\Console\Console;

require dirname(__DIR__) . '/cli/console.php';

if (($argv[1] ?? '') === '--probe') {
    try {
        switch ($argv[2]) {
            case 'colors':
                Console::line('blue', color: 'blue');
                Console::success('done');
                Console::info('details');
                Console::warning('careful');
                Console::error('failed');
                break;
            case 'table':
                Console::table(['Nom', 'État'], (static function (): Generator {
                    yield ['André', 'OK'];
                    yield ['東京', 'Prêt'];
                    yield ["Cafe\u{0301}", null];
                })());
                break;
            case 'table-cells':
                Console::table(['Texte', 'Valeur'], [["\033[31mrouge\033[0m\r\nligne\tfin", true], ['x', false], ['y', 0], ['z', 1.5]], 12);
                break;
            case 'table-limit':
                Console::table(['Nom très long'], [['Éléphant à Tokyo'], ['東京東京東京東京東京']], 10);
                break;
            case 'table-empty': Console::table(['Nom'], []); break;
            case 'table-invalid': Console::table(['Nom'], [['valid'], ['extra', 'cell']]); break;
            case 'table-object': Console::table(['Nom'], [[new stdClass()]]); break;
            case 'table-header': Console::table([], []); break;
            case 'table-width': Console::table(['Nom'], [], 2); break;
            case 'color-invalid': Console::line('no partial output', color: 'unknown'); break;
            case 'sanitize':
                Console::line("\033[31mred\033[0m\033]0;hidden title\007visible\033]8;;https://example.com\033\\link\033]8;;\033\\\007\r");
                break;
            case 'progress':
                $progress = Console::progress(500, 'Import');
                for ($index = 0; $index < 500; $index++) $progress->advance();
                $progress->finish();
                $progress->finish();
                $progress->abort();
                break;
            case 'progress-partial':
                $progress = Console::progress(3, 'Import');
                $progress->set(1);
                $progress->abort();
                $progress->finish();
                break;
            case 'progress-dropped':
                $progress = Console::progress(3, 'Import');
                $progress->advance();
                unset($progress);
                break;
            case 'progress-zero': Console::progress(0, 'Empty')->finish(); break;
            case 'progress-large':
                $progress = Console::progress(PHP_INT_MAX, 'Large');
                $progress->set(PHP_INT_MAX - 1);
                $progress->abort();
                break;
            case 'progress-narrow':
                $progress = Console::progress(10, 'A very long progress label that must fit');
                $progress->advance();
                $progress->finish();
                break;
            case 'progress-negative': Console::progress(-1); break;
            case 'progress-invalid':
                $progress = Console::progress(3);
                $progress->advance(4);
                break;
            case 'progress-backwards':
                $progress = Console::progress(3);
                $progress->set(2);
                $progress->set(1);
                break;
            case 'progress-closed':
                $progress = Console::progress(3);
                $progress->finish();
                $progress->advance(0);
                break;
        }
    } catch (InvalidArgumentException|LogicException $error) {
        if (isset($progress)) $progress->abort();
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
$probe = static function (string $mode, array $environment = [], bool $withoutMbstring = false): array {
    $out = tmpfile();
    $err = tmpfile();
    $arguments = $withoutMbstring ? ['-d', 'disable_functions=mb_strwidth'] : [];
    $process = proc_open([PHP_BINARY, ...$arguments, __FILE__, '--probe', $mode], [0 => ['pipe', 'r'], 1 => $out, 2 => $err], $pipes, null,
        array_merge(getenv(), ['AML_LANG' => 'en', 'TERM' => 'xterm-256color', 'NO_COLOR' => ''], $environment), ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot start display probe.');
    fclose($pipes[0]);
    $code = proc_close($process);
    rewind($out);
    rewind($err);
    $result = ['code' => $code, 'out' => (string) stream_get_contents($out), 'err' => (string) stream_get_contents($err)];
    fclose($out);
    fclose($err);
    return $result;
};

foreach ([[], ['NO_COLOR' => '1'], ['NO_COLOR' => '0'], ['TERM' => 'dumb'], ['FORCE_COLOR' => '1']] as $environment) {
    $result = $probe('colors', $environment);
    $expect($result['code'] === 0 && $result['out'] === 'blue' . PHP_EOL, 'Redirected data output remains plain');
    $expect($result['err'] === '[OK] done' . PHP_EOL . '[INFO] details' . PHP_EOL . '[WARNING] careful' . PHP_EOL . '[ERROR] failed' . PHP_EOL, 'Status messages are labelled and use stderr without ANSI');
}
$french = $probe('colors', ['AML_LANG' => 'fr']);
$expect(str_contains($french['err'], '[AVERTISSEMENT]') && str_contains($french['err'], '[ERREUR]'), 'Localized status labels');
$result = $probe('table');
$expected = "+-------+------+\n| Nom   | État |\n+-------+------+\n| André | OK   |\n| 東京  | Prêt |\n| Cafe\u{0301}  |      |\n+-------+------+\n";
$expect($result['code'] === 0 && $result['err'] === '', 'Table writes only stdout');
$expect(str_replace("\r\n", "\n", $result['out']) === $expected, 'Table aligns accented, combining and wide text');
$cells = $probe('table-cells');
$expect(str_contains($cells['out'], 'rouge / l...') && str_contains($cells['out'], 'true') && str_contains($cells['out'], 'false') && str_contains($cells['out'], '1.5'), 'Cells normalize multiline text and scalar values');
$expect(!str_contains($cells['out'], "\033") && !str_contains($cells['out'], '[31m'), 'Tables strip control sequences before measuring');
$limited = $probe('table-limit');
$expect(str_contains($limited['out'], '...') && preg_match('//u', $limited['out']) === 1, 'Truncation preserves UTF-8');
foreach (explode(PHP_EOL, trim($limited['out'])) as $line) $expect(mb_strwidth($line, 'UTF-8') <= 14, 'Column width is bounded');
$expect($probe('table-empty')['out'] === '+-----+' . PHP_EOL . '| Nom |' . PHP_EOL . '+-----+' . PHP_EOL, 'Empty tables still show headers');
foreach (['table-invalid', 'table-object', 'table-header', 'table-width', 'color-invalid', 'progress-negative'] as $mode) {
    $result = $probe($mode);
    $expect($result['code'] === 2 && $result['out'] === '' && str_contains($result['err'], '[ERROR]'), 'Invalid display input has no partial stdout: ' . $mode);
}
$expect($probe('sanitize')['out'] === 'redvisiblelink' . PHP_EOL, 'Remove CSI and OSC escape sequences instead of leaving their payloads');
$fallback = $probe('table', [], true);
$expect($fallback['code'] === 0 && $fallback['err'] === '' && str_contains($fallback['out'], 'André'), 'Standalone PHP without mbstring remains usable');
$progress = $probe('progress');
$expect($progress['code'] === 0 && $progress['out'] === '', 'Progress uses stderr');
$expect($progress['err'] === 'Import: 0/500 (0%)' . PHP_EOL . 'Import: 500/500 (100%)' . PHP_EOL, 'Redirected progress emits only start and completion, even after repeated finish');
foreach (['progress-partial', 'progress-dropped'] as $mode) {
    $result = $probe($mode);
    $expect($result['code'] === 0 && str_contains($result['err'], '1/3 (33%) - Interrupted') && !str_contains($result['err'], '100%'), 'Interrupted work never becomes successful: ' . $mode);
    $expect(substr_count($result['err'], PHP_EOL) === 2 && !str_contains($result['err'], "\033"), 'Interrupted redirected progress remains concise');
}
$expect($probe('progress-zero')['code'] === 0, 'Empty work has no division by zero');
$large = $probe('progress-large');
$expect($large['code'] === 0 && str_contains($large['err'], '(99%)') && !str_contains($large['err'], '(100%)'), 'Large totals do not round incomplete work up to 100 percent');
foreach (['progress-invalid', 'progress-backwards', 'progress-closed'] as $mode) {
    $result = $probe($mode);
    $expect($result['code'] === 2 && $result['out'] === '' && str_contains($result['err'], '[ERROR]'), 'Invalid progress state: ' . $mode);
}
$frProgress = $probe('progress-partial', ['AML_LANG' => 'fr']);
$expect(str_contains($frProgress['err'], 'Interrompu'), 'Localized interrupted progress');
echo "Console display tests passed ({$assertions} assertions).\n";
