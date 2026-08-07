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
    output('  install [--production]    Prépare les dépendances dans aml_env');
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

function installModules(bool $production = false): never
{
    $root = projectRoot();
    if (!is_file($root . '/composer.json')) {
        fail('Le fichier composer.json est introuvable.');
    }
    $composer = trim((string) shell_exec('command -v composer 2>/dev/null'));
    if ($composer === '') {
        fail('Composer est requis pour installer les modules AML.');
    }
    installFramework($root);
    output('Préparation de l’environnement aml_env/…');
    $command = 'cd ' . escapeshellarg($root)
        . ' && ' . escapeshellarg($composer)
        . ' install --no-interaction --prefer-dist'
        . ($production ? ' --no-dev --optimize-autoloader' : '');
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
    $manifest = [
        'installed_at' => date(DATE_ATOM),
        'mode' => $production ? 'production' : 'development',
        'lock' => is_file($root . '/composer.lock') ? hash_file('sha256', $root . '/composer.lock') : null,
    ];
    file_put_contents(
        $root . '/aml_env/aml-installed.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
    output('Environnement AML installé avec succès.');
    exit(0);
}

function installFramework(string $projectRoot): void
{
    $source = PHPAML_FRAMEWORK_ROOT . '/aml_env/framework';
    $destination = $projectRoot . '/aml_env/framework';
    if (!is_dir($source)) {
        fail('Le moteur PHPAML est introuvable dans l’installation globale.');
    }
    if (realpath($source) === realpath($destination)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $target = $destination . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy($item->getPathname(), $target);
        }
    }
    foreach ([$projectRoot . '/aml_env/storage', $projectRoot . '/aml_env/storage/cache'] as $runtimeDirectory) {
        if (!is_dir($runtimeDirectory)) {
            mkdir($runtimeDirectory, 0755, true);
        }
    }
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
        installModules(in_array('--production', $arguments, true));
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
