# Console programs

`aml create-console` generates a standalone PHP 8.2+ program. Its local
`lib/console.php` provides the `AML\Console` helpers without a download,
Composer installation, or Web framework. The entry point remains
`Program::main(array $arguments): int`.

```bash
aml create-console hello
cd hello
aml run -- Andre --repeat=2
aml run -- -i
aml run -- --help
aml test
```

The generated program greets once by default. `-r` / `--repeat` accepts an integer
from 1 to 100, and `-i` / `--interactive` asks for a name.

## Complete example: import CSV contacts

Every new project includes `examples/contacts.csv`, the launcher
`examples/import-csv.php` and the `App\Examples\ImportCsv` class in
`src/Examples/ImportCsv.php`. It saves contacts to a new JSON file without a
database or Composer dependency.

```bash
php examples/import-csv.php --help
php examples/import-csv.php examples/contacts.csv --dry-run
php examples/import-csv.php examples/contacts.csv --output=contacts.json
```

The UTF-8 CSV must contain exactly the `name` and `email` columns, in either
order. UTF-8 BOM, LF/CRLF endings, blank lines, quoted commas and doubled quotes
are supported. Leading and trailing spaces in values are removed. For a
semicolon-delimited file:

```bash
php examples/import-csv.php contacts.csv --delimiter=";" --output=contacts.json
```

Both fields are required and must fit on one line without control characters.
Emails must pass PHP `FILTER_VALIDATE_EMAIL`; this example rejects duplicates
case-insensitively. Limits are 10 MiB and 10,000 contacts. Errors identify the
physical starting line of a record. Details are limited to the first 20 invalid
rows; the summary counts all valid and invalid data rows.

Progress measures **validation**: 100% means every row has been examined, not
that importing succeeded. Invalid data prevents any contacts from being saved.
`--dry-run` checks data without creating files or checking destination write
permissions. A successful import produces a JSON array of
`{"name": "...", "email": "..."}` objects. The output directory must already
exist; existing files or links are never replaced. Detected write failures
remove the new incomplete file, but an abrupt process termination during writing
can leave a file that needs removal.

The summary uses `STDOUT`; progress and diagnostics use `STDERR`. Exit codes are
**0** for successful import or dry run, **2** for invalid input, and **1** for a
save failure. Structural CSV errors stop reading before the detailed summary.

To use the class as a command, add
`"import-contacts": "App\\Examples\\ImportCsv"` to `application.commands` in
`phpaml.json`, then run `aml run import-contacts examples/contacts.csv --dry-run`.
The example is not registered automatically, preserving the greeting program.
Existing projects can copy the launcher, class and sample from a new project
and use its console library.

## Structured input

Use this inside `Program::main()` with `use AML\Console\Input;` and
`use AML\Console\Console;` at the top of the file:

```php
$input = Input::define('Prepare a data import.')
    ->argument('file', 'File to import', required: true)
    ->option('limit', 'Maximum rows', type: 'int', default: 100, shortcut: 'l', min: 1)
    ->flag('dry-run', 'Preview only', shortcut: 'd')
    ->parse($arguments);

if ($input->helpRequested()) {
    Console::line($input->help());
    return 0;
}

$file = $input->argument('file');
$limit = $input->option('limit');
$dryRun = $input->option('dry-run');
```

Values support `string` and `int`; flags are booleans, defaulting to `false`.
Optional inputs without a default return `null`. Required positional arguments
must precede optional ones. Required inputs cannot have defaults. Integer
`min` and `max` bounds are inclusive. Integers use decimal notation without
leading zeros and must fit in a PHP integer.

Both `--limit=20` and `--limit 20` work, as do `-l=20` and `-l 20`. Shortcuts
cannot be grouped. Unknown or repeated options, missing values, extra positional
arguments, and invalid types or bounds are rejected. Flags take no value.
Use `--name=-value` for string values starting with a dash.

`-h` and `--help` are reserved and skip the required-input checks. Handle help
before reading values or prompting. Help lists descriptions, types, defaults,
and bounds. Supply a custom `usage:` to `Input::define()` for a standalone script.

The first `--` after `aml run` belongs to AML. A second `--` ends program option
parsing: `aml run -- -- --help` passes the literal name `--help` to the generated
greeting program.

## Multiple commands

```bash
aml make:command ImportUsers
aml run list
aml run import-users
aml run import-users --help
```

The generator creates `src/Commands/ImportUsers.php` and registers `import-users`
in `application.commands` in `phpaml.json`. Each class exposes its own
`public static function main(array $arguments): int`, with an `Input` definition
and help handling. The scaffold initially prints a message; add your application
logic and argument schema inside the generated method.

For example, add `->argument('file', required: true)` and
`->option('limit', type: 'int', default: 100, min: 1)` before `->parse($arguments)`.
Read these values after the help check. Then run:

```bash
aml run import-users clients.csv --limit=100
```

`ImportUsers` and `import-users` both generate the class `ImportUsers` with the
command name `import-users`. `Admin/HTTPImport` generates
`App\Commands\Admin\HTTPImport` and the command `admin:http-import`. Existing
files, duplicate command names or PHP classes, and reserved PHP words are
rejected without overwriting the existing program.
Windows device names (`Con`, `Nul`, `Com1` through `Com9`, `Lpt1` through `Lpt9`,
`Prn`, `Aux`) are also rejected in every name segment, including on macOS/Linux,
so generated commands remain portable to Windows. Both `/` and `\` separators
are accepted for nested commands.

| Invocation | Behavior |
| --- | --- |
| `aml run` | Calls `Program::main([])` |
| `aml run import-users clients.csv` | Calls `ImportUsers::main(['clients.csv'])` |
| `aml run list` | Lists registered commands without loading their code |
| `aml run --help` | Lists commands when present; otherwise requests program help |
| `aml run -- Andre` | Explicitly passes `Andre` to `Program::main()` |
| `aml run -- --help` | Requests `Program::main()` help |

Once commands are registered, an unknown command name is an input error with
exit code 2. Use `aml run -- ...` to pass arguments to the main program. Without
registered commands, the old `aml run Andre` form remains supported. `list` is
reserved; `aml run -- list` passes that literal word to the main program.

Registration is explicit: each entry maps a command name to an object containing
`class` and an optional `description`. A class string alone is also supported,
including for aliases. Classes may be loaded through Composer. Catalog
descriptions come from the manifest; detailed help comes from the command's
`Input` definition. A command receives only its arguments, without its own name,
and its exit code is preserved.

In older console projects, the generator adds `lib/console.php` if missing and
preserves existing libraries and bootstraps. `aml run` and `aml test` load the
library even with an older bootstrap. For standalone PHP tests, explicitly load
the library in your bootstrap if needed. The generator is limited to console
projects; Web project scripts retain their existing behavior.

## Interactive input

```php
$name = Console::ask('Your name', 'Andre');
$confirmed = Console::confirm('Continue?', default: false);
$environment = Console::select('Environment', [
    'dev' => 'Development',
    'prod' => 'Production',
], default: 'dev');
$password = Console::secret('Password');
```

`ask()` trims surrounding whitespace. `confirm()` accepts English and French
yes/no answers. `select()` takes a displayed number and returns the original
key; its default is a key. Empty answers use defaults. Invalid answers retry.
At EOF, an available default is returned; otherwise `InputException` is raised.

`secret()` displays no typed characters. It requires interactive stdin and
stderr, plus `proc_open`. It uses `stty` on Unix and PowerShell keyboard input
on Windows. Unix terminal echo is restored after input or interruption.
Redirected input and unavailable hidden input fail without an unmasked fallback.
Keep the returned value out of logs and output.

Prompts and `Console::error()` use stderr. `Console::line()` uses stdout, so
redirecting program output does not include prompts. Output helpers remove
terminal control characters and display plain text; use PHP output functions
when your application requires raw output.

## Terminal display

New projects include an executable example:

```bash
php examples/display.php
php examples/display.php --fail
php examples/display.php --help
```

`--fail` simulates interrupted work and exits with code 2. The example does not
read or modify the files named in its table.

```php
Console::line('Import report', color: 'cyan');
Console::info('Three files to process.');
Console::success('Import completed.');
Console::warning('Two rows were skipped.');
Console::error('The requested file was not found.');

Console::table(['File', 'Rows'], [
    ['customers.csv', 120],
    ['orders.csv', 48],
], maxColumnWidth: 32);
```

`line()` and `table()` use stdout. Status messages use stderr with readable
labels: `[INFO]`, `[OK]`, `[WARNING]`, and `[ERROR]` in English. `error()` only
displays a message; it does not exit or change the program's exit code.

Colors are `black`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan`, `white`,
and `gray`; `bold` is also available. Detection is independent for stdout and
stderr. A nonempty `NO_COLOR` (including `0`) disables colors. `TERM=dumb`
disables both colors and animation. Redirected streams always receive plain
text without control sequences. For example, redirecting stdout to a report
file keeps diagnostics on the terminal.

Tables use ASCII borders and emphasize headers on compatible terminals. Rows
can be supplied by a generator; each row must be a list with one scalar or null
value per header. All rows are validated before any table output. Columns are
limited to 40 display positions by default, configurable with `maxColumnWidth`
(minimum 4). Longer cells end in `...`. Newlines become ` / ` and tabs become
two spaces. The limit applies to individual columns, not the whole table.
Wide-character alignment uses `mbstring`, bundled with AML; without it, their
width is approximate. Emoji rendering can differ between terminals.

```php
$progress = Console::progress(count($files), 'Import');
try {
    foreach ($files as $file) {
        importFile($file); // Your processing code.
        $progress->advance();
    }
    $progress->finish();
} catch (\Throwable $error) {
    $progress->abort();
    throw $error;
}
Console::success('All files processed.');
```

Progress uses stderr. On a compatible terminal it updates the same line at a
limited rate. `COLUMNS` sets its available width, defaulting to 80. With redirected
stderr or `TERM=dumb`, only the initial and final summaries are written.
`NO_COLOR` removes color while keeping interactive progress updates.

`advance()` increments by one, `advance(5)` by five, and `set(20)` sets the absolute
count. Progress cannot move backwards or exceed its total. A zero total is valid.
Call `finish()` only after successful work: it sets the count to the total and
closes the line. `abort()` keeps the current count and marks it interrupted.
Abandoned objects are also marked interrupted during normal destruction; abrupt
process termination cannot guarantee a final message. Closing twice is harmless.
Use one bar at a time and close it before printing other messages or asking questions.

## Errors and loading

`aml run` reports `AML\Console\InputException` on stderr without a stack trace
and exits with **2**. Other uncaught exceptions exit with **1**. The generated
program returns **0** on success. Applications may catch input errors themselves.

AML passes its selected language to the program. `AML_LANG=en` or `AML_LANG=fr`
controls built-in diagnostics and help labels, not application descriptions or
questions. Standalone PHP execution defaults to English.

`bootstrap.php` loads Composer if available, the console library, and `App\`
classes. `composer.json` also declares the library in `autoload.files`.
`php tests/run.php` works without AML or Composer installed. Standalone scripts
can require the bootstrap and handle their own exception exit codes.

Existing projects are not rewritten. To adopt the helpers, copy `lib/console.php`
from a newly generated project and require it in your bootstrap. Projects without
a bootstrap can copy the generated one while preserving custom loading logic.

See the [French guide](console.md) for a complete `Program` example.

## Checking the console before distribution

From the CLI repository, with PHP and `mbstring` available:

```bash
php tests/console-input.php
php tests/console-display.php
php tests/console-smoke.php
php tests/console-commands.php
php tests/console-import.php
```

The independent `Test AML Console` workflow defines these checks for Linux,
macOS and Windows with PHP 8.2 and 8.4. They include paths with spaces and
accents, literal arguments, CRLF input and relocated projects. On macOS/Linux,
the Python tests `console-secret-tty.py` and `console-display-tty.py` also exercise
an interactive terminal. Automated Windows checks cover redirected streams;
hidden input and display in a real Windows terminal still require validation
on that platform.

To check an extracted or installed package on its target operating system,
from the repository matching that exact package version:

```bash
php tests/console-package.php /path/to/package
```

This check uses the real `bin/aml` launcher (`bin/aml.exe` on Windows) to create
projects, dispatch commands and run project tests. Standalone scripts use the
bundled PHP runtime. It also checks literal arguments, exit codes, display and
CSV import. It creates a temporary project without installing or modifying the
package. A missing launcher, or a generator, library or CSV example differing
from the source, fails the check.

An optional second path checks the public launcher installed on the system:

```bash
# After installation on a Linux test machine:
/opt/phpaml/runtime/php/bin/php tests/console-package.php /opt/phpaml /usr/local/bin/aml

# After installation on a macOS test machine:
/usr/local/lib/aml/runtime/php/bin/php tests/console-package.php /usr/local/lib/aml /usr/local/bin/aml
```

The chosen launcher must belong to the package being checked. Build scripts run
the check before creating archives. Build workflows also define checks after
Debian, macOS and Windows installation. The Windows check installs into a
temporary directory and then uninstalls. These steps require the target system;
having a workflow definition is not evidence that it has run.

### Manual checks in Windows Terminal

On a Windows test machine with the package installed, open PowerShell in the
CLI repository matching that package:

```powershell
$amlCommand = (Get-Command aml.exe -ErrorAction Stop).Source
$amlRoot = Split-Path (Split-Path $amlCommand -Parent) -Parent
$amlPhp = Join-Path $amlRoot 'runtime/php/php.exe'
& $amlPhp tests/console-package.php $amlRoot $amlCommand
& $amlPhp tests/console-terminal.php display $amlRoot
& $amlPhp tests/console-terminal.php secret $amlRoot
& $amlPhp tests/console-terminal.php interrupt $amlRoot
```

Run the three diagnostics separately, without redirection. For `display`, check
the blue line when colors are enabled, table alignment and progress on a single
line. With `NO_COLOR`, colors should disappear. Exit code 0 only confirms that
the display ran; visual inspection is still required.

For `secret`, enter only the dummy value `console-test`, then Enter. Typed
characters must remain hidden; the received value is checked without printing
it. For `interrupt`, press `Ctrl+C` at the prompt, verify cancellation and then
type a normal shell command to check that keyboard echo is restored. Do not mark
the diagnostic passed simply because it started. Record the OS, PHP version,
terminal and observed result for every check. These diagnostics also work on
macOS/Linux.
