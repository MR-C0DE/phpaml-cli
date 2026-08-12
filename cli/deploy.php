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
        $command = [PHP_BINARY, PHPAML_FRAMEWORK_ROOT . '/aml_env/bin/aml.php', 'build'];
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
        if (!is_resource($process) || proc_close($process) !== 0) fail('Le build de production a échoué.');
    }
    $profile = deployProfile($name);
    if (($profile['strategy'] ?? 'releases') === 'sftp-only') {
        deploySftpOnly($root, $name, $profile);
    }
    $release = gmdate('Ymd-His');
    $remoteArchive = $profile['path'] . '/releases/' . $release . '.zip';
    $scp = ['scp', '-P', (string) $profile['port'], ...(($profile['key'] ?? '') !== '' ? ['-i', $profile['key']] : []), $root . '/output/phpaml-build.zip', $profile['user'] . '@' . $profile['host'] . ':' . $remoteArchive];
    $prepare = 'mkdir -p ' . escapeshellarg($profile['path'] . '/releases');
    if (deployRun([...deploySshArguments($profile, true), $prepare]) !== 0 || deployRun($scp) !== 0) fail('Transfert SFTP/SCP impossible.');
    $directory = $profile['path'] . '/releases/' . $release;
    $activate = 'mkdir -p ' . escapeshellarg($directory)
        . ' && unzip -q ' . escapeshellarg($remoteArchive) . ' -d ' . escapeshellarg($directory)
        . ' && test -f ' . escapeshellarg($directory . '/public/index.php');
    if (($profile['strategy'] ?? 'releases') === 'public-html') {
        $public = (string) $profile['public_path'];
        $activate .= ' && mkdir -p ' . escapeshellarg($public)
            . ' && cp -a ' . escapeshellarg($directory . '/public/.') . ' ' . escapeshellarg($public . '/')
            . ' && for item in app configs database aml_env composer.json composer.lock info.json; do [ ! -e '
            . escapeshellarg($directory) . '/"$item" ] || cp -a ' . escapeshellarg($directory) . '/"$item" ' . escapeshellarg($profile['path'] . '/');
        $activate .= ' done && ' . deployPublicRootCommand($public . '/index.php', (string) $profile['path']);
    } else {
        $activate .= ' && ' . deployReleaseActivationCommand($profile, $directory, $release);
    }
    if (deployRun([...deploySshArguments($profile, true), $activate]) !== 0) fail('Activation distante impossible.');
    output("✓ Release déployée : {$release}");
    output('Document root distant : ' . (($profile['strategy'] ?? 'releases') === 'public-html' ? $profile['public_path'] : $profile['path'] . '/current/public'));
    exit(0);
}

function deploySftpOnly(string $root, string $name, array $profile): never
{
    $staging = sys_get_temp_dir() . '/phpaml-sftp-' . bin2hex(random_bytes(5));
    mkdir($staging, 0700, true);
    $zip = new ZipArchive();
    if ($zip->open($root . '/output/phpaml-build.zip') !== true || !$zip->extractTo($staging)) fail('Impossible de préparer le transfert SFTP.');
    $zip->close();
    if (!deployRewritePublicRoot($staging . '/public/index.php', (string) $profile['path'])) {
        fail('Impossible d’adapter public/index.php au chemin distant.');
    }
    $batch = $staging . '/deploy.sftp';
    $commands = [
        '-mkdir ' . $profile['path'], '-mkdir ' . $profile['public_path'],
        'put -r ' . $staging . '/app ' . $profile['path'] . '/',
        'put -r ' . $staging . '/configs ' . $profile['path'] . '/',
        'put -r ' . $staging . '/aml_env ' . $profile['path'] . '/',
        'put -r ' . $staging . '/public/* ' . $profile['public_path'] . '/',
        'put ' . $staging . '/public/.htaccess ' . $profile['public_path'] . '/.htaccess', 'quit',
    ];
    file_put_contents($batch, implode("\n", $commands) . "\n");
    $command = ['sftp', '-b', $batch, '-P', (string) $profile['port'], ...(($profile['key'] ?? '') !== '' ? ['-i', $profile['key']] : []), $profile['user'] . '@' . $profile['host']];
    if (deployRun($command) !== 0) fail('Déploiement SFTP impossible.');
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
