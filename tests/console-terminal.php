<?php

declare(strict_types=1);

// Manual checks in a real terminal; never enter a real password here.
$usage = 'Usage: php tests/console-terminal.php <display|secret|interrupt> [package-directory]';
if (($argv[1] ?? '') === '--help') {
    fwrite(STDOUT, $usage . PHP_EOL);
    exit(0);
}
$mode = $argv[1] ?? '';
if (!in_array($mode, ['display', 'secret', 'interrupt'], true) || count($argv) > 3) {
    fwrite(STDERR, $usage . PHP_EOL);
    exit(2);
}
$library = isset($argv[2]) ? rtrim($argv[2], '/\\') . '/runtime/bin/console.php' : dirname(__DIR__) . '/cli/console.php';
if (!is_file($library)) {
    fwrite(STDERR, 'Console library not found: ' . $library . PHP_EOL);
    exit(2);
}
foreach ([STDIN, STDOUT, STDERR] as $stream) {
    if (!function_exists('stream_isatty') || !stream_isatty($stream)) {
        fwrite(STDERR, 'Run this check in a real terminal without redirection.' . PHP_EOL);
        exit(2);
    }
}
require $library;

use AML\Console\Console;
use AML\Console\InputException;

try {
    if ($mode === 'display') {
        Console::line(Console::message('This line should be blue when colors are enabled.', 'Cette ligne doit être bleue lorsque les couleurs sont activées.'), 'blue');
        Console::table(['Contact', 'Status'], [['André', 'OK'], ['東京', 'OK']]);
        $progress = Console::progress(10, 'Progress');
        for ($index = 0; $index < 10; $index++) {
            usleep(100_000);
            $progress->advance();
        }
        $progress->finish();
        Console::info(Console::message('Visually check the color, alignment and progress on a single line. Exit code 0 only means the display ran.', 'Vérifiez visuellement la couleur, l’alignement et la progression sur une seule ligne. Le code 0 indique seulement que l’affichage a été exécuté.'));
        exit(0);
    }
    if ($mode === 'interrupt') {
        Console::info(Console::message('Press Ctrl+C at the hidden prompt. Then check that normal keyboard echo works in your shell.', 'Appuyez sur Ctrl+C à l’invite masquée, puis vérifiez que la frappe normale est visible dans votre terminal.'));
        Console::secret('Ctrl+C');
        Console::error(Console::message('The prompt returned normally; interruption was not verified.', 'L’invite s’est terminée normalement ; l’interruption n’a pas été vérifiée.'));
        exit(1);
    }
    Console::info(Console::message('Type the dummy value console-test, then Enter. The typed characters must stay hidden.', 'Saisissez la valeur de test console-test, puis Entrée. Les caractères frappés doivent rester masqués.'));
    if (Console::secret('Test') !== 'console-test') {
        Console::error(Console::message('The entered value did not match.', 'La valeur saisie ne correspond pas.'));
        exit(1);
    }
    Console::success(Console::message('Exact value received without printing it. Confirm that nothing echoed while typing.', 'Valeur exacte reçue sans la réafficher. Confirmez qu’aucun caractère n’est apparu pendant la frappe.'));
} catch (InputException $error) {
    Console::error($error->getMessage());
    exit(2);
}
