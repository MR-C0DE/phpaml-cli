<?php

declare(strict_types=1);

function deployConfigPath(): string
{
    $base = PHP_OS_FAMILY === 'Windows' ? (getenv('APPDATA') ?: getenv('USERPROFILE')) : getenv('HOME');
    return rtrim((string) ($base ?: sys_get_temp_dir()), '/\\') . '/.phpaml/deploy.json';
}

/** @return array<string, array<string, mixed>> */
function deployProfiles(): array
{
    $data = is_file(deployConfigPath()) ? json_decode((string) file_get_contents(deployConfigPath()), true) : [];
    return is_array($data) ? $data : [];
}

/** @return array<string, mixed> */
function deployProfile(string $name): array
{
    $profiles = deployProfiles();
    if (!isset($profiles[$name]) || !is_array($profiles[$name])) fail("Profil de déploiement introuvable : {$name}.");
    return $profiles[$name];
}

function deployConfigure(string $name, array $arguments): void
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) fail('Nom de profil de déploiement invalide.');
    $host = optionValue($arguments, '--host');
    $user = optionValue($arguments, '--user');
    $path = optionValue($arguments, '--path');
    $port = optionValue($arguments, '--port') ?? '22';
    $key = optionValue($arguments, '--key');
    $strategy = optionValue($arguments, '--strategy') ?? 'releases';
    $publicPath = optionValue($arguments, '--public-path');
    if (!$host || !$user || !$path || !preg_match('/^[A-Za-z0-9.-]+$/', $host)
        || !preg_match('/^[A-Za-z0-9._-]+$/', $user) || !preg_match('~^/[A-Za-z0-9._/-]+$~', $path)
        || filter_var($port, FILTER_VALIDATE_INT) === false || (int) $port < 1 || (int) $port > 65535
        || !in_array($strategy, ['releases', 'public-html', 'sftp-only'], true)
        || ($strategy !== 'releases' && (!$publicPath || !preg_match('~^/[A-Za-z0-9._/-]+$~', $publicPath)))) {
        fail('Utilisation : aml deploy:configure <profil> --host <hôte> --user <utilisateur> --path </chemin> [--strategy releases|public-html|sftp-only] [--public-path </public_html>].');
    }
    $profiles = deployProfiles();
    $profiles[$name] = ['host' => $host, 'user' => $user, 'path' => rtrim($path, '/'), 'public_path' => $publicPath ? rtrim($publicPath, '/') : null, 'strategy' => $strategy, 'port' => (int) $port, 'key' => $key];
    $config = deployConfigPath();
    if (!is_dir(dirname($config))) mkdir(dirname($config), 0700, true);
    file_put_contents($config, json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    @chmod($config, 0600);
    output("✓ Profil de déploiement configuré : {$name}");
}

function deployReleaseActivationCommand(array $profile, string $directory, string $release): string
{
    $current = rtrim((string) $profile['path'], '/') . '/current';
    $backup = rtrim((string) $profile['path'], '/') . '/current.pre-aml-' . $release;

    return 'if [ -e ' . escapeshellarg($current) . ' ] && [ ! -L ' . escapeshellarg($current) . ' ]; then '
        . 'mv ' . escapeshellarg($current) . ' ' . escapeshellarg($backup) . '; fi'
        . ' && ln -sfn ' . escapeshellarg($directory) . ' ' . escapeshellarg($current);
}

function deployPublicRootCommand(string $index, string $projectRoot): string
{
    $script = <<<'PHP'
$file = $argv[1];
$root = $argv[2];
$code = file_get_contents($file);
if (!is_string($code)) exit(1);
$replacement = '$root = ' . var_export($root, true) . ';';
$updated = preg_replace('/\$root\s*=\s*dirname\(__DIR__\);/', $replacement, $code, 1, $count);
if (!is_string($updated) || $count !== 1 || file_put_contents($file, $updated) === false) exit(1);
PHP;

    return 'php -r ' . escapeshellarg($script) . ' ' . escapeshellarg($index) . ' ' . escapeshellarg($projectRoot);
}

function deployRewritePublicRoot(string $index, string $projectRoot): bool
{
    $code = @file_get_contents($index);
    if (!is_string($code)) return false;
    $replacement = '$root = ' . var_export($projectRoot, true) . ';';
    $updated = preg_replace('/\$root\s*=\s*dirname\(__DIR__\);/', $replacement, $code, 1, $count);
    return is_string($updated) && $count === 1 && file_put_contents($index, $updated) !== false;
}

/** @return array{files: list<string>, hashes: array<string, string>} */
function deployManifest(string $manifest): array
{
    $data = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;
    $files = is_array($data) ? ($data['files'] ?? null) : null;
    if (!is_array($files)) throw new RuntimeException('Le manifeste de build est invalide.');
    $validated = [];
    foreach ($files as $file) {
        if (!is_string($file) || $file === '' || str_starts_with($file, '/') || in_array('..', explode('/', $file), true) || preg_match('/[\x00-\x1F\x7F]/', $file)) {
            throw new RuntimeException('Le manifeste contient un chemin non sécurisé.');
        }
        $validated[] = $file;
    }
    $validated = array_values(array_unique($validated));
    $rawHashes = is_array($data['hashes'] ?? null) ? $data['hashes'] : [];
    $hashes = [];
    foreach ($validated as $file) {
        $hash = $rawHashes[$file] ?? null;
        if (is_string($hash) && preg_match('/^[a-f0-9]{64}$/i', $hash) === 1) {
            $hashes[$file] = strtolower($hash);
        }
    }
    return ['files' => $validated, 'hashes' => $hashes];
}

/** @return list<string> */
function deployManifestFiles(string $manifest): array
{
    return deployManifest($manifest)['files'];
}

/**
 * @param array{files?: list<string>, hashes?: array<string, string>} $old
 * @return array{added: list<string>, modified: list<string>, removed: list<string>, unchanged: list<string>, transfer: list<string>}
 */
function deployManifestChanges(array $new, array $old): array
{
    $newFiles = array_values($new['files'] ?? []);
    $oldFiles = array_values($old['files'] ?? []);
    $newHashes = $new['hashes'] ?? [];
    $oldHashes = $old['hashes'] ?? [];
    $added = $modified = $unchanged = [];
    foreach ($newFiles as $file) {
        if (!in_array($file, $oldFiles, true)) {
            $added[] = $file;
        } elseif (isset($newHashes[$file], $oldHashes[$file]) && hash_equals($oldHashes[$file], $newHashes[$file])) {
            $unchanged[] = $file;
        } else {
            $modified[] = $file;
        }
    }
    $removed = array_values(array_diff($oldFiles, $newFiles));
    return [
        'added' => $added,
        'modified' => $modified,
        'removed' => $removed,
        'unchanged' => $unchanged,
        'transfer' => [...$added, ...$modified],
    ];
}

function deploySftpQuote(string $path): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $path) . '"';
}

/** @param list<string> $files @return array{public: list<string>, private: list<string>} */
function deployPartitionFiles(array $files): array
{
    $public = []; $private = [];
    foreach ($files as $file) {
        if (str_starts_with($file, 'public/')) $public[] = $file;
        else $private[] = $file;
    }
    return ['public' => $public, 'private' => $private];
}

function deployManifestSyncCommand(string $directory, string $privateRoot, string $publicRoot): string
{
    $script = <<<'PHP'
$source = $argv[1]; $private = $argv[2]; $public = $argv[3];
$manifestPath = $source . '/build-manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true);
if (!is_array($manifest) || !is_array($manifest['files'] ?? null)) exit(10);
$files = array_values(array_filter($manifest['files'], 'is_string'));
$hashes = is_array($manifest['hashes'] ?? null) ? $manifest['hashes'] : [];
$oldPath = $private . '/.phpaml-deploy-manifest.json';
$old = is_file($oldPath) ? json_decode((string) file_get_contents($oldPath), true) : [];
$oldFiles = is_array($old) && is_array($old['files'] ?? null) ? $old['files'] : [];
$oldHashes = is_array($old) && is_array($old['hashes'] ?? null) ? $old['hashes'] : [];
$changed = array_values(array_filter($files, static fn (string $file): bool =>
    !isset($hashes[$file], $oldHashes[$file]) || !hash_equals((string) $oldHashes[$file], (string) $hashes[$file])
));
$target = static function (string $file) use ($private, $public): string {
    return str_starts_with($file, 'public/') ? $public . '/' . substr($file, 7) : $private . '/' . $file;
};
foreach ($changed as $file) {
    if ($file === '' || str_starts_with($file, '/') || in_array('..', explode('/', $file), true)) exit(12);
    $from = $source . '/' . $file; $to = $target($file) . '.phpaml-next';
    if (!is_file($from)) exit(13);
    if (!is_dir(dirname($to))) mkdir(dirname($to), 0755, true);
    if (!copy($from, $to) || hash_file('sha256', $from) !== hash_file('sha256', $to)) exit(14);
}
if (in_array('public/index.php', $changed, true)) {
    $index = $public . '/index.php.phpaml-next';
    $code = file_get_contents($index);
    if (!is_string($code)) exit(15);
    $replacement = '$root = ' . var_export($private, true) . ';';
    $updated = preg_replace('/\$root\s*=\s*dirname\(__DIR__\);/', $replacement, $code, 1, $count);
    if (!is_string($updated) || $count !== 1 || file_put_contents($index, $updated) === false) exit(16);
}
foreach ($changed as $file) {
    $next = $target($file) . '.phpaml-next'; $to = $target($file);
    if (!rename($next, $to)) exit(19);
}
foreach (array_diff($oldFiles, $files) as $obsolete) {
    if (!is_string($obsolete) || $obsolete === '' || str_starts_with($obsolete, '/') || in_array('..', explode('/', $obsolete), true)) exit(17);
    $path = $target($obsolete);
    if (is_file($path) || is_link($path)) unlink($path);
}
if (!is_dir($private)) mkdir($private, 0755, true);
$nextManifest = $oldPath . '.next';
if (file_put_contents($nextManifest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL) === false || !rename($nextManifest, $oldPath)) exit(18);
PHP;
    return 'php -r ' . escapeshellarg($script) . ' ' . escapeshellarg($directory) . ' ' . escapeshellarg($privateRoot) . ' ' . escapeshellarg($publicRoot);
}

function deployRemoveDirectory(string $directory): void
{
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    @rmdir($directory);
}

function deployVerifyBuildIntegrity(string $archive, string $checksumFile): void
{
    if (!is_file($archive) || !is_file($checksumFile)) throw new RuntimeException('Le build ou son checksum SHA-256 est absent.');
    $line = trim((string) file_get_contents($checksumFile));
    $expected = strtolower((string) strtok($line, " \t"));
    $actual = hash_file('sha256', $archive);
    if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !is_string($actual) || !hash_equals($expected, strtolower($actual))) {
        throw new RuntimeException("L'intégrité du build est invalide. Relancez 'aml build'.");
    }
}

/** @param array{files?: list<string>, hashes?: array<string, string>} $oldManifest @return list<string> */
function deploySftpCommands(string $staging, array $profile, array $oldManifest = []): array
{
    $manifest = deployManifest($staging . '/build-manifest.json');
    $files = $manifest['files'];
    $changes = deployManifestChanges($manifest, $oldManifest);
    $transfer = $changes['transfer'];
    $privateRoot = rtrim((string) $profile['path'], '/');
    $publicRoot = rtrim((string) $profile['public_path'], '/');
    $remote = static fn (string $file): string => str_starts_with($file, 'public/')
        ? $publicRoot . '/' . substr($file, 7)
        : $privateRoot . '/' . $file;
    $commands = [];
    $rootDirectories = [];
    foreach ([$privateRoot, $publicRoot] as $root) {
        $prefix = str_starts_with($root, '/') ? '/' : '';
        foreach (array_values(array_filter(explode('/', trim($root, '/')), 'strlen')) as $segment) {
            $prefix = rtrim($prefix, '/') . '/' . $segment;
            $rootDirectories[$prefix] = true;
        }
    }
    foreach (array_keys($rootDirectories) as $directory) {
        $commands[] = '-mkdir ' . deploySftpQuote($directory);
    }
    $obsolete = $changes['removed'];
    $directories = [];
    foreach ($transfer as $file) {
        $target = $remote($file);
        $root = str_starts_with($file, 'public/') ? $publicRoot : $privateRoot;
        $directory = dirname($target);
        while ($directory !== $root && str_starts_with($directory . '/', $root . '/')) {
            $directories[$directory] = true;
            $directory = dirname($directory);
        }
    }
    $directories = array_keys($directories);
    usort($directories, static fn (string $a, string $b): int => substr_count($a, '/') <=> substr_count($b, '/'));
    foreach ($directories as $directory) $commands[] = '-mkdir ' . deploySftpQuote($directory);
    foreach ($transfer as $file) $commands[] = 'put ' . deploySftpQuote($staging . '/' . $file) . ' ' . deploySftpQuote($remote($file));
    $temporaryManifest = $privateRoot . '/.phpaml-deploy-manifest.next.json';
    $activeManifest = $privateRoot . '/.phpaml-deploy-manifest.json';
    $commands[] = 'put ' . deploySftpQuote($staging . '/build-manifest.json') . ' ' . deploySftpQuote($temporaryManifest);
    foreach ($obsolete as $file) {
        if (!is_string($file) || $file === '' || str_starts_with($file, '/') || in_array('..', explode('/', $file), true)) continue;
        $commands[] = '-rm ' . deploySftpQuote($remote($file));
    }
    $commands[] = '-rm ' . deploySftpQuote($activeManifest);
    $commands[] = 'rename ' . deploySftpQuote($temporaryManifest) . ' ' . deploySftpQuote($activeManifest);
    $commands[] = 'quit';
    return $commands;
}

/** @return list<string> */
function deploySshArguments(array $profile, bool $batch = false): array
{
    $arguments = ['ssh', '-p', (string) $profile['port']];
    if ($batch) array_push($arguments, '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10');
    if (is_string($profile['key'] ?? null) && $profile['key'] !== '') array_push($arguments, '-i', $profile['key']);
    $arguments[] = $profile['user'] . '@' . $profile['host'];
    return $arguments;
}

function deployRun(array $command): int
{
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    return is_resource($process) ? proc_close($process) : 1;
}

/** @return array{code: int, output: string} */
function deployCapture(array $command): array
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) return ['code' => 1, 'output' => ''];
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'output' => (string) $output . (string) $error];
}

/** @param array{added: list<string>, modified: list<string>, removed: list<string>, unchanged: list<string>, transfer: list<string>} $changes */
function deployOutputChanges(array $changes): void
{
    output(sprintf(
        'Δ Déploiement : %d ajouté(s), %d modifié(s), %d supprimé(s), %d inchangé(s).',
        count($changes['added']), count($changes['modified']), count($changes['removed']), count($changes['unchanged'])
    ));
    if ($changes['transfer'] === [] && $changes['removed'] === []) output('✓ Aucun fichier à transférer.');
}

/** @return array{files: list<string>, hashes: array<string, string>} */
function deployRemoteManifest(array $profile, string $path): array
{
    $capture = deployCapture([...deploySshArguments($profile, true), 'cat ' . escapeshellarg($path) . ' 2>/dev/null || true']);
    if ($capture['code'] !== 0 || trim($capture['output']) === '') return [];
    $temporary = tempnam(sys_get_temp_dir(), 'aml-deploy-manifest-');
    if (!is_string($temporary)) return [];
    try {
        file_put_contents($temporary, $capture['output'], LOCK_EX);
        return deployManifest($temporary);
    } catch (Throwable) {
        return [];
    } finally {
        @unlink($temporary);
    }
}

function deployExtractBuild(string $archive): string
{
    $staging = sys_get_temp_dir() . '/phpaml-deploy-' . bin2hex(random_bytes(5));
    if (!mkdir($staging, 0700, true) && !is_dir($staging)) throw new RuntimeException('Impossible de préparer le build différentiel.');
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true || !$zip->extractTo($staging)) {
        $zip->close(); deployRemoveDirectory($staging);
        throw new RuntimeException('Impossible d’extraire le build différentiel.');
    }
    $zip->close();
    return $staging;
}

/** @param list<string> $files */
function deployCreateDeltaArchive(string $staging, array $files, string $destination): void
{
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de créer l’archive différentielle.');
    }
    foreach ([...$files, 'build-manifest.json'] as $file) {
        $source = $staging . '/' . $file;
        if (!is_file($source) || !$zip->addFile($source, $file)) {
            $zip->close(); @unlink($destination);
            throw new RuntimeException("Impossible d’ajouter {$file} au déploiement différentiel.");
        }
    }
    if (!$zip->close()) throw new RuntimeException('Impossible de finaliser l’archive différentielle.');
}

function deployVerifyReleaseCommand(string $directory): string
{
    $script = <<<'PHP'
$root = $argv[1];
$manifest = json_decode((string) file_get_contents($root . '/build-manifest.json'), true);
if (!is_array($manifest) || !is_array($manifest['files'] ?? null) || !is_array($manifest['hashes'] ?? null)) exit(20);
foreach ($manifest['files'] as $file) {
    if (!is_string($file) || !isset($manifest['hashes'][$file]) || !is_file($root . '/' . $file)) exit(21);
    $actual = hash_file('sha256', $root . '/' . $file);
    if (!is_string($actual) || !hash_equals(strtolower((string) $manifest['hashes'][$file]), strtolower($actual))) exit(22);
}
PHP;
    return 'php -r ' . escapeshellarg($script) . ' ' . escapeshellarg($directory);
}

function deployCheck(string $name): never
{
    $profile = deployProfile($name);
    if (($profile['strategy'] ?? 'releases') === 'sftp-only') {
        $batch = tempnam(sys_get_temp_dir(), 'aml-sftp-check-');
        file_put_contents($batch, "pwd\nquit\n");
        $command = ['sftp', '-b', $batch, '-P', (string) $profile['port'], ...(($profile['key'] ?? '') !== '' ? ['-i', $profile['key']] : []), $profile['user'] . '@' . $profile['host']];
        $code = deployRun($command); @unlink($batch);
        if ($code !== 0) fail('Connexion SFTP impossible.', $code);
        output("✓ Connexion SFTP validée : {$name}"); exit(0);
    }
    $command = [...deploySshArguments($profile, true), 'printf PHPAML_DEPLOY_OK'];
    $code = deployRun($command);
    if ($code !== 0) fail('Connexion SSH impossible.', $code);
    output(); output("✓ Connexion SSH validée : {$name}");
    exit(0);
}

function deployShell(string $name, bool $sftp = false): never
{
    $profile = deployProfile($name);
    $command = $sftp
        ? ['sftp', '-P', (string) $profile['port'], ...(($profile['key'] ?? '') !== '' ? ['-i', $profile['key']] : []), $profile['user'] . '@' . $profile['host']]
        : deploySshArguments($profile);
    exit(deployRun($command));
}

function deployProject(string $name, bool $skipBuild = false): never
{
    $root = projectRoot();
    if (!$skipBuild || !is_file($root . '/output/phpaml-build.zip')) {
        $command = [PHP_BINARY, PHPAML_FRAMEWORK_ROOT . '/runtime/bin/aml.php', 'build'];
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
        if (!is_resource($process) || proc_close($process) !== 0) fail('Le build de production a échoué.');
    }
    try {
        deployVerifyBuildIntegrity($root . '/output/phpaml-build.zip', $root . '/output/phpaml-build.zip.sha256');
    } catch (RuntimeException $error) {
        fail($error->getMessage());
    }
    $profile = deployProfile($name);
    if (($profile['strategy'] ?? 'releases') === 'sftp-only') {
        deploySftpOnly($root, $name, $profile);
    }
    $staging = null;
    try {
        $staging = deployExtractBuild($root . '/output/phpaml-build.zip');
        $manifest = deployManifest($staging . '/build-manifest.json');
        $strategy = (string) ($profile['strategy'] ?? 'releases');
        $remoteManifestPath = $strategy === 'public-html'
            ? rtrim((string) $profile['path'], '/') . '/.phpaml-deploy-manifest.json'
            : rtrim((string) $profile['path'], '/') . '/current/build-manifest.json';
        $previousManifest = deployRemoteManifest($profile, $remoteManifestPath);
        $changes = deployManifestChanges($manifest, $previousManifest);
        deployOutputChanges($changes);

        if ($changes['transfer'] === [] && $changes['removed'] === []) {
            output("✓ Production déjà à jour : {$name}");
            deployRemoveDirectory($staging); $staging = null;
            exit(0);
        }

        $release = gmdate('Ymd-His');
        $releasesRoot = rtrim((string) $profile['path'], '/') . '/releases';
        $directory = $releasesRoot . '/' . $release;
        $remoteArchive = $releasesRoot . '/' . $release . '.delta.zip';
        $deltaArchive = $staging . '/.phpaml-delta.zip';
        deployCreateDeltaArchive($staging, $changes['transfer'], $deltaArchive);

        $prepare = 'mkdir -p ' . escapeshellarg($releasesRoot);
        if ($strategy === 'releases' && $previousManifest !== []) {
            $prepare .= ' && mkdir -p ' . escapeshellarg($directory)
                . ' && cp -aL ' . escapeshellarg(rtrim((string) $profile['path'], '/') . '/current/.') . ' ' . escapeshellarg($directory . '/');
        } else {
            $prepare .= ' && mkdir -p ' . escapeshellarg($directory);
        }
        if (deployRun([...deploySshArguments($profile, true), $prepare]) !== 0) throw new RuntimeException('Préparation distante impossible.');

        $scp = ['scp', '-P', (string) $profile['port'], ...(($profile['key'] ?? '') !== '' ? ['-i', $profile['key']] : []), $deltaArchive, $profile['user'] . '@' . $profile['host'] . ':' . $remoteArchive];
        if (deployRun($scp) !== 0) throw new RuntimeException('Transfert différentiel SCP impossible.');

        $activate = 'unzip -q -o ' . escapeshellarg($remoteArchive) . ' -d ' . escapeshellarg($directory);
        if ($strategy === 'public-html') {
            $public = (string) $profile['public_path'];
            $activate .= ' && mkdir -p ' . escapeshellarg($public) . ' ' . escapeshellarg((string) $profile['path'])
                . ' && ' . deployManifestSyncCommand($directory, (string) $profile['path'], $public);
        } else {
            foreach ($changes['removed'] as $removed) {
                $activate .= ' && rm -f -- ' . escapeshellarg($directory . '/' . $removed);
            }
            $activate .= ' && test -f ' . escapeshellarg($directory . '/public/index.php')
                . ' && ' . deployVerifyReleaseCommand($directory)
                . ' && ' . deployReleaseActivationCommand($profile, $directory, $release);
        }
        $activate .= ' && rm -f ' . escapeshellarg($remoteArchive);
        if (deployRun([...deploySshArguments($profile, true), $activate]) !== 0) throw new RuntimeException('Activation distante impossible.');
        output("✓ Release différentielle déployée : {$release}");
        output('Document root distant : ' . ($strategy === 'public-html' ? $profile['public_path'] : $profile['path'] . '/current/public'));
        deployRemoveDirectory($staging); $staging = null;
        exit(0);
    } catch (Throwable $error) {
        if (is_string($staging)) deployRemoveDirectory($staging);
        fail($error->getMessage());
    } finally {
        if (is_string($staging)) deployRemoveDirectory($staging);
    }
}

function deploySftpOnly(string $root, string $name, array $profile): never
{
    $staging = sys_get_temp_dir() . '/phpaml-sftp-' . bin2hex(random_bytes(5));
    $error = null;
    try {
        if (!mkdir($staging, 0700, true) && !is_dir($staging)) throw new RuntimeException('Impossible de créer le dossier temporaire SFTP.');
        $zip = new ZipArchive();
        if ($zip->open($root . '/output/phpaml-build.zip') !== true || !$zip->extractTo($staging)) throw new RuntimeException('Impossible de préparer le transfert SFTP.');
        $zip->close();
        if (!deployRewritePublicRoot($staging . '/public/index.php', (string) $profile['path'])) throw new RuntimeException('Impossible d’adapter public/index.php au chemin distant.');
        $target = $profile['user'] . '@' . $profile['host'];
        $connection = ['-P', (string) $profile['port'], ...(($profile['key'] ?? '') !== '' ? ['-i', $profile['key']] : []), $target];
        $oldManifest = $staging . '/old-manifest.json';
        $fetchBatch = $staging . '/fetch.sftp';
        file_put_contents($fetchBatch, '-get ' . deploySftpQuote($profile['path'] . '/.phpaml-deploy-manifest.json') . ' ' . deploySftpQuote($oldManifest) . "\nquit\n");
        deployRun(['sftp', '-b', $fetchBatch, ...$connection]);
        $previousManifest = is_file($oldManifest) ? deployManifest($oldManifest) : [];
        $changes = deployManifestChanges(deployManifest($staging . '/build-manifest.json'), $previousManifest);
        deployOutputChanges($changes);
        $batch = $staging . '/deploy.sftp';
        file_put_contents($batch, implode("\n", deploySftpCommands($staging, $profile, $previousManifest)) . "\n");
        if (deployRun(['sftp', '-b', $batch, ...$connection]) !== 0) throw new RuntimeException('Déploiement SFTP impossible.');
    } catch (Throwable $exception) {
        $error = $exception;
    } finally {
        deployRemoveDirectory($staging);
    }
    if ($error instanceof Throwable) fail($error->getMessage());
    output("✓ Déploiement SFTP terminé : {$name}"); output('Document root distant : ' . $profile['public_path']); exit(0);
}

function deployRollback(string $name): never
{
    $profile = deployProfile($name);
    if (($profile['strategy'] ?? 'releases') !== 'releases') fail('Le rollback atomique exige la stratégie releases.');
    $releases = $profile['path'] . '/releases';
    $command = 'current=$(readlink ' . escapeshellarg($profile['path'] . '/current') . ' 2>/dev/null || true); '
        . 'previous=$(find ' . escapeshellarg($releases) . ' -mindepth 1 -maxdepth 1 -type d -print | sort -r | while read release; do [ "$release" != "$current" ] && { printf "%s" "$release"; break; }; done); '
        . '[ -n "$previous" ] && test -f "$previous/public/index.php" && ln -sfn "$previous" ' . escapeshellarg($profile['path'] . '/current');
    if (deployRun([...deploySshArguments($profile, true), $command]) !== 0) fail('Aucune release précédente ne peut être activée.');
    output("✓ Rollback terminé : {$name}");
    exit(0);
}

function deployPruneCommand(array $profile, int $keep): string
{
    $releases = rtrim((string) $profile['path'], '/') . '/releases';
    $current = rtrim((string) $profile['path'], '/') . '/current';
    return 'current=$(readlink ' . escapeshellarg($current) . ' 2>/dev/null || true); count=0; '
        . 'find ' . escapeshellarg($releases) . ' -mindepth 1 -maxdepth 1 -type d -print 2>/dev/null | sort -r | while IFS= read -r release; do '
        . 'count=$((count+1)); if [ "$count" -gt ' . $keep . ' ] && [ "$release" != "$current" ]; then rm -rf -- "$release"; fi; done; '
        . 'find ' . escapeshellarg($releases) . ' -mindepth 1 -maxdepth 1 -type f -name "*.zip" -delete 2>/dev/null || true';
}

function deployPrune(string $name, int $keep): never
{
    if ($keep < 1 || $keep > 100) fail('--keep doit être compris entre 1 et 100.');
    $profile = deployProfile($name);
    if (($profile['strategy'] ?? 'releases') === 'sftp-only') fail('Le nettoyage automatique des releases exige un accès SSH.');
    if (deployRun([...deploySshArguments($profile, true), deployPruneCommand($profile, $keep)]) !== 0) fail('Nettoyage distant impossible.');
    output("✓ Anciennes releases supprimées; {$keep} conservée(s).");
    exit(0);
}
