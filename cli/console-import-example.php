<?php

declare(strict_types=1);

namespace App\Examples;

use AML\Console\Console;
use AML\Console\Input;
use AML\Console\InputException;

/** A standalone example: validate contacts before saving a new JSON file. */
final class ImportCsv
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_CONTACTS = 10_000;

    /** @param list<string> $arguments */
    public static function main(array $arguments): int
    {
        try {
            $input = Input::define(Console::message('Import CSV contacts into a new JSON file.', 'Importer des contacts CSV dans un nouveau fichier JSON.'), usage: 'php examples/import-csv.php')
                ->argument('file', Console::message('UTF-8 CSV with name,email columns', 'CSV UTF-8 avec les colonnes name,email'), required: true)
                ->option('output', Console::message('New JSON file', 'Nouveau fichier JSON'), default: 'contacts.json', shortcut: 'o')
                ->option('delimiter', Console::message('Comma or semicolon', 'Virgule ou point-virgule'), default: ',')
                ->flag('dry-run', Console::message('Validate without writing', 'Valider sans enregistrer'), shortcut: 'd')
                ->parse($arguments);
            if ($input->helpRequested()) {
                Console::line($input->help());
                return 0;
            }
            $delimiter = $input->option('delimiter');
            if (!in_array($delimiter, [',', ';'], true)) {
                throw new InputException(Console::message('Use a comma or semicolon for --delimiter.', 'Utilisez une virgule ou un point-virgule pour --delimiter.'));
            }
            return self::import($input->argument('file'), $input->option('output'), $delimiter, $input->option('dry-run'));
        } catch (InputException $error) {
            Console::error($error->getMessage());
            return 2;
        } catch (\Throwable $error) {
            Console::error($error->getMessage());
            return 1;
        }
    }

    private static function import(string $file, string $output, string $delimiter, bool $dryRun): int
    {
        $records = self::readCsv($file, $delimiter);
        $header = array_shift($records);
        if ($header === null) throw new InputException(Console::message('The CSV is empty.', 'Le CSV est vide.'));
        $columns = array_map('trim', $header['cells']);
        $sorted = $columns;
        sort($sorted);
        if ($sorted !== ['email', 'name'] || preg_match('/[\x00-\x1F\x7F]/', implode('', $header['cells'])) === 1) {
            throw new InputException(self::atLine($header['line'], Console::message('Expected exactly the name and email columns.', 'Les colonnes name et email sont requises, sans autre colonne.')));
        }
        if ($records === []) throw new InputException(Console::message('The CSV has no contacts.', 'Le CSV ne contient aucun contact.'));

        $contacts = [];
        $seen = [];
        $errors = [];
        $invalid = 0;
        $progress = Console::progress(count($records), Console::message('Validation', 'Validation'));
        try {
            foreach ($records as $record) {
                $reason = null;
                if (count($record['cells']) !== count($columns)) {
                    $reason = Console::message('Expected two fields.', 'Deux champs sont attendus.');
                } else {
                    $contact = array_combine($columns, array_map('trim', $record['cells']));
                    if ($contact['name'] === '' || $contact['email'] === '') {
                        $reason = Console::message('Name and email must not be empty.', 'Le nom et l’adresse courriel ne doivent pas être vides.');
                    } elseif (preg_match('/[\x00-\x1F\x7F]/', implode('', $record['cells'])) === 1) {
                        $reason = Console::message('Names and emails must be on one line without control characters.', 'Le nom et le courriel doivent tenir sur une ligne, sans caractères de contrôle.');
                    } elseif (filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false) {
                        $reason = Console::message('Invalid email address.', 'Adresse courriel invalide.');
                    } elseif (isset($seen[strtolower($contact['email'])])) {
                        $first = $seen[strtolower($contact['email'])];
                        $reason = Console::message("Duplicate email (first seen on line {$first}).", "Courriel en double (première occurrence à la ligne {$first}).");
                    } else {
                        $seen[strtolower($contact['email'])] = $record['line'];
                        $contacts[] = ['name' => $contact['name'], 'email' => $contact['email']];
                    }
                }
                if ($reason !== null) {
                    $invalid++;
                    if (count($errors) < 20) $errors[] = self::atLine($record['line'], $reason);
                }
                $progress->advance();
            }
            // This counter measures validation, not successful imports.
            $progress->finish();
        } catch (\Throwable $error) {
            $progress->abort();
            throw $error;
        }

        if ($invalid > 0) {
            foreach ($errors as $error) Console::error($error);
            if ($invalid > count($errors)) Console::warning(Console::message('Only the first 20 invalid rows are listed.', 'Seules les 20 premières lignes invalides sont détaillées.'));
            self::summary(count($records), count($contacts), $invalid, 0);
            Console::error(Console::message('Import cancelled. No file was written.', 'Import annulé. Aucun fichier enregistré.'));
            return 2;
        }
        if ($dryRun) {
            self::summary(count($records), count($contacts), 0, 0);
            Console::success(Console::message('Dry run complete. No file was written.', 'Simulation terminée. Aucun fichier enregistré.'));
            return 0;
        }
        try {
            self::save($output, $contacts);
        } catch (\Throwable $error) {
            self::summary(count($records), count($contacts), 0, 0);
            throw $error;
        }
        self::summary(count($records), count($contacts), 0, count($contacts));
        Console::success(Console::message("Contacts saved to {$output}.", "Contacts enregistrés dans {$output}."));
        return 0;
    }

    /** @return list<array{line: int, cells: list<string>}> */
    private static function readCsv(string $file, string $delimiter): array
    {
        if ($file === '' || str_contains($file, '://') || !is_file($file)) {
            throw new InputException(Console::message('Provide an existing local CSV file.', 'Fournissez un fichier CSV local existant.'));
        }
        $content = @file_get_contents($file, false, null, 0, self::MAX_BYTES + 1);
        if ($content === false) throw new InputException(Console::message('The CSV cannot be read.', 'Le CSV ne peut pas être lu.'));
        if (strlen($content) > self::MAX_BYTES) throw new InputException(Console::message('This example accepts CSV files up to 10 MiB.', 'Cet exemple accepte les CSV de 10 Mio au maximum.'));
        if (str_starts_with($content, "\xEF\xBB\xBF")) $content = substr($content, 3);

        // Parse a bounded snapshot so validation and saving use the same input.
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) throw new \RuntimeException('Cannot prepare the CSV reader.');
        try {
            if (fwrite($stream, $content) !== strlen($content)) throw new \RuntimeException('Cannot prepare the CSV reader.');
            unset($content);
            rewind($stream);
            $records = [];
            $line = 1;
            while (true) {
                $start = ftell($stream);
                $cells = fgetcsv($stream, null, $delimiter, '"', '');
                if ($cells === false) break;
                $end = ftell($stream);
                fseek($stream, $start);
                $raw = (string) stream_get_contents($stream, $end - $start);
                $recordLine = $line;
                $line += substr_count($raw, "\n");
                if (trim($raw, " \t\r\n") === '') continue;
                if (preg_match('//u', $raw) !== 1) {
                    throw new InputException(self::atLine($recordLine, Console::message('UTF-8 text is required.', 'Le texte doit être encodé en UTF-8.')));
                }
                if (!self::validQuoting(rtrim($raw, "\r\n"), $delimiter)) {
                    throw new InputException(self::atLine($recordLine, Console::message('Malformed CSV quoting.', 'Guillemets CSV mal formés.')));
                }
                $records[] = ['line' => $recordLine, 'cells' => $cells];
                if (count($records) > self::MAX_CONTACTS + 1) {
                    throw new InputException(Console::message('This example accepts up to 10000 contacts.', 'Cet exemple accepte au maximum 10000 contacts.'));
                }
            }
            return $records;
        } finally {
            fclose($stream);
        }
    }

    /** fgetcsv accepts broken quoting; reject it before accepting any contact. */
    private static function validQuoting(string $record, string $delimiter): bool
    {
        $state = 'start';
        for ($index = 0, $length = strlen($record); $index < $length; $index++) {
            $char = $record[$index];
            if ($state === 'quoted') {
                if ($char === '"') {
                    if (($record[$index + 1] ?? '') === '"') $index++;
                    else $state = 'closed';
                }
            } elseif ($char === $delimiter) {
                $state = 'start';
            } elseif ($state === 'closed' || $char === "\r" || $char === "\n") {
                return false;
            } elseif ($char === '"') {
                if ($state !== 'start') return false;
                $state = 'quoted';
            } else {
                $state = 'plain';
            }
        }
        return $state !== 'quoted';
    }

    /** @param list<array{name: string, email: string}> $contacts */
    private static function save(string $output, array $contacts): void
    {
        if ($output === '' || str_contains($output, '://')) {
            throw new InputException(Console::message('Provide a local path for --output.', 'Fournissez un chemin local pour --output.'));
        }
        $json = json_encode($contacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        // Exclusive creation also protects an existing file or symbolic link.
        $handle = @fopen($output, 'xb');
        if ($handle === false) throw new \RuntimeException(Console::message('Cannot create the output file. It must not exist and its directory must be writable.', 'Impossible de créer le fichier de sortie. Il ne doit pas exister et son dossier doit être accessible en écriture.'));
        $complete = false;
        try {
            for ($offset = 0, $length = strlen($json); $offset < $length; $offset += $written) {
                $written = @fwrite($handle, substr($json, $offset));
                if ($written === false || $written === 0) throw new \RuntimeException(Console::message('Could not write all contacts.', 'Impossible d’enregistrer tous les contacts.'));
            }
            if (!@fflush($handle)) throw new \RuntimeException(Console::message('Could not flush the output file.', 'Impossible de terminer l’écriture du fichier.'));
            $complete = true;
        } finally {
            fclose($handle);
            if (!$complete) @unlink($output);
        }
    }

    private static function atLine(int $line, string $message): string
    {
        return Console::message("Line {$line}: ", "Ligne {$line} : ") . $message;
    }

    private static function summary(int $read, int $valid, int $invalid, int $saved): void
    {
        Console::table([Console::message('Result', 'Résultat'), Console::message('Count', 'Nombre')], [
            [Console::message('Contacts read', 'Contacts lus'), $read],
            [Console::message('Valid', 'Valides'), $valid],
            [Console::message('Invalid', 'Invalides'), $invalid],
            [Console::message('Saved', 'Enregistrés'), $saved],
        ]);
    }
}
