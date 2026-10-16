<?php

declare(strict_types=1);

namespace AML\Console;

/** Invalid user input; aml run reports it on stderr with exit code 2. */
final class InputException extends \InvalidArgumentException {}

final class Console
{
    /** @internal */
    public static function message(string $en, string $fr): string
    {
        return strtolower((string) getenv('AML_LANG')) === 'fr' ? $fr : $en;
    }

    /** Strip terminal control sequences from application text. @internal */
    public static function plain(string $text): string
    {
        $text = (string) preg_replace('/\x1B\][^\x07\x1B]*(?:\x07|\x1B\\\\)|\x1B\[[0-?]*[ -\/]*[@-~]/', '', $text);
        return (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text);
    }

    public static function line(string $text = '', ?string $color = null): void
    {
        Terminal::write(STDOUT, $text, $color);
    }

    public static function error(string $text): void
    {
        Terminal::write(STDERR, '[' . self::message('ERROR', 'ERREUR') . '] ' . $text, 'red');
    }

    public static function success(string $text): void
    {
        Terminal::write(STDERR, '[OK] ' . $text, 'green');
    }

    public static function warning(string $text): void
    {
        Terminal::write(STDERR, '[' . self::message('WARNING', 'AVERTISSEMENT') . '] ' . $text, 'yellow');
    }

    public static function info(string $text): void
    {
        Terminal::write(STDERR, '[INFO] ' . $text, 'cyan');
    }

    /** @param list<scalar|null> $headers @param iterable<list<scalar|null>> $rows */
    public static function table(array $headers, iterable $rows, int $maxColumnWidth = 40): void
    {
        if ($headers === [] || !array_is_list($headers) || $maxColumnWidth < 4) {
            throw new \InvalidArgumentException('A table needs a nonempty header list and maxColumnWidth >= 4.');
        }
        $normalize = static function (array $row) use ($headers, $maxColumnWidth): array {
            if (!array_is_list($row) || count($row) !== count($headers)) {
                throw new \InvalidArgumentException('Every table row must be a list with one value per header.');
            }
            return array_map(static function (mixed $value) use ($maxColumnWidth): string {
                if ($value !== null && !is_scalar($value)) throw new \InvalidArgumentException('Table cells must be scalar values or null.');
                $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                return Terminal::truncate(Terminal::singleLine($text), $maxColumnWidth);
            }, $row);
        };
        $normalized = [$normalize($headers)];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new \InvalidArgumentException('Table rows must be arrays.');
            $normalized[] = $normalize($row);
        }
        // Validate all rows before writing any part of the table.
        $widths = array_fill(0, count($headers), 0);
        foreach ($normalized as $row) {
            foreach ($row as $index => $cell) $widths[$index] = max($widths[$index], Terminal::width($cell));
        }
        $border = '+' . implode('+', array_map(static fn (int $width): string => str_repeat('-', $width + 2), $widths)) . '+';
        self::line($border);
        foreach ($normalized as $index => $row) {
            $cells = [];
            foreach ($row as $column => $cell) $cells[] = ' ' . $cell . str_repeat(' ', $widths[$column] - Terminal::width($cell)) . ' ';
            self::line('|' . implode('|', $cells) . '|', $index === 0 ? 'bold' : null);
            if ($index === 0 || $index === count($normalized) - 1) self::line($border);
        }
    }

    public static function progress(int $total, string $label = ''): ProgressBar
    {
        return new ProgressBar($total, $label);
    }

    public static function ask(string $question, ?string $default = null): string
    {
        while (true) {
            $answer = self::read($question . ($default !== null ? " [{$default}]" : ''));
            if ($answer === null) return $default ?? throw self::closed();
            if ($answer !== '') return $answer;
            if ($default !== null) return $default;
            self::error(self::message('Please enter a value.', 'Veuillez saisir une valeur.'));
        }
    }

    public static function confirm(string $question, bool $default = false): bool
    {
        $hint = self::message($default ? 'Y/n' : 'y/N', $default ? 'O/n' : 'o/N');
        while (true) {
            $answer = self::read("{$question} [{$hint}]");
            if ($answer === null || $answer === '') return $default;
            if (in_array(strtolower($answer), ['y', 'yes', 'o', 'oui'], true)) return true;
            if (in_array(strtolower($answer), ['n', 'no', 'non'], true)) return false;
            self::error(self::message('Please answer yes or no.', 'Veuillez répondre oui ou non.'));
        }
    }

    /** @param array<string|int, string> $choices Returns the selected original key. */
    public static function select(string $question, array $choices, string|int|null $default = null): string|int
    {
        if ($choices === [] || ($default !== null && !array_key_exists($default, $choices))) {
            throw new \InvalidArgumentException('select() needs choices and a valid default key.');
        }
        Terminal::write(STDERR, $question);
        $keys = array_keys($choices);
        $defaultPosition = null;
        foreach ($keys as $index => $key) {
            if (!is_string($choices[$key])) throw new \InvalidArgumentException('Choice labels must be strings.');
            Terminal::write(STDERR, '  ' . ($index + 1) . ') ' . $choices[$key]);
            if ($default !== null && (string) $key === (string) $default) $defaultPosition = $index + 1;
        }
        $prompt = self::message('Choice', 'Choix') . ($defaultPosition !== null ? " [{$defaultPosition}]" : '');
        while (true) {
            $answer = self::read($prompt);
            if ($answer === null) return $default !== null ? $keys[$defaultPosition - 1] : throw self::closed();
            if ($answer === '' && $default !== null) return $keys[$defaultPosition - 1];
            if (preg_match('/^[1-9][0-9]*$/D', $answer) === 1 && isset($keys[(int) $answer - 1])) {
                return $keys[(int) $answer - 1];
            }
            self::error(self::message('Enter a number from the list.', 'Saisissez un numéro de la liste.'));
        }
    }

    /** Read without echoing. Refuse redirected input instead of exposing a secret. */
    public static function secret(string $question): string
    {
        if (!function_exists('stream_isatty') || !stream_isatty(STDIN) || !stream_isatty(STDERR)
            || !function_exists('proc_open')) {
            throw new InputException(self::message(
                'Hidden input requires an interactive terminal.',
                'La saisie masquée nécessite un terminal interactif.',
            ));
        }
        $script = <<<'SH'
saved=$(stty -g) || exit 1
trap 'stty "$saved"' EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM
stty -echo || exit 1
IFS= read -r value || exit 2
printf '%s' "$value"
SH;
        $command = ['/bin/sh', '-c', $script];
        if (PHP_OS_FAMILY === 'Windows') {
            $script = <<<'PS'
$ErrorActionPreference = 'Stop'
try {
    $value = [Security.SecureString]::new()
    while ($true) {
        $key = [Console]::ReadKey($true)
        if ($key.Key -eq [ConsoleKey]::Enter) { break }
        if ($key.Key -eq [ConsoleKey]::Backspace) {
            if ($value.Length -gt 0) { $value.RemoveAt($value.Length - 1) }
        } elseif (![char]::IsControl($key.KeyChar)) { $value.AppendChar($key.KeyChar) }
    }
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($value)
    try {
        [Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)
        [Console]::Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer))
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
        $value.Dispose()
    }
} catch { exit 1 }
PS;
            $command = ['powershell.exe', '-NoLogo', '-NoProfile', '-Command', $script];
        }
        fwrite(STDERR, self::plain($question) . ': ');
        $process = @proc_open($command, [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            Terminal::write(STDERR, '');
            throw new InputException(self::message('Hidden input is unavailable.', 'La saisie masquée est indisponible.'));
        }
        $value = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);
        Terminal::write(STDERR, '');
        if ($status !== 0 || $value === false) {
            throw new InputException(self::message('Hidden input was interrupted or is unavailable.', 'La saisie masquée a été interrompue ou est indisponible.'));
        }
        return $value;
    }

    private static function read(string $question): ?string
    {
        fwrite(STDERR, self::plain($question) . ': ');
        $line = fgets(STDIN);
        return $line === false ? null : trim($line);
    }

    private static function closed(): InputException
    {
        return new InputException(self::message(
            'Input ended before an answer was provided. Supply a value or a default.',
            'La saisie s’est terminée sans réponse. Fournissez une valeur ou une valeur par défaut.',
        ));
    }
}

/** Output implementation shared by console helpers. @internal */
final class Terminal
{
    private const COLORS = ['black' => 30, 'red' => 31, 'green' => 32, 'yellow' => 33, 'blue' => 34, 'magenta' => 35, 'cyan' => 36, 'white' => 37, 'gray' => 90, 'bold' => 1];

    /** @param resource $stream */
    public static function interactive($stream): bool
    {
        if (!function_exists('stream_isatty') || !stream_isatty($stream) || strtolower((string) getenv('TERM')) === 'dumb') return false;
        if (PHP_OS_FAMILY === 'Windows') {
            return function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support($stream, true);
        }
        return true;
    }

    /** @param resource $stream */
    public static function styled($stream, string $text, ?string $color): string
    {
        if ($color !== null && !isset(self::COLORS[$color])) throw new \InvalidArgumentException("Unknown console color: {$color}");
        $text = Console::plain($text);
        $noColor = getenv('NO_COLOR');
        if ($color === null || ($noColor !== false && $noColor !== '') || !self::interactive($stream)) return $text;
        return "\033[" . self::COLORS[$color] . 'm' . $text . "\033[0m";
    }

    /** @param resource $stream */
    public static function write($stream, string $text, ?string $color = null): void
    {
        fwrite($stream, self::styled($stream, $text, $color) . PHP_EOL);
    }

    public static function singleLine(string $text): string
    {
        return Console::plain(str_replace(["\r\n", "\r", "\n", "\t"], [' / ', ' / ', ' / ', '  '], $text));
    }

    public static function width(string $text): int
    {
        $text = preg_replace('/\p{M}/u', '', $text) ?? $text;
        if (function_exists('mb_strwidth')) return mb_strwidth($text, 'UTF-8');
        $count = preg_match_all('/./us', $text);
        return $count === false ? strlen($text) : $count;
    }

    public static function truncate(string $text, int $width): string
    {
        if ($width <= 0) return '';
        if (self::width($text) <= $width) return $text;
        $suffix = str_repeat('.', min(3, $width));
        $result = '';
        $characters = preg_match_all('/\X/u', $text, $matches) === false ? str_split($text) : $matches[0];
        foreach ($characters as $character) {
            if (self::width($result . $character) > $width - strlen($suffix)) break;
            $result .= $character;
        }
        return $result . $suffix;
    }
}

/** A single progress display on stderr; call finish() only after successful work. */
final class ProgressBar
{
    private int $current = 0;
    private bool $closed = false;
    private readonly bool $interactive;
    private readonly string $label;
    private float $lastRender = 0;

    public function __construct(private readonly int $total, string $label = '')
    {
        if ($total < 0) throw new \InvalidArgumentException('Progress total must be nonnegative.');
        $this->label = Terminal::singleLine($label);
        $this->interactive = Terminal::interactive(STDERR);
        $this->render();
    }

    public function advance(int $step = 1): void
    {
        if ($step < 0 || $step > $this->total - $this->current) throw new \InvalidArgumentException('Progress step is outside the remaining work.');
        $this->set($this->current + $step);
    }

    public function set(int $current): void
    {
        if ($this->closed) throw new \LogicException('Progress is already closed.');
        if ($current < $this->current || $current > $this->total) throw new \InvalidArgumentException('Progress must move forward within its total.');
        $this->current = $current;
        if ($this->interactive && ($current === $this->total || hrtime(true) / 1e9 - $this->lastRender >= 0.08)) $this->render();
    }

    public function finish(): void
    {
        if ($this->closed) return;
        $this->current = $this->total;
        $this->closed = true;
        $this->render(true);
    }

    public function abort(): void
    {
        if ($this->closed) return;
        $this->closed = true;
        $this->render(true, true);
    }

    public function __destruct()
    {
        // Never present abandoned or failed work as completed.
        if (!$this->closed && isset($this->label)) {
            try { $this->abort(); } catch (\Throwable) {}
        }
    }

    private function render(bool $final = false, bool $aborted = false): void
    {
        $percent = $this->current === $this->total ? 100 : min(99, (int) floor(($this->current / $this->total) * 100));
        $counts = "{$this->current}/{$this->total} ({$percent}%)";
        $prefix = $this->label === '' ? '' : $this->label . ': ';
        if ($aborted) $counts .= ' - ' . Console::message('Interrupted', 'Interrompu');
        if (!$this->interactive) {
            Terminal::write(STDERR, $prefix . $counts);
        } else {
            $columns = filter_var(getenv('COLUMNS'), FILTER_VALIDATE_INT);
            $columns = is_int($columns) && $columns >= 10 ? min($columns, 500) : 80;
            $prefix = Terminal::truncate($prefix, min(24, max(0, $columns - Terminal::width($counts) - 6)));
            $barWidth = min(20, max(0, $columns - Terminal::width($prefix . $counts) - 5));
            $filled = (int) floor($barWidth * ($percent / 100));
            $bar = $barWidth > 0 ? '[' . str_repeat('=', $filled) . str_repeat('-', $barWidth - $filled) . '] ' : '';
            $frame = Terminal::truncate($prefix . $bar . $counts, $columns - 1);
            fwrite(STDERR, "\r\033[2K" . Terminal::styled(STDERR, $frame, $aborted ? 'yellow' : 'cyan') . ($final ? PHP_EOL : ''));
        }
        $this->lastRender = hrtime(true) / 1e9;
    }
}

final class ParsedInput
{
    /** @internal */
    public function __construct(
        private readonly array $arguments,
        private readonly array $options,
        private readonly bool $helpRequested,
        private readonly string $help,
    ) {}

    public function argument(string $name): string|int|null
    {
        if (!array_key_exists($name, $this->arguments)) throw new \InvalidArgumentException("Unknown argument: {$name}");
        return $this->arguments[$name];
    }

    public function option(string $name): string|int|bool|null
    {
        if (!array_key_exists($name, $this->options)) throw new \InvalidArgumentException("Unknown option: {$name}");
        return $this->options[$name];
    }

    public function helpRequested(): bool { return $this->helpRequested; }
    public function help(): string { return $this->help; }
}

/** A small, standalone argument schema. No global argv reads and no process exits. */
final class Input
{
    private array $arguments = [];
    private array $options = [];
    private array $shortcuts = [];

    private function __construct(private readonly string $description, private readonly string $usage) {}

    public static function define(string $description = '', string $usage = 'aml run --'): self
    {
        return new self($description, $usage);
    }

    public function argument(string $name, string $description = '', bool $required = false, string|int|null $default = null, string $type = 'string', ?int $min = null, ?int $max = null): self
    {
        $definition = $this->definition($name, $description, $type, $default, $required, $min, $max);
        if (isset($this->arguments[$name])) throw new \InvalidArgumentException("Duplicate argument: {$name}");
        if ($required) {
            foreach ($this->arguments as $argument) {
                if (!$argument['required']) throw new \InvalidArgumentException('Required arguments must precede optional arguments.');
            }
        }
        $this->arguments[$name] = $definition;
        return $this;
    }

    public function option(string $name, string $description = '', string $type = 'string', string|int|null $default = null, ?string $shortcut = null, bool $required = false, ?int $min = null, ?int $max = null): self
    {
        $definition = $this->definition($name, $description, $type, $default, $required, $min, $max);
        return $this->addOption($name, $definition, $shortcut);
    }

    public function flag(string $name, string $description = '', ?string $shortcut = null): self
    {
        $definition = $this->definition($name, $description, 'string', null, false, null, null);
        $definition['type'] = 'bool';
        $definition['default'] = false;
        return $this->addOption($name, $definition, $shortcut);
    }

    private function addOption(string $name, array $definition, ?string $shortcut): self
    {
        if ($name === 'help' || isset($this->options[$name])) throw new \InvalidArgumentException("Reserved or duplicate option: {$name}");
        if ($shortcut !== null && (preg_match('/^[a-zA-Z]$/D', $shortcut) !== 1 || $shortcut === 'h' || isset($this->shortcuts[$shortcut]))) {
            throw new \InvalidArgumentException("Invalid, reserved or duplicate shortcut: {$shortcut}");
        }
        $definition['shortcut'] = $shortcut;
        $this->options[$name] = $definition;
        if ($shortcut !== null) $this->shortcuts[$shortcut] = $name;
        return $this;
    }

    private function definition(string $name, string $description, string $type, string|int|null $default, bool $required, ?int $min, ?int $max): array
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $name) !== 1) throw new \InvalidArgumentException("Invalid input name: {$name}");
        if (!in_array($type, ['string', 'int'], true)) throw new \InvalidArgumentException('Input types are string and int; use flag() for booleans.');
        if (($min !== null || $max !== null) && $type !== 'int') throw new \InvalidArgumentException('Bounds require an int input.');
        if ($min !== null && $max !== null && $min > $max) throw new \InvalidArgumentException('min must not exceed max.');
        if ($required && $default !== null) throw new \InvalidArgumentException('A required input cannot have a default.');
        if ($default !== null && (($type === 'int' && !is_int($default)) || ($type === 'string' && !is_string($default))
            || ($min !== null && $default < $min) || ($max !== null && $default > $max))) {
            throw new \InvalidArgumentException("Invalid default for {$name}.");
        }
        return compact('name', 'description', 'type', 'default', 'required', 'min', 'max');
    }

    /** @param list<string> $tokens */
    public function parse(array $tokens): ParsedInput
    {
        $tokens = array_values($tokens);
        $options = array_column($this->options, 'default', 'name');
        $arguments = array_column($this->arguments, 'default', 'name');
        $seen = [];
        $positionals = [];
        $literal = false;
        $help = false;
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (!is_string($token)) throw new \InvalidArgumentException('Input tokens must be strings.');
            if (!$literal && $token === '--') { $literal = true; continue; }
            if (!$literal && in_array($token, ['--help', '-h'], true)) { $help = true; continue; }
            // Negative integers are positional values, unless consumed by an option.
            if ($literal || $token === '-' || !str_starts_with($token, '-') || preg_match('/^-[0-9]+$/D', $token) === 1) {
                $positionals[] = $token;
                continue;
            }
            $long = str_starts_with($token, '--');
            $parts = explode('=', substr($token, $long ? 2 : 1), 2);
            $name = $long ? $parts[0] : ($this->shortcuts[$parts[0]] ?? '');
            $definition = $this->options[$name] ?? null;
            if ($definition === null) throw $this->invalid(Console::message("Unknown option: {$token}.", "Option inconnue : {$token}."));
            if (isset($seen[$name])) throw $this->invalid(Console::message("Option --{$name} was supplied twice.", "L’option --{$name} est fournie deux fois."));
            $seen[$name] = true;
            if ($definition['type'] === 'bool') {
                if (isset($parts[1])) throw $this->invalid(Console::message("Flag --{$name} does not accept a value.", "L’option --{$name} n’accepte pas de valeur."));
                $options[$name] = true;
                continue;
            }
            $value = $parts[1] ?? null;
            if ($value === null) {
                $value = $tokens[$index + 1] ?? null;
                if (!is_string($value) || (str_starts_with($value, '-') && $value !== '-'
                    && !($definition['type'] === 'int' && preg_match('/^-[0-9]+$/D', $value) === 1))) {
                    throw $this->invalid(Console::message("Missing value for --{$name}.", "Valeur manquante pour --{$name}."));
                }
                $index++;
            }
            $options[$name] = $this->convert($value, $definition, '--' . $name);
        }
        if (!$help) {
            if (count($positionals) > count($arguments)) throw $this->invalid(Console::message('Too many arguments.', 'Trop d’arguments.'));
            foreach (array_values($this->arguments) as $index => $definition) {
                $name = $definition['name'];
                if (array_key_exists($index, $positionals)) $arguments[$name] = $this->convert($positionals[$index], $definition, $name);
                elseif ($definition['required']) throw $this->invalid(Console::message("Missing argument: {$name}.", "Argument manquant : {$name}."));
            }
            foreach ($this->options as $name => $definition) {
                if ($definition['required'] && !isset($seen[$name])) throw $this->invalid(Console::message("Missing option: --{$name}.", "Option manquante : --{$name}."));
            }
        }
        return new ParsedInput($arguments, $options, $help, $this->help());
    }

    private function convert(string $value, array $definition, string $label): string|int
    {
        if ($definition['type'] === 'string') return $value;
        $integer = preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) === 1 ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if ($integer === false) throw $this->invalid(Console::message("{$label} must be an integer.", "{$label} doit être un entier."));
        if ($definition['min'] !== null && $integer < $definition['min']) {
            throw $this->invalid(Console::message("{$label} must be at least {$definition['min']}.", "{$label} doit être au moins {$definition['min']}."));
        }
        if ($definition['max'] !== null && $integer > $definition['max']) {
            throw $this->invalid(Console::message("{$label} must be at most {$definition['max']}.", "{$label} doit être au plus {$definition['max']}."));
        }
        return $integer;
    }

    public function help(): string
    {
        $usage = $this->usage . ' [options]';
        foreach ($this->arguments as $name => $definition) $usage .= $definition['required'] ? " <{$name}>" : " [{$name}]";
        $lines = $this->description !== '' ? [$this->description, ''] : [];
        $lines[] = Console::message('Usage: ', 'Utilisation : ') . $usage;
        if ($this->arguments !== []) {
            $lines[] = '';
            $lines[] = 'Arguments :';
            foreach ($this->arguments as $name => $definition) $lines[] = '  ' . $name . ' <' . $definition['type'] . '>  ' . $this->describe($definition);
        }
        $lines[] = '';
        $lines[] = 'Options :';
        foreach ($this->options as $name => $definition) {
            $label = ($definition['shortcut'] !== null ? '-' . $definition['shortcut'] . ', ' : '') . '--' . $name;
            if ($definition['type'] !== 'bool') $label .= ' <' . $definition['type'] . '>';
            $lines[] = '  ' . $label . '  ' . $this->describe($definition);
        }
        $lines[] = '  -h, --help  ' . Console::message('Show this help.', 'Afficher cette aide.');
        return Console::plain(implode(PHP_EOL, $lines));
    }

    private function describe(array $definition): string
    {
        $text = $definition['description'];
        if ($definition['required']) $text .= Console::message(' [required]', ' [obligatoire]');
        elseif ($definition['default'] !== null && $definition['type'] !== 'bool') {
            $text .= Console::message(' [default: ', ' [défaut : ') . json_encode($definition['default'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ']';
        }
        if ($definition['min'] !== null) $text .= ' [min: ' . $definition['min'] . ']';
        if ($definition['max'] !== null) $text .= ' [max: ' . $definition['max'] . ']';
        return trim($text);
    }

    private function invalid(string $message): InputException
    {
        return new InputException(Console::plain($message) . Console::message(' Use --help for usage.', ' Consultez --help pour l’utilisation.'));
    }
}
