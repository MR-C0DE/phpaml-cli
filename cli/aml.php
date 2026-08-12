#!/usr/bin/env php
<?php

declare(strict_types=1);

define('PHPAML_FRAMEWORK_ROOT', dirname(__DIR__, 2));

function languageFile(): string
{
    $base = PHP_OS_FAMILY === 'Windows'
        ? (getenv('APPDATA') ?: getenv('USERPROFILE'))
        : getenv('HOME');
    return rtrim((string) ($base ?: sys_get_temp_dir()), '/\\') . '/.phpaml/language';
}

function currentLanguage(): string
{
    static $language = null;
    if (is_string($language)) {
        return $language;
    }
    $fromEnvironment = strtolower((string) (getenv('AML_LANG') ?: ''));
    if (in_array($fromEnvironment, ['en', 'fr'], true)) {
        return $language = $fromEnvironment;
    }
    $path = languageFile();
    if (is_file($path)) {
        $saved = strtolower(trim((string) file_get_contents($path)));
        if (in_array($saved, ['en', 'fr'], true)) {
            return $language = $saved;
        }
    }
    $arguments = $_SERVER['argv'] ?? [];
    $interactive = function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($interactive && (($arguments[1] ?? '') !== 'language')) {
        fwrite(STDOUT, "Choose your language / Choisissez votre langue:\n  1) English\n  2) Français\n> ");
        $answer = strtolower(trim((string) fgets(STDIN)));
        $language = in_array($answer, ['2', 'fr', 'français', 'francais'], true) ? 'fr' : 'en';
        saveLanguage($language, false);
        return $language;
    }
    return $language = 'en';
}

function saveLanguage(string $language, bool $announce = true): void
{
    $language = strtolower($language);
    if (!in_array($language, ['en', 'fr'], true)) {
        fwrite(STDERR, "Error / Erreur: supported languages are en and fr.\n");
        exit(1);
    }
    $path = languageFile();
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
        fwrite(STDERR, "Error / Erreur: unable to save the language.\n");
        exit(1);
    }
    file_put_contents($path, $language . PHP_EOL);
    if ($announce) {
        fwrite(STDOUT, $language === 'fr' ? "✓ Langue définie : français\n" : "✓ Language set: English\n");
    }
}

function localize(string $message): string
{
    if (currentLanguage() === 'fr' || $message === '') {
        return $message;
    }
    return strtr($message, [
        'Le dossier courant n\'est pas un projet PHPAML.' => 'The current directory is not a PHPAML project.',
        'Utilisez' => 'Use', 'Exécutez' => 'Run', 'Indiquez' => 'Provide',
        'L\'environnement AML est absent.' => 'The AML environment is missing.',
        'est introuvable ou invalide' => 'is missing or invalid', 'est introuvable' => 'is missing',
        'Téléchargement impossible' => 'Download failed', 'La réponse de GitHub' => 'The GitHub response',
        'est invalide' => 'is invalid', 'Aucune mise à jour automatique' => 'No automatic update',
        'n’est disponible pour cette plateforme' => 'is available for this platform',
        'ne contient pas' => 'does not contain', 'déjà à jour' => 'already up to date',
        'Mise à jour disponible' => 'Update available', 'Impossible de créer' => 'Unable to create',
        'Téléchargement de' => 'Downloading', 'Impossible d’enregistrer' => 'Unable to save',
        'Le checksum SHA-256' => 'The SHA-256 checksum', 'Intégrité de la mise à jour vérifiée.' => 'Update integrity verified.',
        'Cette installation est portable' => 'This is a portable installation', 'remplacement automatique désactivé' => 'automatic replacement disabled',
        'Paquet vérifié disponible ici' => 'Verified package available here', 'a été lancé' => 'was launched',
        'Terminez l’installation' => 'Complete the installation', 'a échoué' => 'failed',
        'a été installé avec succès' => 'was installed successfully', 'Aucun modèle PHPAML' => 'No PHPAML template',
        'hors connexion' => 'offline', 'est corrompu' => 'is corrupted', 'Le nom du projet est invalide.' => 'The project name is invalid.',
        'Impossible d\'ouvrir' => 'Unable to open', 'contient un chemin non sécurisé' => 'contains an unsafe path',
        'Création annulée pour éviter un écrasement' => 'Creation cancelled to prevent overwriting',
        'Impossible d\'extraire' => 'Unable to extract', 'Application' => 'Application', 'créée avec' => 'created with',
        'Adresse invalide' => 'Invalid address', 'Le port doit être compris entre' => 'The port must be between',
        'Les dépendances sont absentes.' => 'Dependencies are missing.', 'écoute sur' => 'is listening at',
        'Utilisez Ctrl+C pour arrêter le serveur.' => 'Use Ctrl+C to stop the server.',
        'Composer privé est absent' => 'Private Composer is missing', 'Préparation de l’environnement' => 'Preparing the environment',
        'Environnement AML installé avec succès.' => 'AML environment installed successfully.',
        'Aucun moteur PHPAML' => 'No PHPAML engine', 'est incomplète' => 'is incomplete',
        'Script' => 'Script', 'inconnu' => 'unknown', 'Scripts disponibles' => 'Available scripts',
        'Aucune route trouvée.' => 'No routes found.', 'MÉTHODE' => 'METHOD', 'Le nom de classe est invalide.' => 'The class name is invalid.',
        'existe déjà' => 'already exists', 'Créé :' => 'Created:', 'Le nom de migration est invalide.' => 'The migration name is invalid.',
        'Aucune migration en attente.' => 'No pending migrations.', 'Migrée :' => 'Migrated:', 'Cache vidé.' => 'Cache cleared.',
        'Aucune migration à annuler.' => 'No migration to roll back.', 'Annulée :' => 'Rolled back:',
        'Diagnostic PHPAML' => 'PHPAML diagnostics', 'Diagnostic réussi' => 'Diagnostics passed', 'avertissement' => 'warning',
        'Diagnostic échoué' => 'Diagnostics failed', 'erreur' => 'error', 'L\'option' => 'Option', 'nécessite une valeur' => 'requires a value',
        '.env existe déjà.' => '.env already exists.', 'pour le remplacer' => 'to replace it', 'Impossible de modifier' => 'Unable to update',
        'créé depuis' => 'created from', 'mis à jour' => 'updated', 'est absent ou vide' => 'is missing or empty',
        'Variable inconnue' => 'Unknown variable', 'Pilote non pris en charge' => 'Unsupported driver',
        'Configuration de la base de données' => 'Database configuration', 'Pilote' => 'Driver', 'Utilisateur' => 'Username',
        'Mot de passe' => 'Password', 'non défini' => 'not set', '(vide)' => '(empty)', 'prêt' => 'ready', 'configuré' => 'configured',
        'Commande inconnue' => 'Unknown command', 'version inconnue' => 'unknown version', 'Clé invalide' => 'Invalid key',
        'utilisez des majuscules, chiffres et underscores' => 'use uppercase letters, digits, and underscores',
        'Extensions PHP' => 'PHP extensions', 'toutes présentes' => 'all present', 'absentes :' => 'missing:',
        'Composer privé' => 'Private Composer', 'disponible' => 'available', 'introuvable' => 'not found',
        'Dossier temporaire' => 'Temporary directory', 'doit être accessible en écriture' => 'must be writable',
        'accessible en écriture' => 'writable', 'contrôle ignoré' => 'check skipped',
        'limite API atteinte' => 'API limit reached', 'indisponible' => 'unavailable',
        'Port de développement' => 'Development port', 'occupé' => 'in use',
        'Projet' => 'Project', 'aucun projet PHPAML dans le dossier courant' => 'no PHPAML project in the current directory',
        'Configuration' => 'Configuration', 'présent' => 'present', 'absent' => 'missing',
        'Environnement' => 'Environment', 'créez .env à partir de .env.example' => 'create .env from .env.example',
        'Moteur du projet' => 'Project engine', 'installé dans aml_env' => 'installed in aml_env',
        'Stockage du projet' => 'Project storage', 'ou supérieur requis' => 'or later required',
        'Mode debug' => 'Debug mode', 'désactivé' => 'disabled', 'est obligatoire en production' => 'is required in production',
        'HTTPS' => 'HTTPS', 'est absent' => 'is missing', 'Secret applicatif' => 'Application secret',
        'doit contenir au moins' => 'must contain at least', 'caractères' => 'characters',
        'Cache de production' => 'Production cache', 'doit être prêt et accessible en écriture' => 'must be ready and writable',
        'La configuration SEO est absente.' => 'SEO configuration is missing.', 'La configuration SEO est invalide.' => 'SEO configuration is invalid.',
        'Impossible d’enregistrer la configuration SEO.' => 'Unable to save SEO configuration.',
        'existe déjà.' => 'already exists.', 'pour le remplacer' => 'to replace it',
        'Configuration SEO créée' => 'SEO configuration created', 'Clé SEO inconnue' => 'Unknown SEO key',
        'Valeurs acceptées' => 'Accepted values', 'L’URL de base SEO est invalide.' => 'The SEO base URL is invalid.',
        'Cette valeur SEO ne peut pas être vide.' => 'This SEO value cannot be empty.', 'mis à jour' => 'updated',
        'Configurez une base_url SEO valide avant la génération.' => 'Configure a valid SEO base_url before generation.',
        'public/robots.txt et public/sitemap.xml générés' => 'public/robots.txt and public/sitemap.xml generated',
        'Impossible d’enregistrer public/sitemap.xml.' => 'Unable to save public/sitemap.xml.',
        'Impossible d’enregistrer public/robots.txt.' => 'Unable to save public/robots.txt.',
        'L’URL à auditer est invalide.' => 'The audit URL is invalid.',
        'Audit SEO impossible' => 'SEO audit failed', 'manquant' => 'missing', 'caractères' => 'characters',
        'Le fichier HTML à auditer est introuvable ou illisible.' => 'The HTML file to audit is missing or unreadable.',
        'Impossible de lire le fichier HTML à auditer.' => 'Unable to read the HTML file to audit.',
        'Le chemin SEO doit commencer par /.' => 'The SEO path must start with /.',
        'Le chemin SEO contient des caractères interdits.' => 'The SEO path contains forbidden characters.',
        'La règle SEO doit être allow ou disallow.' => 'The SEO rule must be allow or disallow.',
        'Règle SEO ajoutée' => 'SEO rule added', 'Règle SEO supprimée' => 'SEO rule removed',
    ]);
}

function output(string $message = ''): void
{
    fwrite(STDOUT, localize($message) . PHP_EOL);
}

function fail(string $message, int $code = 1): never
{
    $prefix = currentLanguage() === 'fr' ? 'Erreur' : 'Error';
    fwrite(STDERR, $prefix . ' : ' . localize($message) . PHP_EOL);
    exit($code);
}

function projectRoot(): string
{
    $current = getcwd() ?: PHPAML_FRAMEWORK_ROOT;
    if (is_file($current . '/info.json') && is_file($current . '/public/index.php')) {
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
    if (currentLanguage() === 'en') {
        foreach ([
            'AML — PHPAML command-line interface', '',
            'Usage: aml <command> [options]', '', 'Commands:',
            '  create <directory>       Create an application (use . for the current directory)',
            '  serve [host:port]        Start the development server',
            '  install [options]        Install the engine and dependencies into aml_env',
            '  update [options]         Update the AML environment',
            '  doctor [options]         Check the installation and current project (--production for deployment)',
            '  routes                   List application routes',
            '  make:controller <name>   Generate a controller',
            '  make:model <name>        Generate a model',
            '  make:middleware <name>   Generate middleware',
            '  make:migration <name>    Generate a migration',
            '  migrate                  Run pending migrations',
            '  migrate:rollback         Roll back the latest migration (--steps N)',
            '  db:configure [driver]    Configure the database (sqlite or mysql)',
            '  db:show                  Show database configuration',
            '  seo:init                 Create the SEO configuration',
            '  seo:set <key> <value>    Update an SEO setting',
            '  seo:show                 Show the SEO configuration',
            '  seo:allow <path>         Allow crawlers on a path',
            '  seo:disallow <path>      Block crawlers on a path',
            '  seo:remove <rule> <path> Remove an allow/disallow rule',
            '  seo:generate             Generate robots.txt and sitemap.xml',
            '  seo:audit [url]          Audit SEO metadata (--json, --file)',
            '  env:init [--force]       Create .env from .env.example',
            '  env:list                 List .env variables',
            '  env:get <key>            Read an .env variable',
            '  env:set <key> <value>    Create or update an .env variable',
            '  language [en|fr]         Show or change the CLI language',
            '  cache:clear              Clear the application cache',
            '  run <script>             Run a script declared in info.json',
            '  test                     Run automated tests',
            '  version                  Show the framework version',
            '  help                     Show this help', '', 'Examples:',
            '  aml create .', '  aml create my-project', '  aml serve 127.0.0.1:8080',
            '  aml install', '  aml update --check', '  aml doctor',
            '  aml env:init', '  aml env:set APP_DEBUG false',
            '  aml db:configure sqlite', '  aml language fr',
        ] as $line) {
            output($line);
        }
        return;
    }
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
    output('  migrate:rollback          Annule la dernière migration (--steps N)');
    output('  db:configure [pilote]     Configure la base (sqlite ou mysql)');
    output('  db:show                   Affiche la configuration de la base');
    output('  seo:init                  Crée la configuration SEO');
    output('  seo:set <clé> <valeur>    Modifie un réglage SEO');
    output('  seo:show                  Affiche la configuration SEO');
    output('  seo:allow <chemin>        Autorise l’exploration d’un chemin');
    output('  seo:disallow <chemin>     Bloque l’exploration d’un chemin');
    output('  seo:remove <règle> <chemin> Supprime une règle allow/disallow');
    output('  seo:generate              Génère robots.txt et sitemap.xml');
    output('  seo:audit [url]           Audite les métadonnées SEO (--json, --file)');
    output('  env:init [--force]        Crée .env depuis .env.example');
    output('  env:list                  Affiche les variables de .env');
    output('  env:get <clé>             Lit une variable de .env');
    output('  env:set <clé> <valeur>    Crée ou modifie une variable de .env');
    output('  language [en|fr]          Affiche ou change la langue du CLI');
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
    output('  aml doctor --production');
    output('  aml doctor --port 8080');
    output('  aml env:init');
    output('  aml env:set APP_DEBUG false');
    output('  aml db:configure sqlite');
}

/** @return list<string> */
function scaffoldFiles(): array
{
    $files = ['info.json', 'readme', '.gitignore'];
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
        file_put_contents($destinationPath, $content);
    }

    $zip->close();

    if (currentLanguage() === 'en') {
        output("Application '{$projectName}' created with PHPAML v{$template['version']} in {$target}");
        output($destination === '.'
            ? 'Run: aml install && aml serve'
            : "Run: cd {$destination} && aml install && aml serve");
    } else {
        output("Application '{$projectName}' créée avec PHPAML v{$template['version']} dans {$target}");
        output($destination === '.'
            ? 'Lancez : aml install && aml serve'
            : "Lancez : cd {$destination} && aml install && aml serve");
    }
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
    if (!is_file($root . '/public/index.php')) {
        fail("La racine publique public/index.php est absente.");
    }
    output("PHPAML écoute sur http://{$address}");
    output('Utilisez Ctrl+C pour arrêter le serveur.');
    passthru(
        escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($address)
        . ' -t ' . escapeshellarg($root . '/public') . ' ' . escapeshellarg($root . '/public/index.php'),
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
    if ($version === null) {
        $project = projectInfo($root);
        $declaredFramework = $project['aml']['framework'] ?? null;
        if (is_string($declaredFramework) && trim($declaredFramework) !== '') {
            $version = ltrim(trim($declaredFramework), 'v');
        }
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

function migrate(bool $rollback = false, int $steps = 1): void
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
    $migrator = new \PHPAML\Data\Migrator($connection, $root . '/database/migrations');
    $completed = $rollback ? $migrator->rollback($steps) : $migrator->migrate();
    output($completed === []
        ? ($rollback ? 'Aucune migration à annuler.' : 'Aucune migration en attente.')
        : implode(PHP_EOL, array_map(
            static fn ($name) => ($rollback ? 'Annulée :' : 'Migrée :') . " {$name}",
            $completed
        )));
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
    $name = localize($name);
    $message = localize($message);
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

function doctor(?string $requestedPort, bool $offline, bool $json, bool $production = false): never
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

    $runtimeDirectories = [
        ((string) (getenv('TMPDIR') ?: PHPAML_FRAMEWORK_ROOT . '/aml_env/tmp')) => 'Dossier temporaire',
        ((string) (getenv('COMPOSER_HOME') ?: PHPAML_FRAMEWORK_ROOT . '/aml_env/cache')) => 'Cache AML',
    ];
    foreach ($runtimeDirectories as $directory => $label) {
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
    $isProject = is_file($current . '/info.json') && is_file($current . '/public/index.php');
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
        $environment = readEnvValues($current . '/.env');
        if ($production) {
            $debugDisabled = strtolower($environment['APP_DEBUG'] ?? '') === 'false';
            doctorAdd($checks, $debugDisabled ? 'ok' : 'error', 'Mode debug', $debugDisabled ? 'désactivé' : 'APP_DEBUG=false est obligatoire en production');
            $appUrl = trim($environment['APP_URL'] ?? '');
            doctorAdd($checks, str_starts_with($appUrl, 'https://') ? 'ok' : 'error', 'HTTPS', $appUrl !== '' ? $appUrl : 'APP_URL HTTPS est absent');
            $appKey = trim($environment['APP_KEY'] ?? '');
            doctorAdd($checks, strlen($appKey) >= 32 ? 'ok' : 'error', 'Secret applicatif', strlen($appKey) >= 32 ? 'configuré' : 'APP_KEY doit contenir au moins 32 caractères');
            $productionCache = $current . '/aml_env/storage/cache';
            $cacheReady = is_dir($productionCache) && is_writable($productionCache);
            doctorAdd($checks, $cacheReady ? 'ok' : 'error', 'Cache de production', $cacheReady ? 'prêt et accessible en écriture' : 'aml_env/storage/cache doit être prêt et accessible en écriture');
        }
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
        $symbols = currentLanguage() === 'fr'
            ? ['ok' => 'OK', 'warning' => 'ATTENTION', 'error' => 'ERREUR', 'info' => 'INFO']
            : ['ok' => 'OK', 'warning' => 'WARNING', 'error' => 'ERROR', 'info' => 'INFO'];
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

function envFilePath(): string
{
    return projectRoot() . '/.env';
}

/** @return array<string, string> */
function readEnvValues(string $path): array
{
    $values = [];
    if (!is_file($path)) {
        return $values;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value);
    }
    return $values;
}

function envInit(bool $force = false): void
{
    $root = projectRoot();
    $target = $root . '/.env';
    $example = $root . '/.env.example';
    if (is_file($target) && !$force) {
        fail(".env existe déjà. Utilisez 'aml env:init --force' pour le remplacer.");
    }
    if (!is_file($example)) {
        fail(".env.example est introuvable.");
    }
    if (!copy($example, $target)) {
        fail("Impossible de créer .env.");
    }
    output('✓ .env créé depuis .env.example');
}

function envSet(string $key, string $value): void
{
    if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) !== 1) {
        fail("Clé invalide : utilisez des majuscules, chiffres et underscores.");
    }
    $path = envFilePath();
    if (!is_file($path)) {
        envInit();
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $escaped = str_replace(["\r", "\n"], '', $value);
    $replacement = $key . '=' . $escaped;
    $found = false;
    foreach ($lines as &$line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line) === 1) {
            $line = $replacement;
            $found = true;
            break;
        }
    }
    unset($line);
    if (!$found) {
        $lines[] = $replacement;
    }
    if (file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
        fail("Impossible de modifier .env.");
    }
    output("✓ {$key} mis à jour");
}

function envList(): void
{
    $values = readEnvValues(envFilePath());
    if ($values === []) {
        fail(".env est absent ou vide. Exécutez 'aml env:init'.");
    }
    foreach ($values as $key => $value) {
        $display = preg_match('/(PASSWORD|SECRET|TOKEN|KEY)$/', $key) === 1 && $value !== ''
            ? str_repeat('*', 8)
            : $value;
        output(str_pad($key, 24) . $display);
    }
}

function envGet(string $key): void
{
    $values = readEnvValues(envFilePath());
    if (!array_key_exists($key, $values)) {
        fail("Variable inconnue : {$key}.");
    }
    output($values[$key]);
}

function configureDatabase(string $driver, array $arguments): void
{
    $driver = strtolower($driver);
    if ($driver === 'sqlite') {
        $relativePath = optionValue($arguments, '--path') ?? 'aml_env/storage/database.sqlite';
        $absolutePath = str_starts_with($relativePath, DIRECTORY_SEPARATOR)
            ? $relativePath
            : projectRoot() . '/' . ltrim($relativePath, '/\\');
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            fail("Impossible de créer {$directory}.");
        }
        if (!is_file($absolutePath) && touch($absolutePath) === false) {
            fail("Impossible de créer {$absolutePath}.");
        }
        envSet('DATABASE_DRIVER', 'sqlite');
        envSet('DATABASE_DSN', 'sqlite:' . $relativePath);
        envSet('DATABASE_USER', optionValue($arguments, '--user') ?? 'root');
        envSet('DATABASE_PASSWORD', optionValue($arguments, '--password') ?? 'root');
        output("✓ SQLite prêt : {$relativePath}");
        return;
    }
    if ($driver === 'mysql') {
        $host = optionValue($arguments, '--host') ?? '127.0.0.1';
        $port = optionValue($arguments, '--port') ?? '3306';
        $database = optionValue($arguments, '--database') ?? 'phpaml';
        envSet('DATABASE_DRIVER', 'mysql');
        envSet('DATABASE_DSN', "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4");
        envSet('DATABASE_USER', optionValue($arguments, '--user') ?? 'root');
        envSet('DATABASE_PASSWORD', optionValue($arguments, '--password') ?? 'root');
        output("✓ MySQL configuré : {$host}:{$port}/{$database}");
        return;
    }
    fail("Pilote non pris en charge : {$driver}. Utilisez sqlite ou mysql.");
}

function showDatabaseConfig(): void
{
    $values = readEnvValues(envFilePath());
    output('Configuration de la base de données');
    output('  Pilote       : ' . ($values['DATABASE_DRIVER'] ?? 'sqlite'));
    output('  DSN          : ' . ($values['DATABASE_DSN'] ?? 'non défini'));
    output('  Utilisateur  : ' . ($values['DATABASE_USER'] ?? ''));
    output('  Mot de passe : ' . (isset($values['DATABASE_PASSWORD']) && $values['DATABASE_PASSWORD'] !== '' ? '********' : '(vide)'));
}

function seoConfigPath(): string
{
    return projectRoot() . '/configs/seo.json';
}

/** @return array<string, mixed> */
function seoConfig(): array
{
    $path = seoConfigPath();
    if (!is_file($path)) {
        fail("La configuration SEO est absente. Exécutez 'aml seo:init'.");
    }
    try {
        $config = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        fail('La configuration SEO est invalide.');
    }
    if (!is_array($config)) {
        fail('La configuration SEO est invalide.');
    }
    return $config;
}

/** @param array<string, mixed> $config */
function writeSeoConfig(array $config): void
{
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents(seoConfigPath(), $json . PHP_EOL, LOCK_EX) === false) {
        fail('Impossible d’enregistrer la configuration SEO.');
    }
}

function seoInit(bool $force = false): void
{
    $path = seoConfigPath();
    if (is_file($path) && !$force) {
        fail("configs/seo.json existe déjà. Utilisez 'aml seo:init --force' pour le remplacer.");
    }
    $environment = readEnvValues(projectRoot() . '/.env');
    $info = projectInfo();
    $name = (string) ($info['name'] ?? 'PHPAML application');
    writeSeoConfig([
        'site_name' => $name,
        'title' => $name,
        'description' => '',
        'base_url' => rtrim((string) ($environment['APP_URL'] ?? 'http://localhost:8000'), '/'),
        'locale' => currentLanguage() === 'fr' ? 'fr_CA' : 'en_CA',
        'robots' => 'index,follow',
        'image' => '',
        'twitter_card' => 'summary_large_image',
        'type' => 'WebSite',
        'author' => '',
        'theme_color' => '#6d28d9',
        'allow' => ['/'],
        'disallow' => [],
    ]);
    output('✓ Configuration SEO créée : configs/seo.json');
}

function seoSet(string $key, string $value): void
{
    $allowed = ['site_name', 'title', 'description', 'base_url', 'locale', 'robots', 'image', 'twitter_card', 'type', 'author', 'theme_color'];
    if (!in_array($key, $allowed, true)) {
        fail('Clé SEO inconnue : ' . $key . '. Valeurs acceptées : ' . implode(', ', $allowed));
    }
    if ($key === 'base_url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
        fail('L’URL de base SEO est invalide.');
    }
    if (in_array($key, ['title', 'site_name'], true) && trim($value) === '') {
        fail('Cette valeur SEO ne peut pas être vide.');
    }
    $config = seoConfig();
    $config[$key] = trim(str_replace(["\r", "\n"], ' ', $value));
    writeSeoConfig($config);
    output("✓ SEO {$key} mis à jour");
}

function seoShow(bool $json): void
{
    $config = seoConfig();
    if ($json) {
        output((string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return;
    }
    output(currentLanguage() === 'fr' ? 'Configuration SEO' : 'SEO configuration');
    foreach ($config as $key => $value) {
        $display = is_array($value) ? implode(', ', array_filter($value, 'is_scalar')) : (is_scalar($value) ? (string) $value : '');
        output(str_pad((string) $key, 18) . $display);
    }
}

function normalizeSeoPath(string $path): string
{
    $path = trim($path);
    if ($path === '' || !str_starts_with($path, '/')) {
        fail('Le chemin SEO doit commencer par /.');
    }
    if (preg_match('/[\r\n#]/', $path)) {
        fail('Le chemin SEO contient des caractères interdits.');
    }
    return $path;
}

function seoRule(string $rule, string $path, bool $remove = false): void
{
    if (!in_array($rule, ['allow', 'disallow'], true)) {
        fail('La règle SEO doit être allow ou disallow.');
    }
    $path = normalizeSeoPath($path);
    $config = seoConfig();
    $rules = is_array($config[$rule] ?? null) ? $config[$rule] : [];
    $rules = array_values(array_unique(array_filter($rules, 'is_string')));
    if ($remove) {
        $rules = array_values(array_filter($rules, static fn (string $item): bool => $item !== $path));
        $message = "✓ Règle SEO supprimée : {$rule} {$path}";
    } else {
        $rules[] = $path;
        $rules = array_values(array_unique($rules));
        sort($rules, SORT_STRING);
        $message = "✓ Règle SEO ajoutée : {$rule} {$path}";
    }
    $config[$rule] = $rules;
    writeSeoConfig($config);
    output($message);
}

/** @param list<string> $disallowed @param list<string> $allowed */
function seoPathIsDisallowed(string $path, array $disallowed, array $allowed = ['/']): bool
{
    $specificAllow = -1;
    foreach ($allowed as $rule) {
        if ($rule === '/' || $path === $rule || str_starts_with($path, rtrim($rule, '/') . '/')) {
            $specificAllow = max($specificAllow, strlen($rule));
        }
    }
    $specificDisallow = -1;
    foreach ($disallowed as $rule) {
        if ($rule === '/' || $path === $rule || str_starts_with($path, rtrim($rule, '/') . '/')) {
            $specificDisallow = max($specificDisallow, strlen($rule));
        }
    }
    return $specificDisallow >= 0 && $specificDisallow >= $specificAllow;
}

function seoGenerate(): void
{
    $root = projectRoot();
    $seo = seoConfig();
    $baseUrl = rtrim((string) ($seo['base_url'] ?? ''), '/');
    if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
        fail('Configurez une base_url SEO valide avant la génération.');
    }
    $config = loadProjectConfig();
    $routes = is_array($config['routes'] ?? null) ? $config['routes'] : [];
    $allow = array_values(array_filter(is_array($seo['allow'] ?? null) ? $seo['allow'] : ['/'], 'is_string'));
    $disallow = array_values(array_filter(is_array($seo['disallow'] ?? null) ? $seo['disallow'] : [], 'is_string'));
    $paths = ['/'];
    foreach (array_keys($routes) as $definition) {
        if (!is_string($definition) || !str_starts_with($definition, 'GET ')) {
            continue;
        }
        $path = substr($definition, 4);
        if (!str_contains($path, '{')) {
            $paths[] = $path;
        }
    }
    $paths = array_values(array_unique($paths));
    $paths = array_values(array_filter($paths, static fn (string $path): bool => !seoPathIsDisallowed($path, $disallow, $allow)));
    sort($paths, SORT_STRING);
    $urls = '';
    foreach ($paths as $path) {
        $location = htmlspecialchars($baseUrl . ($path === '/' ? '/' : '/' . ltrim($path, '/')), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $urls .= "  <url><loc>{$location}</loc></url>\n";
    }
    $sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}</urlset>\n";
    if (file_put_contents($root . '/public/sitemap.xml', $sitemap, LOCK_EX) === false) {
        fail('Impossible d’enregistrer public/sitemap.xml.');
    }
    $robots = "User-agent: *\n";
    foreach ($allow as $path) {
        $robots .= 'Allow: ' . normalizeSeoPath($path) . "\n";
    }
    foreach ($disallow as $path) {
        $robots .= 'Disallow: ' . normalizeSeoPath($path) . "\n";
    }
    $robots .= "Sitemap: {$baseUrl}/sitemap.xml\n";
    if (file_put_contents($root . '/public/robots.txt', $robots, LOCK_EX) === false) {
        fail('Impossible d’enregistrer public/robots.txt.');
    }
    output('✓ public/robots.txt et public/sitemap.xml générés');
}

/** @return list<array{status: string, name: string, message: string}> */
function seoAuditChecks(string $url, string $html): array
{
    $checks = [];
    $add = static function (bool $ok, string $name, string $message) use (&$checks): void {
        $checks[] = ['status' => $ok ? 'ok' : 'error', 'name' => $name, 'message' => $message];
    };
    preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch);
    $title = trim(strip_tags(html_entity_decode($titleMatch[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $add($title !== '' && mb_strlen($title) <= 60, 'Title', $title === '' ? 'missing' : mb_strlen($title) . ' characters');
    preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/i', $html, $descriptionMatch);
    if (!isset($descriptionMatch[1])) {
        preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/i', $html, $descriptionMatch);
    }
    $description = trim(html_entity_decode($descriptionMatch[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $add(mb_strlen($description) >= 50 && mb_strlen($description) <= 160, 'Description', $description === '' ? 'missing' : mb_strlen($description) . ' characters');
    $add((bool) preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=/i', $html), 'Canonical', 'canonical link');
    $add((bool) preg_match('/<meta[^>]+property=["\']og:title["\']/i', $html), 'Open Graph', 'og:title');
    $add((bool) preg_match('/<meta[^>]+name=["\']twitter:card["\']/i', $html), 'Twitter Card', 'twitter:card');
    $add((bool) preg_match('/<html[^>]+lang=["\'][^"\']+["\']/i', $html), 'Language', 'html lang');
    $add((bool) preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html), 'Viewport', 'mobile viewport');
    $add((bool) preg_match('/<script[^>]+type=["\']application\/ld\+json["\']/i', $html), 'Structured data', 'JSON-LD');
    $add(substr_count(strtolower($html), '<h1') === 1, 'H1', substr_count(strtolower($html), '<h1') . ' found');
    preg_match_all('/<img\b[^>]*>/i', $html, $images);
    $missingAlt = count(array_filter($images[0], static fn (string $image): bool => !preg_match('/\balt=["\'][^"\']*["\']/i', $image)));
    $add($missingAlt === 0, 'Image alt', "{$missingAlt} missing");
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $add(str_starts_with($url, 'https://') || $isLocal, 'HTTPS', $url);
    return $checks;
}

function seoAudit(?string $url, bool $json, ?string $file = null): never
{
    $seo = is_file(seoConfigPath()) ? seoConfig() : [];
    $url ??= (string) ($seo['base_url'] ?? 'http://localhost:8000');
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        fail('L’URL à auditer est invalide.');
    }
    if ($file !== null) {
        $path = str_starts_with($file, '/') ? $file : projectRoot() . '/' . ltrim($file, '/');
        if (!is_file($path) || !is_readable($path)) {
            fail('Le fichier HTML à auditer est introuvable ou illisible.');
        }
        $html = file_get_contents($path);
        if (!is_string($html)) {
            fail('Impossible de lire le fichier HTML à auditer.');
        }
    } else {
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'AML-SEO/1.0']);
        $html = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($html) || $status < 200 || $status >= 400) {
            fail("Audit SEO impossible (HTTP {$status}) : " . ($error ?: $url));
        }
    }
    $checks = seoAuditChecks($url, $html);
    $errors = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'error'));
    if ($json) {
        output((string) json_encode(['url' => $url, 'healthy' => $errors === 0, 'errors' => $errors, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    } else {
        output((currentLanguage() === 'fr' ? 'Audit SEO : ' : 'SEO audit: ') . $url);
        foreach ($checks as $check) {
            output(str_pad('[' . strtoupper($check['status']) . ']', 10) . str_pad($check['name'], 20) . $check['message']);
        }
    }
    exit($errors === 0 ? 0 : 1);
}

switch ($command) {
    case 'language':
    case 'lang':
        if (isset($arguments[1])) {
            saveLanguage($arguments[1]);
        } else {
            output(currentLanguage() === 'fr' ? 'Langue actuelle : français' : 'Current language: English');
        }
        break;
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
            in_array('--json', $arguments, true),
            in_array('--production', $arguments, true)
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
    case 'migrate:rollback':
        $steps = optionValue($arguments, '--steps') ?? '1';
        if (filter_var($steps, FILTER_VALIDATE_INT) === false || (int) $steps < 1) {
            fail("L'option '--steps' nécessite une valeur entière positive.");
        }
        migrate(true, (int) $steps);
        break;
    case 'db:configure':
        configureDatabase($arguments[1] ?? 'sqlite', $arguments);
        break;
    case 'db:show':
        showDatabaseConfig();
        break;
    case 'seo:init':
        seoInit(in_array('--force', $arguments, true));
        break;
    case 'seo:set':
        isset($arguments[1], $arguments[2]) ? seoSet($arguments[1], $arguments[2]) : fail('Utilisation : aml seo:set <clé> <valeur>.');
        break;
    case 'seo:show':
        seoShow(in_array('--json', $arguments, true));
        break;
    case 'seo:allow':
        isset($arguments[1]) ? seoRule('allow', $arguments[1]) : fail('Utilisation : aml seo:allow <chemin>.');
        break;
    case 'seo:disallow':
        isset($arguments[1]) ? seoRule('disallow', $arguments[1]) : fail('Utilisation : aml seo:disallow <chemin>.');
        break;
    case 'seo:remove':
        isset($arguments[1], $arguments[2]) ? seoRule($arguments[1], $arguments[2], true) : fail('Utilisation : aml seo:remove <allow|disallow> <chemin>.');
        break;
    case 'seo:generate':
        seoGenerate();
        break;
    case 'seo:audit':
    case 'seo':
        $seoUrl = isset($arguments[1]) && !str_starts_with($arguments[1], '--') ? $arguments[1] : null;
        seoAudit($seoUrl, in_array('--json', $arguments, true), optionValue($arguments, '--file'));
    case 'env:init':
        envInit(in_array('--force', $arguments, true));
        break;
    case 'env:list':
        envList();
        break;
    case 'env:get':
        isset($arguments[1]) ? envGet($arguments[1]) : fail('Indiquez la clé à lire.');
        break;
    case 'env:set':
        isset($arguments[1], $arguments[2])
            ? envSet($arguments[1], $arguments[2])
            : fail('Utilisation : aml env:set <clé> <valeur>.');
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
