#!/usr/bin/env php
<?php

declare(strict_types=1);

define('PHPAML_FRAMEWORK_ROOT', dirname(__DIR__, 2));

function output(string $message = ''): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "Erreur : {$message}" . PHP_EOL);
    exit($code);
}

function projectRoot(): string
{
    $current = getcwd() ?: PHPAML_FRAMEWORK_ROOT;
    if (is_file($current . '/info.json') && is_file($current . '/index.php')) {
        return $current;
    }
    fail("Le dossier courant n'est pas un projet PHPAML. Utilisez 'aml create .'.");
}

function loadProjectConfig(): array
{
    $root = projectRoot();
    bootstrapProject($root);
    \PHPAML\Config\Env::load($root . '/.env');
    return require $root . '/configs/app.php';
}

function bootstrapProject(string $root): void
{
    $moduleAutoloader = $root . '/aml_env/autoload.php';
    if (is_file($moduleAutoloader)) {
        require_once $moduleAutoloader;
        return;
    }
    $frameworkAutoloader = $root . '/aml_env/framework/Autoloader.php';
    if (!is_file($frameworkAutoloader)) {
        fail("L'environnement AML est absent. Exécutez 'aml install'.");
    }
    require_once $frameworkAutoloader;
    \PHPAML\Autoloader::register(['PHPAML\\' => $root . '/aml_env/framework', 'App\\' => $root . '/app']);
}

function projectInfo(?string $root = null): array
{
    $path = ($root ?? projectRoot()) . '/info.json';
    $content = is_file($path) ? file_get_contents($path) : false;
    $info = json_decode($content ?: '', true);
    if (!is_array($info)) {
        fail("Le fichier '{$path}' est introuvable ou invalide.");
    }
    return $info;
}

function showHelp(): void
{
    output('AML — interface en ligne de commande de PHPAML');
    output();
    output('Utilisation : aml <commande> [options]');
    output();
    output('Commandes :');
    output('  create <dossier>          Crée une application (utilisez . pour le dossier courant)');
    output('  serve [hôte:port]         Lance le serveur de développement');
    output('  install [options]         Installe moteur et dépendances dans aml_env');
    output('  update [options]          Met à jour l’environnement AML');
    output('  doctor [options]          Vérifie l’installation et le projet courant');
    output('  routes                    Affiche les routes de l’application');
    output('  make:controller <nom>     Génère un contrôleur');
    output('  make:model <nom>          Génère un modèle');
    output('  make:middleware <nom>     Génère un middleware');
    output('  make:migration <nom>      Génère une migration');
    output('  migrate                   Exécute les migrations en attente');
    output('  cache:clear               Vide le cache de l’application');
    output('  run <script>              Exécute un script déclaré dans info.json');
    output('  test                      Exécute les tests automatisés');
    output('  version                   Affiche la version du framework');
    output('  help                      Affiche cette aide');
    output();
    output('Exemples :');
    output('  aml create .');
    output('  aml create mon-projet');
    output('  aml create mon-projet --version 0.1.0');
    output('  aml create mon-projet --offline');
    output('  aml serve 127.0.0.1:8080');
    output('  aml install');
    output('  aml install --version 0.1.0');
    output('  aml update --check');
    output('  aml doctor');
    output('  aml doctor --port 8080');
}

/** @return list<string> */
function scaffoldFiles(): array
{
    $files = ['index.php', 'info.json', 'readme', '.htaccess', '.gitignore'];
    $files[] = '.env.example';
    $files[] = 'composer.json';
    $files[] = 'phpstan.neon';
    foreach (['app', 'configs', 'tests', 'database'] as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(PHPAML_FRAMEWORK_ROOT . '/' . $directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen(PHPAML_FRAMEWORK_ROOT) + 1);
            if (
                $file->isFile()
                && $file->getFilename() !== '.DS_Store'
                && (!str_starts_with($relative, 'storage/') || $file->getFilename() === '.gitkeep')
            ) {
                $files[] = $relative;
            }
        }
    }
    return $files;
}

function httpGet(string $url): string
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'AML-CLI/' . (projectInfo(PHPAML_FRAMEWORK_ROOT)['version'] ?? 'development'),
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
    ]);
    $content = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if (!is_string($content) || $status < 200 || $status >= 300) {
        fail("Téléchargement impossible ({$status}) : " . ($error ?: $url));
    }
    return $content;
}

/** @return array{version: string, package: string, checksum: string, package_name: string} */
function resolveCliRelease(?string $requestedVersion = null): array
{
    $endpoint = $requestedVersion === null
        ? 'https://api.github.com/repos/MR-C0DE/phpaml-cli/releases/latest'
        : 'https://api.github.com/repos/MR-C0DE/phpaml-cli/releases/tags/v' . ltrim($requestedVersion, 'v');
    $release = json_decode(httpGet($endpoint), true);
    if (!is_array($release) || !isset($release['tag_name'], $release['assets'])) {
        fail('La réponse de GitHub pour AML est invalide.');
    }

    $version = ltrim((string) $release['tag_name'], 'v');
    $machine = strtolower(php_uname('m'));
    $architecture = in_array($machine, ['x86_64', 'amd64'], true) ? 'x64'
        : (in_array($machine, ['arm64', 'aarch64'], true) ? 'arm64' : '');
    $packageName = match (PHP_OS_FAMILY) {
        'Windows' => $architecture === 'x64' ? "phpaml-{$version}-windows-x64.exe" : '',
        'Darwin' => $architecture === 'arm64' ? "phpaml-{$version}-macos-arm64.pkg" : '',
        'Linux' => $architecture === 'x64' ? "phpaml-{$version}-linux-x64.deb" : '',
        default => '',
    };
    if ($packageName === '') {
        fail('Aucune mise à jour automatique n’est disponible pour cette plateforme.');
    }

    $packageUrl = null;
    $checksumUrl = null;
    foreach ($release['assets'] as $asset) {
        if (($asset['name'] ?? null) === $packageName) {
            $packageUrl = $asset['browser_download_url'] ?? null;
        }
        if (($asset['name'] ?? null) === $packageName . '.sha256') {
            $checksumUrl = $asset['browser_download_url'] ?? null;
        }
    }
    if (!is_string($packageUrl) || !is_string($checksumUrl)) {
        fail("La release AML v{$version} ne contient pas le paquet attendu pour cette plateforme.");
    }
    return [
        'version' => $version,
        'package' => $packageUrl,
        'checksum' => $checksumUrl,
        'package_name' => $packageName,
    ];
}

function normalizedPath(string $path): string
{
    $resolved = realpath($path) ?: $path;
    return rtrim(strtolower(str_replace('\\', '/', $resolved)), '/');
}

function isNativeCliInstallation(): bool
{
    $root = normalizedPath(PHPAML_FRAMEWORK_ROOT);
    return match (PHP_OS_FAMILY) {
        'Windows' => ($local = getenv('LOCALAPPDATA')) !== false
            && $root === normalizedPath($local . '/Programs/PHPAML'),
        'Darwin' => $root === normalizedPath('/usr/local/lib/aml'),
        'Linux' => $root === normalizedPath('/opt/phpaml'),
        default => false,
    };
}

function privilegedCommand(string $command): string
{
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        return $command;
    }
    return 'sudo ' . $command;
}

function updateCli(?string $requestedVersion, bool $checkOnly, bool $force): never
{
    $currentVersion = (string) (projectInfo(PHPAML_FRAMEWORK_ROOT)['version'] ?? '0.0.0');
    $release = resolveCliRelease($requestedVersion);
    if (!$force && version_compare($release['version'], $currentVersion, '<=')) {
        output("AML est déjà à jour (v{$currentVersion}).");
        exit(0);
    }
    output("Mise à jour disponible : v{$currentVersion} → v{$release['version']}");
    if ($checkOnly) {
        exit(0);
    }

    $temporaryRoot = PHP_OS_FAMILY === 'Windows' ? sys_get_temp_dir() : '/tmp';
    $directory = rtrim($temporaryRoot, '/\\') . DIRECTORY_SEPARATOR
        . 'phpaml-update-' . bin2hex(random_bytes(6));
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        fail('Impossible de créer le dossier temporaire de mise à jour.');
    }
    $package = $directory . DIRECTORY_SEPARATOR . $release['package_name'];
    $checksum = $package . '.sha256';
    output("Téléchargement de PHPAML v{$release['version']}…");
    if (file_put_contents($package, httpGet($release['package'])) === false
        || file_put_contents($checksum, httpGet($release['checksum'])) === false) {
        fail('Impossible d’enregistrer la mise à jour.');
    }
    $expected = strtolower((string) strtok(trim((string) file_get_contents($checksum)), " \t"));
    $actual = hash_file('sha256', $package);
    if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, $actual)) {
        @unlink($package);
        @unlink($checksum);
        fail('Le checksum SHA-256 de la mise à jour AML est invalide.');
    }
    output('Intégrité de la mise à jour vérifiée.');

    if (!isNativeCliInstallation()) {
        output('Cette installation est portable : remplacement automatique désactivé.');
        output("Paquet vérifié disponible ici : {$package}");
        exit(0);
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'cmd.exe /c start "" ' . escapeshellarg($package)
            . ' /SILENT /SUPPRESSMSGBOXES /NORESTART /CLOSEAPPLICATIONS';
        pclose(popen($command, 'r'));
        output('L’installateur Windows a été lancé. Terminez l’installation pour appliquer la mise à jour.');
        exit(0);
    }

    $installCommand = PHP_OS_FAMILY === 'Darwin'
        ? privilegedCommand('installer -pkg ' . escapeshellarg($package) . ' -target /')
        : privilegedCommand('dpkg -i ' . escapeshellarg($package));
    passthru($installCommand, $exitCode);
    if ($exitCode !== 0) {
        fail('L’installation de la mise à jour a échoué.', $exitCode);
    }
    @unlink($package);
    @unlink($checksum);
    @rmdir($directory);
    output("AML v{$release['version']} a été installé avec succès.");
    exit(0);
}

/** @return array{version: string, archive: string, checksum: string} */
function resolveTemplateRelease(?string $requestedVersion): array
{
    $endpoint = $requestedVersion === null
        ? 'https://api.github.com/repos/MR-C0DE/phpaml-template/releases/latest'
        : 'https://api.github.com/repos/MR-C0DE/phpaml-template/releases/tags/v' . ltrim($requestedVersion, 'v');
    $release = json_decode(httpGet($endpoint), true);
    if (!is_array($release) || !isset($release['tag_name'], $release['assets'])) {
        fail('La réponse de GitHub pour le modèle est invalide.');
    }
    $version = ltrim((string) $release['tag_name'], 'v');
    $archiveName = "phpaml-template-{$version}.zip";
    $checksumName = $archiveName . '.sha256';
    $archiveUrl = null;
    $checksumUrl = null;
    foreach ($release['assets'] as $asset) {
        if (($asset['name'] ?? null) === $archiveName) {
            $archiveUrl = $asset['browser_download_url'] ?? null;
        }
        if (($asset['name'] ?? null) === $checksumName) {
            $checksumUrl = $asset['browser_download_url'] ?? null;
        }
    }
    if (!is_string($archiveUrl) || !is_string($checksumUrl)) {
        fail("La release v{$version} ne contient pas l'archive et le checksum attendus.");
    }
    return ['version' => $version, 'archive' => $archiveUrl, 'checksum' => $checksumUrl];
}

/** @return array{version: string, archive: string} */
function acquireTemplate(?string $version, bool $refresh, bool $offline): array
{
    $cacheRoot = PHPAML_FRAMEWORK_ROOT . '/aml_env/cache/templates';
    if ($offline) {
        if ($version === null) {
            $versions = array_filter(glob($cacheRoot . '/*') ?: [], 'is_dir');
            rsort($versions, SORT_NATURAL);
            $version = $versions === [] ? null : basename($versions[0]);
        }
        if ($version === null) {
            fail('Aucun modèle PHPAML ne se trouve dans le cache hors connexion.');
        }
        $archive = $cacheRoot . '/' . ltrim($version, 'v') . '/phpaml-template-' . ltrim($version, 'v') . '.zip';
        $checksumFile = $archive . '.sha256';
        if (!is_file($archive) || !is_file($checksumFile)) {
            fail("Le modèle v{$version} n'est pas disponible hors connexion.");
        }
        $expected = strtolower((string) strtok(trim((string) file_get_contents($checksumFile)), " \t"));
        $actual = hash_file('sha256', $archive);
        if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, $actual)) {
            fail('Le modèle présent dans le cache hors connexion est corrompu.');
        }
        return ['version' => ltrim($version, 'v'), 'archive' => $archive];
    }

    $release = resolveTemplateRelease($version);
    $directory = $cacheRoot . '/' . $release['version'];
    $archive = $directory . '/phpaml-template-' . $release['version'] . '.zip';
    $checksumFile = $archive . '.sha256';
    if ($refresh || !is_file($archive) || !is_file($checksumFile)) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($archive, httpGet($release['archive']));
        file_put_contents($checksumFile, httpGet($release['checksum']));
    }
    $checksumContent = trim((string) file_get_contents($checksumFile));
    $expected = strtolower((string) strtok($checksumContent, " \t"));
    $actual = hash_file('sha256', $archive);
    if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, $actual)) {
        fail('Le checksum SHA-256 du modèle PHPAML est invalide.');
    }
    return ['version' => $release['version'], 'archive' => $archive];
}

function createProject(
    string $destination,
    ?string $version = null,
    bool $refresh = false,
    bool $offline = false
): void
{
    $base = getcwd() ?: PHPAML_FRAMEWORK_ROOT;
    $target = $destination === '.' ? $base : $base . DIRECTORY_SEPARATOR . trim($destination, DIRECTORY_SEPARATOR);
    $projectName = $destination === '.' ? basename($target) : basename($destination);

    if ($projectName === '' || in_array($projectName, ['.', '..'], true)) {
        fail('Le nom du projet est invalide.');
    }

    $template = acquireTemplate($version, $refresh, $offline);
    $zip = new ZipArchive();
    if ($zip->open($template['archive']) !== true) {
        fail("Impossible d'ouvrir l'archive du modèle.");
    }

    $files = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        $parts = explode('/', $entry, 2);
        $relative = $parts[1] ?? '';
        if ($relative === '' || str_ends_with($relative, '/')) {
            continue;
        }
        if (str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
            $zip->close();
            fail('L’archive du modèle contient un chemin non sécurisé.');
        }
        $files[$relative] = $index;
    }
    $conflicts = [];
    foreach (array_keys($files) as $relative) {
        if (file_exists($target . '/' . $relative)) {
            $conflicts[] = $relative;
        }
    }
    if ($conflicts !== []) {
        fail('Création annulée pour éviter un écrasement : ' . implode(', ', array_slice($conflicts, 0, 5)));
    }

    if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
        fail("Impossible de créer '{$target}'.");
    }

    foreach ($files as $relative => $index) {
        $destinationPath = $target . '/' . $relative;
        $directory = dirname($destinationPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $content = $zip->getFromIndex($index);
        if (!is_string($content)) {
            $zip->close();
            fail("Impossible d'extraire '{$relative}'.");
        }
        if ($relative === 'info.json') {
            $info = json_decode($content, true);
            $info['name'] = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $projectName));
            $content = json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
        if ($relative === 'composer.json') {
            $composer = json_decode($content, true);
            $composer['name'] = 'phpaml/' . strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $projectName));
            $content = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
        file_put_contents($destinationPath, $content);
    }

    $zip->close();

    output("Application '{$projectName}' créée avec PHPAML v{$template['version']} dans {$target}");
    output($destination === '.'
        ? 'Lancez : aml install && aml serve'
        : "Lancez : cd {$destination} && aml install && aml serve");
}

function serve(string $address): never
{
    if (!preg_match('/^[a-zA-Z0-9.\-]+:\d{1,5}$/', $address)) {
        fail("Adresse invalide : {$address}");
    }
    $port = (int) substr($address, (int) strrpos($address, ':') + 1);
    if ($port < 1 || $port > 65535) {
        fail('Le port doit être compris entre 1 et 65535.');
    }
    $root = projectRoot();
    if (!is_file($root . '/aml_env/autoload.php')) {
        fail("Les dépendances sont absentes. Exécutez d'abord 'aml install'.");
    }
    output("PHPAML écoute sur http://{$address}");
    output('Utilisez Ctrl+C pour arrêter le serveur.');
    passthru(
        escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($address)
        . ' -t ' . escapeshellarg($root) . ' ' . escapeshellarg($root . '/index.php'),
        $exitCode
    );
    exit($exitCode);
}

function installModules(
    bool $production = false,
    ?string $version = null,
    bool $refresh = false,
    bool $offline = false
): never
{
    $root = projectRoot();
    if (!is_file($root . '/composer.json')) {
        fail('Le fichier composer.json est introuvable.');
    }
    $bundledComposer = PHPAML_FRAMEWORK_ROOT . '/runtime/composer/composer.phar';
    if (is_file($bundledComposer)) {
        $composer = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bundledComposer);
    } else {
        $systemComposer = trim((string) shell_exec('command -v composer 2>/dev/null'));
        if ($systemComposer === '') {
            fail("Composer privé est absent de l'installation AML.");
        }
        $composer = escapeshellarg($systemComposer);
    }
    $frameworkVersion = installFramework($root, $version, $refresh, $offline);
    output('Préparation de l’environnement aml_env/…');
    $command = 'cd ' . escapeshellarg($root)
        . ' && ' . $composer
        . ' install --no-interaction --prefer-dist'
        . ($production ? ' --no-dev --optimize-autoloader' : '');
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
    $manifest = [
        'installed_at' => date(DATE_ATOM),
        'mode' => $production ? 'production' : 'development',
        'framework' => $frameworkVersion,
        'lock' => is_file($root . '/composer.lock') ? hash_file('sha256', $root . '/composer.lock') : null,
    ];
    file_put_contents(
        $root . '/aml_env/aml-installed.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
    output('Environnement AML installé avec succès.');
    exit(0);
}

/** @return array{version: string, archive: string} */
function acquireFramework(?string $version, bool $refresh, bool $offline): array
{
    $cacheRoot = PHPAML_FRAMEWORK_ROOT . '/aml_env/cache/framework';
    if ($offline) {
        if ($version === null) {
            $versions = array_filter(glob($cacheRoot . '/*') ?: [], 'is_dir');
            rsort($versions, SORT_NATURAL);
            $version = $versions === [] ? null : basename($versions[0]);
        }
        if ($version === null) {
            fail('Aucun moteur PHPAML ne se trouve dans le cache hors connexion.');
        }
        $version = ltrim($version, 'v');
        $archive = "{$cacheRoot}/{$version}/phpaml-framework-{$version}.zip";
        $checksumFile = $archive . '.sha256';
        if (!is_file($archive) || !is_file($checksumFile)) {
            fail("Le moteur PHPAML v{$version} n'est pas disponible hors connexion.");
        }
        $expected = strtolower((string) strtok(trim((string) file_get_contents($checksumFile)), " \t"));
        $actual = hash_file('sha256', $archive);
        if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, $actual)) {
            fail('Le moteur PHPAML présent dans le cache est corrompu.');
        }
        return compact('version', 'archive');
    }

    $endpoint = $version === null
        ? 'https://api.github.com/repos/MR-C0DE/phpaml-framework/releases/latest'
        : 'https://api.github.com/repos/MR-C0DE/phpaml-framework/releases/tags/v' . ltrim($version, 'v');
    $release = json_decode(httpGet($endpoint), true);
    if (!is_array($release) || !isset($release['tag_name'], $release['assets'])) {
        fail('La réponse de GitHub pour le moteur PHPAML est invalide.');
    }
    $version = ltrim((string) $release['tag_name'], 'v');
    $archiveName = "phpaml-framework-{$version}.zip";
    $archiveUrl = null;
    $checksumUrl = null;
    foreach ($release['assets'] as $asset) {
        if (($asset['name'] ?? null) === $archiveName) {
            $archiveUrl = $asset['browser_download_url'] ?? null;
        }
        if (($asset['name'] ?? null) === $archiveName . '.sha256') {
            $checksumUrl = $asset['browser_download_url'] ?? null;
        }
    }
    if (!is_string($archiveUrl) || !is_string($checksumUrl)) {
        fail("La release du moteur v{$version} est incomplète.");
    }
    $directory = "{$cacheRoot}/{$version}";
    $archive = "{$directory}/{$archiveName}";
    $checksumFile = $archive . '.sha256';
    if ($refresh || !is_file($archive) || !is_file($checksumFile)) {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($archive, httpGet($archiveUrl));
        file_put_contents($checksumFile, httpGet($checksumUrl));
    }
    $expected = strtolower((string) strtok(trim((string) file_get_contents($checksumFile)), " \t"));
    $actual = hash_file('sha256', $archive);
    if (!preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, $actual)) {
        fail('Le checksum SHA-256 du moteur PHPAML est invalide.');
    }
    return compact('version', 'archive');
}

function installFramework(string $projectRoot, ?string $version, bool $refresh, bool $offline): string
{
    $framework = acquireFramework($version, $refresh, $offline);
    $destination = $projectRoot . '/aml_env/framework';
    $zip = new ZipArchive();
    if ($zip->open($framework['archive']) !== true) {
        fail("Impossible d'ouvrir l'archive du moteur PHPAML.");
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        $prefix = 'phpaml-framework/src/';
        if (!str_starts_with($entry, $prefix) || str_ends_with($entry, '/')) {
            continue;
        }
        $relative = substr($entry, strlen($prefix));
        if ($relative === '' || in_array('..', explode('/', $relative), true)) {
            $zip->close();
            fail('L’archive du moteur contient un chemin non sécurisé.');
        }
        $target = $destination . '/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        $content = $zip->getFromIndex($index);
        if (!is_string($content)) {
            $zip->close();
            fail("Impossible d'extraire '{$relative}'.");
        }
        file_put_contents($target, $content);
    }
    $zip->close();
    foreach ([$projectRoot . '/aml_env/storage', $projectRoot . '/aml_env/storage/cache'] as $runtimeDirectory) {
        if (!is_dir($runtimeDirectory)) {
            mkdir($runtimeDirectory, 0755, true);
        }
    }
    return $framework['version'];
}

function runScript(string $name): never
{
    $scripts = projectInfo()['scripts'] ?? [];
    if (!is_array($scripts) || !isset($scripts[$name]) || !is_string($scripts[$name])) {
        $available = is_array($scripts) ? implode(', ', array_keys($scripts)) : '';
        fail("Script '{$name}' inconnu." . ($available ? " Scripts disponibles : {$available}." : ''));
    }
    output("> {$scripts[$name]}");
    passthru('cd ' . escapeshellarg(projectRoot()) . ' && ' . $scripts[$name], $exitCode);
    exit($exitCode);
}

function showRoutes(): void
{
    $config = loadProjectConfig();
    $routes = $config['routes'] ?? [];
    if ($routes === []) {
        output('Aucune route trouvée.');
        return;
    }
    output(str_pad('MÉTHODE', 12) . str_pad('ROUTE', 20) . 'ACTION');
    output(str_repeat('-', 64));
    foreach ($routes as $definition => $routeConfig) {
        $handler = isset($routeConfig['handler']) ? $routeConfig['handler'] : $routeConfig;
        [$method, $route] = array_pad(explode(' ', $definition, 2), 2, '');
        [$controller, $action] = $handler;
        output(str_pad($method, 12) . str_pad($route, 20) . $controller . '::' . $action);
    }
}

function className(string $name, string $suffix = ''): string
{
    $name = preg_replace('/[^a-zA-Z0-9]+/', ' ', $name) ?: '';
    $name = str_replace(' ', '', ucwords(trim($name)));
    if ($name === '' || ctype_digit($name[0])) {
        fail('Le nom de classe est invalide.');
    }
    return str_ends_with($name, $suffix) ? $name : $name . $suffix;
}

function generateClass(string $type, string $name): void
{
    $root = projectRoot();
    if ($type === 'controller') {
        $class = className($name, 'Controller');
        $path = "app/Controllers/{$class}.php";
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Controllers;\n\nuse PHPAML\\Http\\Request;\nuse PHPAML\\Http\\Response;\nuse PHPAML\\Mvc\\Controller;\n\nfinal class {$class} extends Controller\n{\n    public function index(Request \$request): Response\n    {\n        return \$this->json(['controller' => '{$class}']);\n    }\n}\n";
    } elseif ($type === 'model') {
        $class = className($name);
        $path = "app/Models/{$class}.php";
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\nfinal class {$class}\n{\n}\n";
    } else {
        $class = className($name, 'Middleware');
        $path = "app/Middleware/{$class}.php";
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Middleware;\n\nuse Closure;\nuse PHPAML\\Http\\Request;\nuse PHPAML\\Http\\Response;\nuse PHPAML\\Middleware\\MiddlewareInterface;\n\nfinal class {$class} implements MiddlewareInterface\n{\n    public function process(Request \$request, Closure \$next): Response\n    {\n        return \$next(\$request);\n    }\n}\n";
    }
    $fullPath = $root . '/' . $path;
    if (file_exists($fullPath)) {
        fail("Le fichier '{$path}' existe déjà.");
    }
    if (!is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }
    file_put_contents($fullPath, $content);
    output("Créé : {$path}");
}

function generateMigration(string $name): void
{
    $className = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
    if ($className === '') {
        fail('Le nom de migration est invalide.');
    }
    $relative = 'database/migrations/' . date('Ymd_His') . '_' . $className . '.php';
    $path = projectRoot() . '/' . $relative;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    $content = <<<'PHP'
<?php

declare(strict_types=1);

use PHPAML\Data\Connection;
use PHPAML\Data\Migration;

return new class extends Migration {
    public function up(Connection $connection): void
    {
        // $connection->pdo()->exec('CREATE TABLE example (id INTEGER PRIMARY KEY)');
    }

    public function down(Connection $connection): void
    {
        // $connection->pdo()->exec('DROP TABLE example');
    }
};
PHP;
    file_put_contents($path, $content . PHP_EOL);
    output("Créé : {$relative}");
}

function migrate(): void
{
    $root = projectRoot();
    bootstrapProject($root);
    \PHPAML\Config\Env::load($root . '/.env');
    $config = require $root . '/configs/app.php';
    $database = $config['database'];
    $connection = new \PHPAML\Data\Connection(
        (string) $database['dsn'],
        $database['username'] ?: null,
        $database['password'] ?: null
    );
    $completed = (new \PHPAML\Data\Migrator($connection, $root . '/database/migrations'))->migrate();
    output($completed === [] ? 'Aucune migration en attente.' : implode(PHP_EOL, array_map(static fn ($name) => "Migrée : {$name}", $completed)));
}

function clearCache(): void
{
    $directory = projectRoot() . '/aml_env/storage/cache';
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file) && basename($file) !== '.gitkeep') {
            unlink($file);
        }
    }
    output('Cache vidé.');
}

function runTests(): never
{
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(projectRoot() . '/tests/run.php'), $exitCode);
    exit($exitCode);
}

/** @param list<array{status: string, name: string, message: string}> $checks */
function doctorAdd(array &$checks, string $status, string $name, string $message): void
{
    $checks[] = compact('status', 'name', 'message');
}

/** @return array{status: int, message: string} */
function githubStatus(?string $version): array
{
    if (!extension_loaded('curl')) {
        return ['status' => 0, 'message' => 'extension curl absente'];
    }
    $handle = curl_init('https://api.github.com/repos/MR-C0DE/phpaml-cli/releases/latest');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'AML-Doctor/' . ($version ?? 'development'),
    ]);
    curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    return ['status' => $status, 'message' => $error];
}

function doctor(?string $requestedPort, bool $offline, bool $json): never
{
    $checks = [];
    $infoPath = PHPAML_FRAMEWORK_ROOT . '/info.json';
    $infoContent = is_file($infoPath) ? file_get_contents($infoPath) : false;
    $info = json_decode($infoContent ?: '', true);
    $version = is_array($info) && is_string($info['version'] ?? null) ? $info['version'] : null;
    doctorAdd(
        $checks,
        $version !== null ? 'ok' : 'error',
        'AML',
        $version !== null ? "version {$version}" : 'info.json absent ou invalide'
    );

    $phpSupported = version_compare(PHP_VERSION, '8.2.0', '>=');
    doctorAdd(
        $checks,
        $phpSupported ? 'ok' : 'error',
        'PHP',
        PHP_VERSION . ($phpSupported ? ' compatible' : ' — PHP 8.2 ou supérieur requis')
    );
    doctorAdd($checks, is_executable(PHP_BINARY) ? 'ok' : 'error', 'Runtime PHP', PHP_BINARY);

    $requiredExtensions = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'phar', 'tokenizer', 'zip'];
    $missingExtensions = array_values(array_filter(
        $requiredExtensions,
        static fn (string $extension): bool => !extension_loaded($extension)
    ));
    doctorAdd(
        $checks,
        $missingExtensions === [] ? 'ok' : 'error',
        'Extensions PHP',
        $missingExtensions === [] ? 'toutes présentes' : 'absentes : ' . implode(', ', $missingExtensions)
    );

    $composer = PHPAML_FRAMEWORK_ROOT . '/runtime/composer/composer.phar';
    doctorAdd(
        $checks,
        is_file($composer) && is_readable($composer) ? 'ok' : 'error',
        'Composer privé',
        is_file($composer) && is_readable($composer) ? 'disponible' : 'runtime/composer/composer.phar introuvable'
    );

    foreach (['aml_env/tmp' => 'Dossier temporaire', 'aml_env/cache' => 'Cache AML'] as $relative => $label) {
        $directory = PHPAML_FRAMEWORK_ROOT . '/' . $relative;
        $writable = is_dir($directory) && is_writable($directory);
        doctorAdd(
            $checks,
            $writable ? 'ok' : 'error',
            $label,
            $writable ? 'accessible en écriture' : "{$directory} doit être accessible en écriture"
        );
    }

    if ($offline) {
        doctorAdd($checks, 'info', 'GitHub', 'contrôle ignoré (--offline)');
    } else {
        $github = githubStatus($version);
        $connected = $github['status'] >= 200 && $github['status'] < 400;
        $rateLimited = $github['status'] === 403 || $github['status'] === 429;
        doctorAdd(
            $checks,
            $connected ? 'ok' : ($rateLimited ? 'warning' : 'error'),
            'GitHub',
            $connected ? "accessible (HTTP {$github['status']})"
                : ($rateLimited ? "accessible, limite API atteinte (HTTP {$github['status']})"
                    : ($github['message'] ?: "indisponible (HTTP {$github['status']})"))
        );
    }

    $port = $requestedPort === null ? 8000 : filter_var($requestedPort, FILTER_VALIDATE_INT);
    if ($port === false || $port < 1 || $port > 65535) {
        doctorAdd($checks, 'error', 'Port de développement', 'le port doit être compris entre 1 et 65535');
    } else {
        $errno = 0;
        $error = '';
        $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $error);
        doctorAdd(
            $checks,
            is_resource($socket) ? 'ok' : 'warning',
            'Port de développement',
            is_resource($socket) ? "127.0.0.1:{$port} disponible" : "127.0.0.1:{$port} occupé"
        );
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    $current = getcwd() ?: '';
    $isProject = is_file($current . '/info.json') && is_file($current . '/index.php');
    if (!$isProject) {
        doctorAdd($checks, 'info', 'Projet', 'aucun projet PHPAML dans le dossier courant');
    } else {
        $projectInfoContent = file_get_contents($current . '/info.json');
        $projectInfo = json_decode($projectInfoContent ?: '', true);
        doctorAdd($checks, is_array($projectInfo) ? 'ok' : 'error', 'Projet', $current);
        doctorAdd(
            $checks,
            is_file($current . '/configs/app.php') ? 'ok' : 'error',
            'Configuration',
            is_file($current . '/configs/app.php') ? 'configs/app.php présent' : 'configs/app.php absent'
        );
        doctorAdd(
            $checks,
            is_file($current . '/.env') ? 'ok' : 'warning',
            'Environnement',
            is_file($current . '/.env') ? '.env présent' : "créez .env à partir de .env.example"
        );
        $framework = $current . '/aml_env/framework/Autoloader.php';
        $autoload = $current . '/aml_env/autoload.php';
        $installed = is_file($framework) && is_file($autoload);
        doctorAdd(
            $checks,
            $installed ? 'ok' : 'error',
            'Moteur du projet',
            $installed ? 'installé dans aml_env' : "absent — exécutez 'aml install'"
        );
        $storage = $current . '/aml_env/storage';
        doctorAdd(
            $checks,
            is_dir($storage) && is_writable($storage) ? 'ok' : 'error',
            'Stockage du projet',
            is_dir($storage) && is_writable($storage) ? 'accessible en écriture' : 'aml_env/storage doit être accessible en écriture'
        );
    }

    $errors = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'error'));
    $warnings = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'warning'));
    if ($json) {
        output((string) json_encode(
            ['healthy' => $errors === 0, 'errors' => $errors, 'warnings' => $warnings, 'checks' => $checks],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    } else {
        output('Diagnostic PHPAML');
        output(str_repeat('─', 64));
        $symbols = ['ok' => 'OK', 'warning' => 'ATTENTION', 'error' => 'ERREUR', 'info' => 'INFO'];
        foreach ($checks as $check) {
            output(str_pad('[' . $symbols[$check['status']] . ']', 12) . str_pad($check['name'], 24) . $check['message']);
        }
        output(str_repeat('─', 64));
        output($errors === 0
            ? "Diagnostic réussi avec {$warnings} avertissement(s)."
            : "Diagnostic échoué : {$errors} erreur(s), {$warnings} avertissement(s).");
    }
    exit($errors === 0 ? 0 : 1);
}

$arguments = $_SERVER['argv'];
array_shift($arguments);
$command = $arguments[0] ?? 'help';

function optionValue(array $arguments, string $option): ?string
{
    $position = array_search($option, $arguments, true);
    if ($position === false) {
        return null;
    }
    if (!isset($arguments[$position + 1]) || str_starts_with($arguments[$position + 1], '--')) {
        fail("L'option '{$option}' nécessite une valeur.");
    }
    return $arguments[$position + 1];
}

switch ($command) {
    case 'create':
        $destination = isset($arguments[1]) && !str_starts_with($arguments[1], '--') ? $arguments[1] : '.';
        createProject(
            $destination,
            optionValue($arguments, '--version'),
            in_array('--refresh', $arguments, true),
            in_array('--offline', $arguments, true)
        );
        break;
    case 'serve':
        serve($arguments[1] ?? 'localhost:8000');
    case 'install':
        installModules(
            in_array('--production', $arguments, true),
            optionValue($arguments, '--version'),
            in_array('--refresh', $arguments, true),
            in_array('--offline', $arguments, true)
        );
    case 'update':
        updateCli(
            optionValue($arguments, '--version'),
            in_array('--check', $arguments, true),
            in_array('--force', $arguments, true)
        );
    case 'doctor':
        doctor(
            optionValue($arguments, '--port'),
            in_array('--offline', $arguments, true),
            in_array('--json', $arguments, true)
        );
    case 'run':
        isset($arguments[1]) ? runScript($arguments[1]) : fail('Indiquez le nom du script.');
    case 'routes':
        showRoutes();
        break;
    case 'make:controller':
    case 'make:model':
    case 'make:middleware':
        isset($arguments[1])
            ? generateClass(substr($command, 5), $arguments[1])
            : fail('Indiquez le nom de la classe à générer.');
        break;
    case 'make:migration':
        isset($arguments[1]) ? generateMigration($arguments[1]) : fail('Indiquez le nom de la migration.');
        break;
    case 'migrate':
        migrate();
        break;
    case 'cache:clear':
        clearCache();
        break;
    case 'test':
        runTests();
    case 'version':
    case '--version':
    case '-v':
        output((string) (projectInfo(PHPAML_FRAMEWORK_ROOT)['version'] ?? 'version inconnue'));
        break;
    case 'help':
    case '--help':
    case '-h':
        showHelp();
        break;
    default:
        fail("Commande inconnue : {$command}. Utilisez 'aml help'.");
}
