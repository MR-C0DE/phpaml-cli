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
    if (!$host || !$user || !$path || !preg_match('/^[A-Za-z0-9.-]+$/', $host)
        || !preg_match('/^[A-Za-z0-9._-]+$/', $user) || !str_starts_with($path, '/')
        || filter_var($port, FILTER_VALIDATE_INT) === false || (int) $port < 1 || (int) $port > 65535) {
        fail('Utilisation : aml deploy:configure <profil> --host <hôte> --user <utilisateur> --path </chemin> [--port 22] [--key <fichier>].');
    }
    $profiles = deployProfiles();
    $profiles[$name] = ['host' => $host, 'user' => $user, 'path' => rtrim($path, '/'), 'port' => (int) $port, 'key' => $key];
    $config = deployConfigPath();
    if (!is_dir(dirname($config))) mkdir(dirname($config), 0700, true);
    file_put_contents($config, json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    @chmod($config, 0600);
    output("✓ Profil de déploiement configuré : {$name}");
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
    $release = gmdate('Ymd-His');
    $remoteArchive = $profile['path'] . '/releases/' . $release . '.zip';
    $scp = ['scp', '-P', (string) $profile['port'], ...(($profile['key'] ?? '') !== '' ? ['-i', $profile['key']] : []), $root . '/output/phpaml-build.zip', $profile['user'] . '@' . $profile['host'] . ':' . $remoteArchive];
    $prepare = 'mkdir -p ' . escapeshellarg($profile['path'] . '/releases');
    if (deployRun([...deploySshArguments($profile, true), $prepare]) !== 0 || deployRun($scp) !== 0) fail('Transfert SFTP/SCP impossible.');
    $directory = $profile['path'] . '/releases/' . $release;
    $activate = 'mkdir -p ' . escapeshellarg($directory)
        . ' && unzip -q ' . escapeshellarg($remoteArchive) . ' -d ' . escapeshellarg($directory)
        . ' && test -f ' . escapeshellarg($directory . '/public/index.php')
        . ' && ln -sfn ' . escapeshellarg($directory) . ' ' . escapeshellarg($profile['path'] . '/current');
    if (deployRun([...deploySshArguments($profile, true), $activate]) !== 0) fail('Activation distante impossible.');
    output("✓ Release déployée : {$release}");
    output('Document root distant : ' . $profile['path'] . '/current/public');
    exit(0);
}

function deployRollback(string $name): never
{
    $profile = deployProfile($name);
    $releases = $profile['path'] . '/releases';
    $command = 'current=$(readlink ' . escapeshellarg($profile['path'] . '/current') . ' 2>/dev/null || true); '
        . 'previous=$(find ' . escapeshellarg($releases) . ' -mindepth 1 -maxdepth 1 -type d -print | sort -r | while read release; do [ "$release" != "$current" ] && { printf "%s" "$release"; break; }; done); '
        . '[ -n "$previous" ] && test -f "$previous/public/index.php" && ln -sfn "$previous" ' . escapeshellarg($profile['path'] . '/current');
    if (deployRun([...deploySshArguments($profile, true), $command]) !== 0) fail('Aucune release précédente ne peut être activée.');
    output("✓ Rollback terminé : {$name}");
    exit(0);
}
