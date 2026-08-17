#!/usr/bin/env php
<?php

declare(strict_types=1);

define('PHPAML_FRAMEWORK_ROOT', dirname(__DIR__, 2));

function amlCacheRoot(): string
{
    return rtrim((string) (getenv('AML_CACHE_HOME') ?: PHPAML_FRAMEWORK_ROOT . '/runtime/cache'), '/\\');
}

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
        'Le moteur PHPAML est absent : installation automatique…' => 'The PHPAML engine is missing: installing it automatically…',
        'La commande' => 'The command', 'a été retirée.' => 'has been removed.',
        'Impossible d’ouvrir le projet créé dans' => 'Unable to open the project created in',
        'Impossible de migrer app/View vers app/UI.' => 'Unable to migrate app/View to app/UI.',
        'Migré : app/View → app/UI' => 'Migrated: app/View → app/UI',
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
        'environnement restreint : écriture interdite' => 'restricted environment: writing is forbidden',
        'accessible en écriture' => 'writable', 'contrôle ignoré' => 'check skipped',
        'limite API atteinte' => 'API limit reached', 'indisponible' => 'unavailable',
        'Port de développement' => 'Development port', 'occupé' => 'in use',
        'ouverture interdite par l’environnement' => 'opening forbidden by the environment',
        'indisponible' => 'unavailable',
        'Projet' => 'Project', 'aucun projet PHPAML dans le dossier courant' => 'no PHPAML project in the current directory',
        'Configuration' => 'Configuration', 'présent' => 'present', 'absent' => 'missing',
        'Environnement' => 'Environment', 'créez .env à partir de .env.example' => 'create .env from .env.example',
        'Moteur du projet' => 'Project engine', 'installé dans runtime' => 'installed in runtime',
        'Stockage du projet' => 'Project storage', 'ou supérieur requis' => 'or later required',
        'Structure AML View' => 'AML View structure', 'dossier obligatoire présent' => 'required directory present',
        'dossier obligatoire absent' => 'required directory missing',
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
        'Installation de phpaml/view' => 'Installing phpaml/view',
        'L’installation Composer de phpaml/view a échoué.' => 'Composer installation of phpaml/view failed.',
        'La version AML View est invalide.' => 'The AML View version is invalid.',
        'Le binaire Composer configuré' => 'The configured Composer binary',
        'non exécutable' => 'not executable', 'Impossible de modifier phpaml.json.' => 'Unable to update phpaml.json.',
        'AML View installé.' => 'AML View installed.', 'Ouvrez' => 'Open', 'pour tester la page interactive.' => 'to test the interactive page.',
        'Modifié :' => 'Updated:', 'Type AML View inconnu.' => 'Unknown AML View type.',
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
    if ((is_file($current . '/phpaml.json') || is_file($current . '/info.json')) && is_file($current . '/public/index.php')) {
        return $current;
    }
    fail("Le dossier courant n'est pas un projet PHPAML. Utilisez 'aml create .'.");
}

function projectManifestPath(string $root): string
{
    return is_file($root . '/phpaml.json') ? $root . '/phpaml.json' : $root . '/info.json';
}

function projectRuntimePath(string $root): string
{
    if (is_dir($root . '/runtime') || !is_dir($root . '/aml_env')) {
        return $root . '/runtime';
    }
    return $root . '/aml_env';
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
    $runtime = projectRuntimePath($root);
    $moduleAutoloader = $runtime . '/autoload.php';
    if (is_file($moduleAutoloader)) {
        require_once $moduleAutoloader;
        return;
    }
    $frameworkAutoloader = $runtime . '/framework/Autoloader.php';
    if (!is_file($frameworkAutoloader)) {
        fail("L'environnement AML est absent. Exécutez 'aml install'.");
    }
    require_once $frameworkAutoloader;
    \PHPAML\Autoloader::register(['PHPAML\\' => $runtime . '/framework', 'App\\' => $root . '/app']);
}

function projectInfo(?string $root = null): array
{
    $path = projectManifestPath($root ?? projectRoot());
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
            '  create-view-app <directory> Create an application with AML View',
            '  serve [host:port]        Start the development server',
            '  install [module]         Install the engine or an optional module',
            '  build [options]          Create a production deployment archive',
            '  deploy <profile>         Build and deploy through SSH/SFTP',
            '  deploy:configure <name>  Configure a deployment profile',
            '  deploy:check <name>      Test an SSH connection',
            '  deploy:rollback <name>   Activate the previous release',
            '  ssh <profile>            Open a remote SSH shell',
            '  sftp <profile>           Open an SFTP session',
            '  --update [options]       Update AML itself (also: aml update)',
            '  doctor [options]         Check the installation and current project (--production for deployment)',
            '  debug [problem]          Diagnose with AI (--fix to apply, --yes to confirm safe changes)',
            '  debug:history            List saved AI diagnostics',
            '  debug:show <id>          Show a diagnostic report',
            '  debug:rollback <id>      Roll back changes from a diagnostic',
            '  ai:configure <provider>  Configure DeepSeek, OpenAI or Claude',
            '  ai:show                  Show the active AI provider (key remains hidden)',
            '  routes                   List application routes',
            '  make:controller <name>   Generate a controller',
            '  make:model <name>        Generate a model',
            '  make:middleware <name>   Generate middleware',
            '  make:migration <name>    Generate a migration',
            '  make:seeder <name>       Generate a data seeder',
            '  make:view-page <name>    Generate an AML View page',
            '  make:view-component <name> Generate an AML View component',
            '  make:view-layout <name>  Generate an AML View layout',
            '  make:view-loading [route] Generate an AML View loading state',
            '  make:view-error [route]  Generate an AML View error state',
            '  make:view-not-found [route] Generate an AML View 404 state',
            '  i18n:add <locale>        Add a translation locale',
            '  i18n:list                List translation locales',
            '  i18n:check               Validate JSON and missing translations',
            '  i18n:missing <locale>    List missing translations for a locale',
            '  i18n:set-default <locale> Set the default application locale',
            '  migrate                  Run pending migrations',
            '  data:migrate             Run phpaml/data migrations',
            '  data:rollback            Roll back phpaml/data migrations',
            '  data:seed                Run configured data seeders',
            '  data:status              Show migration status',
            '  data:doctor              Diagnose the selected connection',
            '  migrate:rollback         Roll back the latest migration (--steps N)',
            '  migrate:structure        Preview or apply the legacy structure migration',
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
            '  run <script>             Run a script declared in phpaml.json',
            '  test                     Run automated tests',
            '  version                  Show the framework version',
            '  help                     Show this help', '', 'Examples:',
            '  aml create .', '  aml create my-project', '  aml create-view-app my-view-app',
            '  aml serve 127.0.0.1:8080', '  aml install', '  aml --update --check', '  aml doctor',
            '  aml env:init', '  aml env:set APP_DEBUG false',
            '  aml db:configure sqlite', '  aml install i18n', '  aml language fr',
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
    output('  create-view-app <dossier> Crée une application avec AML View');
    output('  serve [hôte:port]         Lance le serveur de développement');
    output('  install [module]          Installe le moteur ou un module optionnel');
    output('  build [options]           Crée une archive de déploiement production');
    output('  deploy <profil>           Construit et déploie par SSH/SFTP');
    output('  deploy:configure <nom>    Configure un serveur de déploiement');
    output('  deploy:check <nom>        Teste la connexion SSH');
    output('  deploy:rollback <nom>     Réactive la release précédente');
    output('  ssh <profil>              Ouvre une session SSH');
    output('  sftp <profil>             Ouvre une session SFTP');
    output('  --update [options]        Met à jour AML (alias : aml update)');
    output('  doctor [options]          Vérifie l’installation et le projet courant');
    output('  debug [problème]          Diagnostique avec l’IA (--fix applique, --yes confirme)');
    output('  debug:history             Affiche les diagnostics enregistrés');
    output('  debug:show <id>           Affiche un rapport de diagnostic');
    output('  debug:rollback <id>       Annule les corrections d’un diagnostic');
    output('  ai:configure <fournisseur> Configure DeepSeek, OpenAI ou Claude');
    output('  ai:show                   Affiche le fournisseur IA (clé masquée)');
    output('  routes                    Affiche les routes de l’application');
    output('  make:controller <nom>     Génère un contrôleur');
    output('  make:model <nom>          Génère un modèle');
    output('  make:middleware <nom>     Génère un middleware');
    output('  make:migration <nom>      Génère une migration');
    output('  make:seeder <nom>         Génère un seeder de données');
    output('  make:view-page <nom>      Génère une page AML View');
    output('  make:view-component <nom> Génère un composant AML View');
    output('  make:view-layout <nom>    Génère un layout AML View');
    output('  make:view-loading [route] Génère un état de chargement AML View');
    output('  make:view-error [route]   Génère un état d’erreur AML View');
    output('  make:view-not-found [route] Génère un état 404 AML View');
    output('  i18n:add <langue>         Ajoute une langue de traduction');
    output('  i18n:list                 Affiche les langues disponibles');
    output('  i18n:check                Valide les JSON et traductions manquantes');
    output('  i18n:missing <langue>     Affiche les traductions manquantes');
    output('  i18n:set-default <langue> Définit la langue principale');
    output('  migrate                   Exécute les migrations en attente');
    output('  data:migrate              Exécute les migrations phpaml/data');
    output('  data:rollback             Annule les migrations phpaml/data');
    output('  data:seed                 Exécute les seeders configurés');
    output('  data:status               Affiche l’état des migrations');
    output('  data:doctor               Diagnostique la connexion active');
    output('  migrate:rollback          Annule la dernière migration (--steps N)');
    output('  migrate:structure         Prévisualise ou applique la migration de structure');
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
    output('  run <script>              Exécute un script déclaré dans phpaml.json');
    output('  test                      Exécute les tests automatisés');
    output('  version                   Affiche la version du framework');
    output('  help                      Affiche cette aide');
    output();
    output('Exemples :');
    output('  aml create .');
    output('  aml create mon-projet');
    output('  aml create-view-app mon-interface');
    output('  aml create mon-projet --version 0.1.0');
    output('  aml create mon-projet --offline');
    output('  aml serve 127.0.0.1:8080');
    output('  aml install');
    output('  aml install i18n');
    output('  aml install --version 0.1.0');
    output('  aml --update --check');
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
    $files = ['phpaml.json', 'readme', '.gitignore'];
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
    $content = false;
    $status = 0;
    $error = '';
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'AML-CLI/' . (projectInfo(PHPAML_FRAMEWORK_ROOT)['version'] ?? 'development'),
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
        ]);
        $content = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (is_string($content) && $status >= 200 && $status < 300) {
            return $content;
        }
        if ($attempt < 3 && ($status === 0 || $status === 408 || $status === 429 || $status >= 500)) {
            usleep(250000 * $attempt);
            continue;
        }
        break;
    }
    fail("Téléchargement impossible après 3 tentatives ({$status}) : " . ($error ?: $url));
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
    $cacheRoot = amlCacheRoot() . '/templates';
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

function creationTarget(string $destination): string
{
    $base = getcwd() ?: PHPAML_FRAMEWORK_ROOT;
    $normalizedDestination = str_replace('\\', '/', trim($destination));
    $isAbsolute = str_starts_with($normalizedDestination, '/')
        || preg_match('/^[A-Za-z]:\//', $normalizedDestination) === 1
        || str_starts_with($destination, '\\\\');

    return $destination === '.'
        ? $base
        : ($isAbsolute
            ? rtrim($destination, '/\\')
            : $base . DIRECTORY_SEPARATOR . trim($destination, '/\\'));
}

function createProject(
    string $destination,
    ?string $version = null,
    bool $refresh = false,
    bool $offline = false
): void
{
    $target = creationTarget($destination);
    $projectName = basename(str_replace('\\', '/', $target));

    if ($projectName === '' || in_array($projectName, ['.', '..'], true)) {
        fail('Le nom du projet est invalide.');
    }

    if ($version === null) {
        $configuredTemplate = projectInfo(PHPAML_FRAMEWORK_ROOT)['template']['version'] ?? null;
        $version = is_string($configuredTemplate) && trim($configuredTemplate) !== ''
            ? trim($configuredTemplate)
            : null;
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
        if ($relative === 'phpaml.json') {
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

function createViewApplication(
    string $destination,
    ?string $templateVersion = null,
    ?string $viewVersion = null,
    bool $refresh = false,
    bool $offline = false
): never {
    $target = creationTarget($destination);
    createProject($destination, $templateVersion, $refresh, $offline);
    if (!chdir($target)) {
        fail("Impossible d’ouvrir le projet créé dans {$target}.");
    }
    installView($viewVersion, $offline);
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
    if (!is_file($root . '/runtime/autoload.php')) {
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

function buildProject(bool $skipTests = false): never
{
    $root = projectRoot();
    if (!$skipTests) {
        $testCode = executeProjectTests($root);
        if ($testCode !== 0) fail('Le build est annulé car les tests ont échoué.', $testCode);
    }
    if (!is_file($root . '/public/.htaccess')) {
        fail('public/.htaccess est absent : les URL propres ne peuvent pas être garanties.');
    }
    $rules = (string) file_get_contents($root . '/public/.htaccess');
    if (!str_contains($rules, 'RewriteRule ^ index.php')) {
        fail('public/.htaccess ne redirige pas les routes vers index.php.');
    }

    $outputRoot = $root . '/output';
    if (!is_dir($outputRoot) && !mkdir($outputRoot, 0775, true) && !is_dir($outputRoot)) {
        fail('Impossible de créer le dossier output.');
    }
    $archive = $outputRoot . '/phpaml-build.zip';
    $zip = new ZipArchive();
    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail('Impossible de créer output/phpaml-build.zip.');
    }
    $excludedRoots = ['.git', '.env', 'tests', 'output', 'tmp', 'tools', 'readme', 'deliverables'];
    $excludedExtensions = ['log', 'sqlite', 'sqlite3', 'bak'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    $files = [];
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $top = explode('/', $relative, 2)[0];
        if (in_array($top, $excludedRoots, true)
            || str_starts_with($relative, 'runtime/storage/debug-')
            || str_starts_with($relative, 'runtime/storage/log')
            || in_array(strtolower($file->getExtension()), $excludedExtensions, true)) continue;
        $zip->addFile($file->getPathname(), $relative);
        $files[] = $relative;
    }
    sort($files);
    $manifest = [
        'built_at' => date(DATE_ATOM),
        'aml_version' => projectInfo(PHPAML_FRAMEWORK_ROOT)['version'] ?? null,
        'project' => projectInfo($root)['name'] ?? basename($root),
        'entrypoint' => 'public/index.php',
        'document_root' => 'public',
        'clean_urls' => true,
        'files' => $files,
    ];
    $zip->addFromString('build-manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    if (!$zip->close() || !is_file($archive) || filesize($archive) === 0) {
        @unlink($archive);
        @unlink($archive . '.sha256');
        @unlink($outputRoot . '/manifest.json');
        fail('Impossible de finaliser l’archive de production. Vérifiez l’espace disque disponible et les permissions du dossier output.');
    }
    $checksum = hash_file('sha256', $archive);
    if (!is_string($checksum)
        || file_put_contents($archive . '.sha256', "{$checksum}  phpaml-build.zip\n", LOCK_EX) === false
        || file_put_contents($outputRoot . '/manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX) === false) {
        @unlink($archive);
        @unlink($archive . '.sha256');
        @unlink($outputRoot . '/manifest.json');
        fail('Impossible d’écrire les fichiers du build. Vérifiez l’espace disque disponible et les permissions du dossier output.');
    }
    output('✓ Build créé : output/phpaml-build.zip');
    output('✓ Checksum : output/phpaml-build.zip.sha256');
    output('Document root: public/ — URL propres activées (/about, sans index.php).');
    exit(0);
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
        $declaredFramework = $project['runtime']['framework'] ?? $project['aml']['framework'] ?? null;
        if (is_string($declaredFramework) && trim($declaredFramework) !== '') {
            $version = ltrim(trim($declaredFramework), 'v');
        }
    }
    $composer = composerCommand();
    $frameworkVersion = installFramework($root, $version, $refresh, $offline);
    output('Préparation de l’environnement runtime/…');
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
        $root . '/runtime/aml-installed.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
    output('Environnement AML installé avec succès.');
    exit(0);
}

function composerCommand(): string
{
    $override = trim((string) (getenv('AML_COMPOSER_BINARY') ?: ''));
    if ($override !== '') {
        if (!is_file($override) || !is_executable($override)) {
            fail('Le binaire Composer configuré est introuvable ou non exécutable.');
        }
        return escapeshellarg($override);
    }
    $bundledComposer = PHPAML_FRAMEWORK_ROOT . '/runtime/composer/composer.phar';
    if (is_file($bundledComposer)) {
        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bundledComposer);
    }
    $systemComposer = trim((string) shell_exec('command -v composer 2>/dev/null'));
    if ($systemComposer === '') {
        fail("Composer privé est absent de l'installation AML.");
    }
    return escapeshellarg($systemComposer);
}

/** @param array<string, mixed> $manifest */
function writeProjectManifest(string $root, array $manifest): void
{
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents(projectManifestPath($root), $encoded . PHP_EOL, LOCK_EX) === false) {
        fail('Impossible de modifier phpaml.json.');
    }
}

function writeNewFile(string $root, string $relative, string $content): bool
{
    $path = $root . '/' . $relative;
    if (is_file($path)) {
        return false;
    }
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) {
        fail("Impossible de créer " . dirname($relative) . '.');
    }
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        fail("Impossible de créer {$relative}.");
    }
    output("Créé : {$relative}");
    return true;
}

function installView(?string $version = null, bool $offline = false): never
{
    $root = projectRoot();
    if (!is_file($root . '/composer.json')) {
        fail('Le fichier composer.json est introuvable.');
    }
    $constraint = $version === null ? '^0.1.0-beta.3' : ltrim(trim($version), 'v');
    if (preg_match('/^[0-9A-Za-z.*^~<>=|@+_.-]+$/', $constraint) !== 1) {
        fail('La version AML View est invalide.');
    }

    if (!is_file($root . '/runtime/framework/Autoloader.php') || !is_file($root . '/runtime/aml-installed.json')) {
        output('Le moteur PHPAML est absent : installation automatique…');
        $installCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' install'
            . ($offline ? ' --offline' : '');
        passthru('cd ' . escapeshellarg($root) . ' && ' . $installCommand, $installExitCode);
        if ($installExitCode !== 0) {
            fail('L’installation automatique du moteur PHPAML a échoué.');
        }
    }

    // AML View applications follow a source-first layout, similar to modern
    // file-based web frameworks. The classic PHPAML application remains in
    // app/, while create-view-app migrates all application code into src/.
    if (is_dir($root . '/app') && !file_exists($root . '/src')) {
        if (!rename($root . '/app', $root . '/src')) {
            fail('Impossible de migrer app vers src.');
        }
        output('Migré : app/ → src/');
    }

    $publicImages = $root . '/public/img';
    $publicAssets = $root . '/public/assets';
    if (!is_dir($publicAssets) && !mkdir($publicAssets, 0755, true) && !is_dir($publicAssets)) {
        fail('Impossible de créer public/assets.');
    }
    if (is_dir($publicImages)) {
        foreach (['favicon.svg', 'phpaml-logo-violet-lime.png'] as $publicDocument) {
            $source = $publicImages . '/' . $publicDocument;
            $destination = $root . '/public/' . $publicDocument;
            if (is_file($source) && !file_exists($destination) && !rename($source, $destination)) {
                fail("Impossible de déplacer {$publicDocument} à la racine de public/.");
            }
        }
        if (count(scandir($publicImages) ?: []) === 2) {
            rmdir($publicImages);
        }
    }
    foreach (['public/css/index.css', 'public/js/main.js'] as $legacyAsset) {
        $legacyPath = $root . '/' . $legacyAsset;
        if (is_file($legacyPath)) {
            unlink($legacyPath);
            $legacyDirectory = dirname($legacyPath);
            if (is_dir($legacyDirectory) && count(scandir($legacyDirectory) ?: []) === 2) {
                rmdir($legacyDirectory);
            }
        }
    }

    $legacyServerRoot = $root . '/src/server';
    foreach (['Controllers' => 'controllers', 'Models' => 'models', 'Middleware' => 'middleware'] as $serverDirectory => $targetDirectory) {
        $from = $legacyServerRoot . '/' . $serverDirectory;
        $to = $root . '/src/' . $targetDirectory;
        if (is_dir($from) && !file_exists($to) && !rename($from, $to)) {
            fail("Impossible de migrer src/server/{$serverDirectory} vers src/{$targetDirectory}.");
        }
    }
    if (is_dir($legacyServerRoot) && count(scandir($legacyServerRoot) ?: []) === 2) {
        rmdir($legacyServerRoot);
    }
    $classicViews = $root . '/src/views';
    if (is_dir($classicViews) && !is_file($classicViews . '/page.php') && !is_dir($classicViews . '/templates')) {
        $viewFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($classicViews, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($viewFiles as $viewFile) {
            $viewFile->isDir() ? rmdir($viewFile->getPathname()) : unlink($viewFile->getPathname());
        }
        if (!rmdir($classicViews)) {
            fail('Impossible de retirer les vues PHP classiques du projet AML View.');
        }
    }
    foreach (['Controllers' => 'controllers', 'Models' => 'models', 'Middleware' => 'middleware', 'Services' => 'services'] as $legacyDirectory => $sourceDirectory) {
        $legacyPath = $root . '/src/' . $legacyDirectory;
        $sourcePath = $root . '/src/' . $sourceDirectory;
        if (is_dir($legacyPath) && !file_exists($sourcePath) && !rename($legacyPath, $sourcePath)) {
            fail("Impossible de migrer src/{$legacyDirectory} vers src/{$sourceDirectory}.");
        }
        if (!is_dir($sourcePath)) {
            continue;
        }
        $sourceIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($sourceIterator as $sourceFile) {
            if (!$sourceFile->isFile() || strtolower($sourceFile->getExtension()) !== 'php') {
                continue;
            }
            $content = (string) file_get_contents($sourceFile->getPathname());
            $content = str_replace('App\\Server\\', 'App\\', $content);
            $content = str_replace(
                "return \$this->view('home.php', ['model' => new HomeModel()]);",
                "return \$this->json(['name' => (new HomeModel())->getName(), 'status' => 'ok']);",
                $content,
            );
            file_put_contents($sourceFile->getPathname(), $content, LOCK_EX);
        }
    }
    foreach (['controllers', 'models'] as $sourceDirectory) {
        $sourcePath = $root . '/src/' . $sourceDirectory;
        if (!is_dir($sourcePath) && !mkdir($sourcePath, 0755, true) && !is_dir($sourcePath)) {
            fail("Impossible de créer src/{$sourceDirectory}.");
        }
    }
    $configPath = $root . '/configs/app.php';
    if (is_file($configPath)) {
        $configContent = (string) file_get_contents($configPath);
        $configContent = str_replace('use App\\Server\\', 'use App\\', $configContent);
        $configContent = preg_replace("/^\s*'views_path'\s*=>.*\R/m", '', $configContent) ?? $configContent;
        $configContent = str_replace(["'GET /'", "'name' => 'home'"], ["'GET /api/health'", "'name' => 'api.health'"], $configContent);
        file_put_contents($configPath, $configContent, LOCK_EX);
    }

    $composerPath = $root . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
    $composer['autoload'] = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
    $composer['autoload']['psr-4'] = is_array($composer['autoload']['psr-4'] ?? null)
        ? $composer['autoload']['psr-4']
        : [];
    $composer['autoload']['psr-4']['App\\'] = 'src/';
    $composer['autoload']['psr-4']['App\\Views\\'] = 'src/views/';
    $composer['autoload']['psr-4']['App\\Views\\Pages\\'] = 'src/views/pages/';
    $composer['autoload']['psr-4']['App\\Views\\Components\\'] = 'src/views/components/';
    $composer['autoload']['psr-4']['App\\Views\\Layouts\\'] = 'src/views/layouts/';
    $composer['autoload']['psr-4']['App\\Views\\States\\'] = 'src/views/states/';
    $composer['autoload']['psr-4']['App\\Controllers\\'] = 'src/controllers/';
    $composer['autoload']['psr-4']['App\\Models\\'] = 'src/models/';
    $composer['autoload']['psr-4']['App\\Middleware\\'] = 'src/middleware/';
    $composer['autoload']['psr-4']['App\\Services\\'] = 'src/services/';
    unset($composer['autoload']['psr-4']['App\\Server\\']);
    file_put_contents(
        $composerPath,
        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL,
        LOCK_EX,
    );

    $publicIndexPath = $root . '/public/index.php';
    if (is_file($publicIndexPath)) {
        $publicIndex = (string) file_get_contents($publicIndexPath);
        $publicIndex = str_replace("'App\\\\' => \$root . '/app'", "'App\\\\' => \$root . '/src'", $publicIndex);
        file_put_contents($publicIndexPath, $publicIndex, LOCK_EX);
    }

    output('Installation de phpaml/view…');
    $command = 'cd ' . escapeshellarg($root) . ' && ' . composerCommand()
        . ' require ' . escapeshellarg('phpaml/view:' . $constraint)
        . ' ' . escapeshellarg('phpaml/engine:^0.1@beta')
        . ' --no-interaction --prefer-dist --no-progress';
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fail('L’installation Composer de phpaml/view a échoué.');
    }

    $legacyViewRoot = $root . '/src/View';
    $uiRoot = $root . '/src/views';
    if (is_dir($legacyViewRoot) && !file_exists($uiRoot)) {
        if (!rename($legacyViewRoot, $uiRoot)) {
            fail('Impossible de migrer app/View vers app/UI.');
        }
        output('Migré : src/View → src/views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uiRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            $content = str_replace(['namespace App\\View', 'use App\\View'], ['namespace App\\Views', 'use App\\Views'], $content);
            file_put_contents($file->getPathname(), $content, LOCK_EX);
        }
    }

    foreach (['src/views/pages/home', 'src/views/components', 'src/views/layouts', 'src/views/states', 'src/views/themes/light', 'src/views/themes/dark'] as $directory) {
        if (!is_dir($root . '/' . $directory) && !mkdir($root . '/' . $directory, 0755, true) && !is_dir($root . '/' . $directory)) {
            fail("Impossible de créer {$directory}.");
        }
    }

    writeNewFile($root, 'src/views/pages/home/page.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Views\Pages\Home;

use AML\Engine\ClientAction;
use AML\Engine\Api;
use AML\Engine\Actions;
use AML\Engine\StateRef;
use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\Persisted;
use AML\View\Shared;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, Each, Element, Grid, Group, Heading, Image, Input, Link, MainContent, Section, Text, VStack};

final class HomePage extends Page
{
    #[State, Shared('demo.count'), Persisted('local', 'phpaml.demo.count')]
    public int $count = 0;

    #[State]
    public string $counterMessage = 'Ready';

    #[State]
    public bool $detailsOpen = false;

    #[State]
    public string $name = 'Builder';

    #[State]
    public string $apiStatus = 'Not checked';

    #[State]
    public string $apiError = '';

    #[State]
    public bool $apiLoading = false;

    #[State]
    public array $tasks = ['Learn AML View', 'Build an interface'];

    #[State]
    public string $newTask = '';

    public function metadata(): PageMetadata
    {
        return (new PageMetadata())
            ->title('PHPAML View — Reactive interfaces in PHP')
            ->description('A declarative PHP interface with browser-managed state and reusable components.')
            ->openGraph(image: '/phpaml-logo-violet-lime.png')
            ->twitter(image: '/phpaml-logo-violet-lime.png');
    }

    public function body(): View
    {
        return MainContent(Group(
                Section(
                    VStack(
                        Text('PHPAML · AML VIEW')->class('eyebrow'),
                        Heading('Build reactive web interfaces. In PHP.')->bold(),
                        Text('A declarative interface layer with local reactive state, reusable components and no JavaScript framework required.')
                            ->class('hero-copy'),
                        Element('div',
                            Link('Read the source', 'https://github.com/MR-C0DE/phpaml-view')->class('button', 'button-secondary'),
                            Link('Start building', '#demo')->class('button', 'button-primary'),
                        )->class('hero-actions'),
                    )->gap(20)->class('hero-content'),
                    Image('/phpaml-logo-violet-lime.png', 'PHPAML mammoth logo')->class('hero-logo'),
                )->class('view-hero', 'shell'),
                Section(
                    Text('LIVE FRONTEND STATE')->class('eyebrow'),
                    Heading('One click. One state cycle.', 2)->bold(),
                    Text('The counter is rendered by AML View and updated through a signed interaction.')
                        ->class('demo-copy'),
                    Element('div',
                        Text(StateRef::to('count', $this->count))->class('counter-value'),
                        Button('Add one')
                            ->onClick(Actions::sequence(
                                ClientAction::increment('count'),
                                ClientAction::set('counterMessage', 'Updated locally'),
                            ))
                            ->loadingLabel('Updating…')
                            ->class('button', 'button-primary', 'counter-button'),
                        Button('Smart reset')
                            ->onClick(Actions::when(
                                'count',
                                'gt',
                                0,
                                Actions::sequence(
                                    ClientAction::set('count', 0),
                                    ClientAction::set('counterMessage', 'Reset complete'),
                                ),
                                ClientAction::set('counterMessage', 'Already at zero'),
                            ))
                            ->disabledWhen(StateRef::to('count', $this->count), 0)
                            ->class('button', 'button-secondary'),
                        Text(StateRef::to('counterMessage', $this->counterMessage))->class('counter-message'),
                    )->component('counter')->class('counter-card'),
                    Button('Toggle details')
                        ->onClick(ClientAction::toggle('detailsOpen'))
                        ->classWhen(StateRef::to('detailsOpen', $this->detailsOpen), 'is-active')
                        ->class('button', 'button-secondary', 'details-toggle'),
                    Element('div',
                        Heading('Reactive presentation', 3),
                        Text('Visibility and CSS classes are controlled by client state.'),
                    )
                        ->showWhen(StateRef::to('detailsOpen', $this->detailsOpen))
                        ->class('details-panel'),
                    Element('div',
                        Input('name', value: $this->name)
                            ->bindClient('name')
                            ->required('Please enter your name.')
                            ->minLength(2)
                            ->attribute('aria-label', 'Your name')
                            ->class('local-input'),
                        Text(StateRef::to('name', $this->name))->class('local-preview'),
                    )->component('profile-form')->class('local-form-card'),
                    Element('div',
                        Button('Check API')
                            ->onClick(
                                Api::get('/api/health')
                                    ->storeIn('apiStatus', 'status')
                                    ->errorIn('apiError')
                                    ->loadingIn('apiLoading')
                            )
                            ->class('button', 'button-secondary'),
                        Text(StateRef::to('apiStatus', $this->apiStatus))->class('api-status'),
                        Text(StateRef::to('apiError', $this->apiError))->class('api-error'),
                    )->class('api-card'),
                    Element('div',
                        Heading('Reactive collection', 3),
                        Input('newTask')
                            ->bindClient('newTask')
                            ->attribute('placeholder', 'New task')
                            ->attribute('aria-label', 'New task')
                            ->class('local-input'),
                        Button('Add task')
                            ->onClick(Actions::sequence(
                                ClientAction::append('tasks', StateRef::to('newTask')),
                                ClientAction::set('newTask', ''),
                            ))
                            ->disabledWhen(StateRef::to('newTask', $this->newTask), '')
                            ->class('button', 'button-primary'),
                        Button('Remove first')
                            ->onClick(ClientAction::removeAt('tasks', 0))
                            ->class('button', 'button-secondary'),
                        Button('Clear tasks')
                            ->onClick(ClientAction::clear('tasks'))
                            ->class('button', 'button-secondary'),
                        Each(StateRef::to('tasks', $this->tasks), label: '', key: ''),
                    )->component('task-list')->class('task-card'),
                )->attribute('id', 'demo')->class('view-demo', 'shell'),
                Grid(3,
                    self::feature('01', 'Declarative PHP', 'Compose pages with readable PHP objects instead of mixing templates and scripts.'),
                    self::feature('02', 'Local interactions', 'State updates run instantly in PHPAML Engine without contacting the server.'),
                    self::feature('03', 'Progressive adoption', 'Use AML View where it helps and keep classic PHPAML views everywhere else.'),
                )->class('view-features', 'shell'),
            ))->class('view-content');
    }

    private static function feature(string $number, string $title, string $description): View
    {
        return VStack(
            Text($number)->class('feature-number'),
            Heading($title, 3)->bold(),
            Text($description),
        )->gap(14)->class('view-feature');
    }
}

PHP
    );
    writeNewFile($root, 'src/views/pages/about/page.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Views\Pages\About;

use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\Shared;
use AML\View\State;
use AML\View\View;
use AML\Engine\StateRef;
use function AML\View\{Heading, Link, Text, VStack};

final class AboutPage extends Page
{
    #[State, Shared('demo.count')]
    public int $count = 0;

    public function metadata(): PageMetadata
    {
        return (new PageMetadata())->title('About — PHPAML View')->description('Frontend navigation powered by PHPAML Engine.');
    }

    public function body(): View
    {
        return VStack(
            Text('CLIENT ROUTER')->class('eyebrow'),
            Heading('Navigation without a page reload')->size(48)->bold(),
            Text('PHPAML Engine loads this route, updates the document history and keeps the frontend runtime mounted.'),
            Text(StateRef::to('count', $this->count))->class('shared-counter'),
            Link('Return home', '/')->class('button', 'button-primary'),
        )->gap(20)->padding(40)->class('shell');
    }
}
PHP
    );
    writeNewFile($root, 'src/views/components/Navigation.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Views\Components;

use AML\View\Component;
use AML\View\View;
use function AML\View\{Element, Image, Link, Text, ThemeSwitcher};

final class Navigation extends Component
{
    public function body(): View
    {
        return Element('header',
            Element('nav',
                Element('a',
                    Image('/phpaml-logo-violet-lime.png', '')->class('view-brand-logo'),
                    Element('strong', Text('PHPAML')),
                )->attribute('href', '/')->class('view-brand')->attribute('aria-label', 'PHPAML home'),
                Element('div',
                    Link('About', '/about'),
                    Link('Docs', 'https://phpaml.com/docs'),
                    Link('GitHub', 'https://github.com/MR-C0DE/phpaml-view')->class('nav-github'),
                    ThemeSwitcher('light', 'dark', 'system')->class('theme-switcher'),
                )->class('view-nav-links'),
            )->class('view-nav', 'shell'),
        )->class('view-header');
    }
}

function Navigation(): Navigation
{
    return new Navigation();
}
PHP
    );
    writeNewFile($root, 'src/views/layouts/AppLayout.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Views\Layouts;

use AML\View\Layout;
use AML\View\View;
use function App\Views\Components\Navigation;
use function AML\View\{Element, Link, Slot, Text, ThemeProvider, VStack};

final class AppLayout extends Layout
{
    public function body(): View
    {
        return ThemeProvider(
            default: 'system',
            content: VStack(
                Navigation(),
                Slot(),
                Element('footer',
                    Text('PHPAML View · Built with PHPAML')->class('footer-copy'),
                    Link('Documentation', 'https://phpaml.com/docs'),
                )->class('view-footer', 'shell'),
            )->class('view-app'),
            themes: ['light', 'dark'],
        );
    }
}
PHP
    );
    writeNewFile($root, 'src/views/states/NotFound.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Views\States;

use AML\View\Page;
use AML\View\View;
use function AML\View\{Heading, Link, Text, VStack};

final class NotFoundPage extends Page
{
    public function body(): View
    {
        return VStack(
            Text('404')->class('eyebrow'),
            Heading('This page does not exist.')->size(48)->bold(),
            Text('No AML View page matches ' . $this->param('path', '/')),
            Link('Return home', '/')->class('button', 'button-primary'),
        )->gap(20)->padding(40)->class('shell', 'route-state');
    }
}
PHP
    );
    writeNewFile($root, 'src/views/states/Loading.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Views\States;

use AML\View\Page;
use AML\View\View;
use function AML\View\{Text, VStack};

final class LoadingPage extends Page
{
    public function body(): View
    {
        return VStack(
            Text('Loading…')->class('loading-title'),
            Text('AML View is preparing the next interface.'),
        )->gap(12)->padding(40)->class('shell', 'route-state');
    }
}
PHP
    );
    writeNewFile($root, 'src/views/states/Error.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Views\States;

use AML\View\Page;
use AML\View\View;
use function AML\View\{Heading, Link, Text, VStack};

final class ErrorPage extends Page
{
    public function body(): View
    {
        return VStack(
            Text('APPLICATION ERROR')->class('eyebrow'),
            Heading('Something went wrong.')->size(48)->bold(),
            Text('The incident was contained. You can safely try again.'),
            Link('Return home', '/')->class('button', 'button-primary'),
        )->gap(20)->padding(40)->class('shell', 'route-state');
    }
}
PHP
    );
    writeNewFile($root, 'src/views/stylesheets/base.css', <<<'CSS'
:root { color-scheme: dark; --bg:#09070d; --panel:#15111f; --ink:#f8f7ff; --muted:#aaa6ba; --line:rgba(255,255,255,.11); --violet:#9b72ff; --lime:#c7ff3d; font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
* { box-sizing:border-box; }
html { scroll-behavior:smooth; }
body { margin:0; overflow-x:hidden; color:var(--ink); background:radial-gradient(circle at 78% 12%,rgba(139,92,246,.2),transparent 30rem),radial-gradient(circle at 12% 60%,rgba(199,255,61,.06),transparent 26rem),var(--bg); }
a { color:inherit; }
.shell { width:min(74rem,calc(100% - 3rem)); margin-inline:auto; }
.button { min-height:3.1rem; padding:0 1.2rem; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--line); border-radius:.55rem; font:inherit; font-size:.9rem; font-weight:850; text-decoration:none; cursor:pointer; transition:transform .18s ease,filter .18s ease; }
.button:hover { transform:translateY(-2px); }
.button-primary { border-color:var(--lime); color:#17110d; background:var(--lime); }
.button-secondary { background:rgba(255,255,255,.035); }
@media (max-width:38rem) { .shell { width:min(100% - 1.4rem,74rem); } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/components/navigation.css', <<<'CSS'
.view-header { position:relative; z-index:10; border-bottom:1px solid var(--line); background:rgba(9,7,13,.78); backdrop-filter:blur(18px); }
.view-nav { min-height:4.8rem; display:flex; align-items:center; gap:.7rem; }
.view-brand { display:inline-flex; align-items:center; gap:.7rem; font-weight:900; letter-spacing:.055em; text-decoration:none; }
.view-brand-logo { width:2.35rem; height:2.35rem; object-fit:contain; }
.view-nav-links { margin-left:auto; display:flex; align-items:center; gap:1.25rem; }
.view-nav-links a { color:var(--muted); font-size:.9rem; font-weight:750; text-decoration:none; }
.view-nav-links .nav-github { padding:.65rem .9rem; border:1px solid var(--line); border-radius:.55rem; color:var(--ink); background:rgba(255,255,255,.035); }
.theme-switcher { display:flex; gap:.25rem; padding:.2rem; border:1px solid var(--line); border-radius:.55rem; }
.theme-switcher button { padding:.4rem .55rem; border:0; border-radius:.35rem; color:var(--muted); background:transparent; cursor:pointer; }
.theme-switcher button[aria-pressed="true"] { color:var(--ink); background:var(--panel); }
@media (max-width:38rem) { .view-nav { min-height:4.25rem; } .view-nav-links > a:first-child { display:none; } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/layouts/app.css', <<<'CSS'
.view-app { min-height:100vh; }
.view-footer { padding-block:2.25rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; border-top:1px solid var(--line); color:var(--muted); font-size:.8rem; }
.view-footer a { color:var(--ink); text-decoration:none; }
@media (max-width:38rem) { .view-footer { align-items:flex-start; flex-direction:column; } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/pages/home.css', <<<'CSS'
.view-hero { min-height:39rem; padding-block:5rem; display:grid; grid-template-columns:1.15fr .85fr; align-items:center; gap:4rem; }
.hero-content { min-width:0; }
.eyebrow { color:var(--lime); font:.76rem/1.2 ui-monospace,SFMono-Regular,Menlo,monospace; font-weight:850; letter-spacing:.15em; }
.view-hero h1 { max-width:48rem; margin:0; font-size:clamp(3.2rem,6.8vw,6rem); line-height:.94; letter-spacing:-.065em; }
.hero-copy,.demo-copy { max-width:42rem; color:var(--muted); font-size:clamp(1rem,2vw,1.15rem); }
.hero-actions { display:flex; flex-wrap:wrap; gap:.8rem; }
.hero-logo { width:min(100%,25rem); height:auto; justify-self:center; filter:drop-shadow(0 2rem 4rem rgba(0,0,0,.45)); }
.view-demo { padding-block:5.5rem; border-top:1px solid var(--line); }
.view-demo h2 { margin:.75rem 0 1rem; font-size:clamp(2.2rem,5vw,4rem); letter-spacing:-.055em; }
.counter-card { margin-top:2.3rem; padding:1.4rem; display:flex; align-items:center; gap:1rem; border:1px solid rgba(155,114,255,.45); border-radius:.85rem; background:linear-gradient(135deg,rgba(155,114,255,.16),rgba(255,255,255,.025)); }
.counter-value { min-width:5rem; font:700 clamp(2.4rem,8vw,5rem)/1 ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--violet); }
.counter-button { margin-left:auto; }
.counter-button:disabled { opacity:.65; cursor:wait; }
.counter-message { color:var(--muted); font-size:.85rem; }
.details-toggle { margin-top:1rem; }
.details-toggle.is-active { color:var(--bg); border-color:var(--lime); background:var(--lime); }
.details-panel { margin-top:1rem; padding:1.25rem; border-left:3px solid var(--lime); background:rgba(199,255,61,.06); }
.local-form-card { margin-top:1rem; padding:1.4rem; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); align-items:center; gap:1rem; border:1px solid var(--line); border-radius:.85rem; background:rgba(255,255,255,.025); }
.local-input { width:100%; padding:.85rem 1rem; border:1px solid var(--line); border-radius:.55rem; color:var(--ink); background:var(--panel); font:inherit; }
.local-preview { color:var(--lime); font-weight:800; }
.local-form-card [role="alert"] { grid-column:1/-1; color:#ff8585; font-size:.82rem; }
.api-card { margin-top:1rem; display:flex; align-items:center; flex-wrap:wrap; gap:1rem; }
.api-status { color:var(--lime); font-weight:800; }
.api-error { color:#ff8585; }
.task-card { margin-top:1rem; padding:1.4rem; display:flex; align-items:center; flex-wrap:wrap; gap:.75rem; border:1px solid var(--line); border-radius:.85rem; }
.task-card h3 { width:100%; margin:0; }
.task-card ul { width:100%; margin:.5rem 0 0; padding-left:1.25rem; color:var(--muted); }
.task-card li { padding:.25rem 0; }
.view-features { padding-block:1rem 6rem; gap:1rem !important; }
.view-feature { min-height:14rem; padding:1.5rem; border:1px solid var(--line); border-radius:.75rem; background:linear-gradient(145deg,rgba(255,255,255,.045),rgba(255,255,255,.012)); }
.view-feature h3 { margin:2rem 0 0; font-size:1.25rem; }
.view-feature > span:last-child { color:var(--muted); font-size:.92rem; }
.feature-number { color:var(--violet); font:800 .75rem/1 ui-monospace,SFMono-Regular,Menlo,monospace; }
@media (max-width:58rem) { .view-hero { min-height:auto; grid-template-columns:1fr; gap:1rem; } .hero-logo { width:min(60vw,20rem); } .view-features { grid-template-columns:1fr !important; } }
@media (max-width:38rem) { .view-hero { padding-block:3.5rem; } .view-hero h1 { font-size:clamp(2.75rem,14vw,4rem); overflow-wrap:anywhere; } .hero-actions { flex-direction:column; } .hero-actions .button { width:100%; } .counter-card { align-items:stretch; flex-direction:column; } .counter-button { width:100%; margin-left:0; } .local-form-card { grid-template-columns:1fr; } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/states/route-states.css', <<<'CSS'
.route-state { min-height:calc(100vh - 10rem); justify-content:center; }
.loading-title { color:var(--lime); font-size:1.15rem; font-weight:850; }
CSS
    );
    writeNewFile($root, 'src/views/themes/light/tokens.css', <<<'CSS'
[data-aml-theme="light"] { color-scheme:light; --bg:#f7f5fb; --panel:#ffffff; --ink:#17121f; --muted:#625d6d; --line:rgba(23,18,31,.14); --violet:#6d3de8; --lime:#7da800; }
CSS
    );
    writeNewFile($root, 'src/views/themes/dark/tokens.css', <<<'CSS'
[data-aml-theme="dark"] { color-scheme:dark; --bg:#09070d; --panel:#15111f; --ink:#f8f7ff; --muted:#aaa6ba; --line:rgba(255,255,255,.11); --violet:#9b72ff; --lime:#c7ff3d; }
CSS
    );
    writeNewFile($root, 'tests/aml-view.php', <<<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/runtime/autoload.php';

$tests = [
    'home page renders' => static function () use ($root): void {
        $application = new \AML\View\FileApplication($root . '/src/views');
        $result = $application->mount('/');
        if (!$result instanceof \AML\View\PageResult) {
            throw new RuntimeException('The home route did not return a page.');
        }
        if (!str_contains($result->rootHtml(), 'Build reactive web interfaces')) {
            throw new RuntimeException('The home page content is missing.');
        }
    },
    'about route renders' => static function () use ($root): void {
        $result = (new \AML\View\FileApplication($root . '/src/views'))->mount('/about');
        if (!$result instanceof \AML\View\PageResult) {
            throw new RuntimeException('The about route did not return a page.');
        }
    },
    'stylesheets are collected' => static function () use ($root): void {
        $styles = (new \AML\View\FileApplication($root . '/src/views'))->styles();
        if (!str_contains($styles, '.view-hero') || !str_contains($styles, '.view-nav')) {
            throw new RuntimeException('AML View stylesheets were not collected.');
        }
    },
    'unknown routes use the declarative 404 page' => static function () use ($root): void {
        $application = new \AML\View\FileApplication($root . '/src/views');
        try {
            $application->mount('/missing-page');
            throw new RuntimeException('An unknown route should not resolve.');
        } catch (OutOfBoundsException) {
            $html = $application->notFound('/missing-page');
            if (!str_contains($html, 'This page does not exist')) {
                throw new RuntimeException('The declarative 404 page is missing.');
            }
        }
    },
];

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "✓ AML View: {$name}" . PHP_EOL);
    } catch (Throwable $error) {
        fwrite(STDERR, "✗ AML View: {$name}: {$error->getMessage()}" . PHP_EOL);
        $failed++;
    }
}

exit($failed === 0 ? 0 : 1);
PHP
    );
    $indexPath = $root . '/public/index.php';
    $index = is_file($indexPath) ? (string) file_get_contents($indexPath) : '';
    $marker = "// AML View integration\n";
    if (!str_contains($index, $marker)) {
        $anchor = '$config = require $root . \'/configs/app.php\';';
        if (!str_contains($index, $anchor)) {
            fail("public/index.php ne contient pas le point d’intégration attendu.");
        }
        $integration = <<<'PHP'
// AML View integration
$viewApp = new \AML\View\FileApplication($root . '/src/views');
if ($requestPath === '/_aml/styles.css') {
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: no-cache');
    echo $viewApp->styles();
    return;
}
try {
    $result = $viewApp->mount($requestPath);
} catch (OutOfBoundsException) {
    if (preg_match('#^/api(?:/|$)#', $requestPath)) {
        $result = null;
    } else {
        http_response_code(404);
        $result = $viewApp->notFound($requestPath);
    }
} catch (Throwable $error) {
    http_response_code(500);
    $result = $viewApp->error($requestPath, $error);
}
if ($result !== null) {
    ?><!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?= $result instanceof \AML\View\PageResult ? $viewApp->head($requestPath) : '' ?>
        <link rel="icon" href="/favicon.svg">
        <link rel="stylesheet" href="/_aml/styles.css">
    </head>
    <body>
    <?= $result instanceof \AML\View\PageResult ? $result->rootHtml() : $result ?>
    <?= \AML\Engine\EngineRuntime::script() ?>
    </body>
    </html><?php
    return;
}

PHP;
        $index = str_replace($anchor, $integration . $anchor, $index);
        if (file_put_contents($indexPath, $index, LOCK_EX) === false) {
            fail('Impossible de brancher AML View dans public/index.php.');
        }
        output('Modifié : public/index.php');
    } else {
        $migratedIndex = str_replace(
            ['/app/View/interaction.php', '/app/View/page.php'],
            ['/src/app/interaction.php', '/src/app/page.php'],
            $index
        );
        if ($migratedIndex !== $index) {
            file_put_contents($indexPath, $migratedIndex, LOCK_EX);
            output('Migré : intégration public/index.php vers app/UI');
        }
    }

    $manifest = projectInfo($root);
    $manifest['modules'] = is_array($manifest['modules'] ?? null) ? $manifest['modules'] : [];
    $manifest['modules']['view'] = ['package' => 'phpaml/view', 'constraint' => $constraint, 'mode' => 'frontend'];
    $manifest['modules']['engine'] = ['package' => 'phpaml/engine', 'mode' => 'client'];
    writeProjectManifest($root, $manifest);
    output('✓ AML View installé. Ouvrez / pour tester la page interactive.');
    exit(0);
}

function installI18n(?string $version = null): never
{
    $root = projectRoot();
    $constraint = $version === null ? '^0.1@beta' : ltrim(trim($version), 'v');
    if (preg_match('/^[0-9A-Za-z.*^~<>=|@+_.-]+$/', $constraint) !== 1) {
        fail('La version PHPAML i18n est invalide.');
    }
    output('Installation de phpaml/i18n…');
    $command = 'cd ' . escapeshellarg($root) . ' && ' . composerCommand()
        . ' require ' . escapeshellarg('phpaml/i18n:' . $constraint)
        . ' --no-interaction --prefer-dist --no-progress';
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fail('L’installation Composer de phpaml/i18n a échoué.');
    }
    foreach (['en', 'fr'] as $locale) {
        writeNewFile($root, "src/locales/{$locale}/common.json", (string) json_encode([
            'welcome' => $locale === 'fr' ? 'Bienvenue' : 'Welcome',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
    }
    envSet('APP_LOCALE', 'en');
    envSet('APP_FALLBACK_LOCALE', 'fr');
    $indexPath = $root . '/public/index.php';
    $index = is_file($indexPath) ? (string) file_get_contents($indexPath) : '';
    $marker = "// PHPAML i18n integration\n";
    if ($index !== '' && !str_contains($index, $marker)) {
        $anchor = "\\PHPAML\\Config\\Env::load(\$root . '/.env');";
        $integration = <<<'PHP'
// PHPAML i18n integration
if (class_exists(\AML\I18n\I18n::class)) {
    \AML\I18n\I18n::configure(
        $root . '/src/locales',
        (string) \PHPAML\Config\Env::get('APP_LOCALE', 'en'),
        (string) \PHPAML\Config\Env::get('APP_FALLBACK_LOCALE', 'fr'),
    );
}
PHP;
        if (!str_contains($index, $anchor)) {
            fail("public/index.php ne contient pas le point d’intégration i18n attendu.");
        }
        $index = str_replace($anchor, $anchor . PHP_EOL . $integration, $index);
        if (file_put_contents($indexPath, $index, LOCK_EX) === false) {
            fail('Impossible de brancher PHPAML i18n dans public/index.php.');
        }
        output('Modifié : public/index.php');
    }
    $manifest = projectInfo($root);
    $manifest['modules'] = is_array($manifest['modules'] ?? null) ? $manifest['modules'] : [];
    $manifest['modules']['i18n'] = ['package' => 'phpaml/i18n', 'version' => $constraint];
    writeProjectManifest($root, $manifest);
    output('PHPAML i18n installé avec succès.');
    exit(0);
}

function i18nLocale(string $locale): string
{
    if (preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $locale) !== 1) {
        fail("Langue invalide : {$locale}");
    }
    return $locale;
}

/** @return array<string, true> */
function i18nKeys(string $directory): array
{
    $keys = [];
    if (!is_dir($directory)) {
        return $keys;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
        $prefix = str_replace('/', '.', substr($relative, 0, -5));
        try {
            $decoded = json_decode((string) file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            fail("JSON invalide dans {$relative} : {$error->getMessage()}");
        }
        $flatten = static function (array $values, string $path) use (&$flatten, &$keys): void {
            foreach ($values as $key => $value) {
                $current = $path . '.' . $key;
                is_array($value) ? $flatten($value, $current) : $keys[$current] = true;
            }
        };
        $flatten(is_array($decoded) ? $decoded : [], $prefix);
    }
    ksort($keys);
    return $keys;
}

function i18nAdd(string $locale): never
{
    $locale = i18nLocale($locale);
    $root = projectRoot();
    writeNewFile($root, "src/locales/{$locale}/common.json", "{}\n");
    output("Langue ajoutée : {$locale}");
    exit(0);
}

function i18nList(): never
{
    $directory = projectRoot() . '/src/locales';
    $locales = [];
    if (is_dir($directory)) {
        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isDir() && !$entry->isDot()) {
                $locales[] = $entry->getFilename();
            }
        }
    }
    sort($locales);
    output($locales === [] ? 'Aucune langue configurée.' : implode(PHP_EOL, $locales));
    exit(0);
}

function i18nCheck(?string $onlyLocale = null): never
{
    $root = projectRoot() . '/src/locales';
    $directories = glob($root . '/*', GLOB_ONLYDIR) ?: [];
    $catalogues = [];
    foreach ($directories as $directory) {
        $locale = basename($directory);
        $catalogues[$locale] = i18nKeys($directory);
    }
    if ($catalogues === []) {
        fail("Aucune traduction trouvée dans src/locales.");
    }
    if ($onlyLocale !== null && !isset($catalogues[i18nLocale($onlyLocale)])) {
        fail("La langue '{$onlyLocale}' est absente de src/locales.");
    }
    $all = [];
    foreach ($catalogues as $keys) {
        $all += $keys;
    }
    $errors = 0;
    foreach ($catalogues as $locale => $keys) {
        if ($onlyLocale !== null && $locale !== i18nLocale($onlyLocale)) {
            continue;
        }
        $missing = array_keys(array_diff_key($all, $keys));
        if ($missing === []) {
            output("✓ {$locale} — " . (currentLanguage() === 'fr' ? 'complet' : 'complete'));
            continue;
        }
        $errors += count($missing);
        output("✗ {$locale} — " . count($missing) . (currentLanguage() === 'fr' ? ' traduction(s) manquante(s)' : ' missing translation(s)'));
        foreach ($missing as $key) {
            output("  - {$key}");
        }
    }
    exit($errors === 0 ? 0 : 1);
}

/** @return array{version: string, archive: string} */
function acquireFramework(?string $version, bool $refresh, bool $offline): array
{
    $cacheRoot = amlCacheRoot() . '/framework';
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
    $destination = $projectRoot . '/runtime/framework';
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
    foreach ([$projectRoot . '/runtime/storage', $projectRoot . '/runtime/storage/cache'] as $runtimeDirectory) {
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

/** @param list<string> $arguments */
function installData(array $arguments): never
{
    $root = projectRoot();
    if (!is_file($root . '/composer.json')) {
        fail('Le fichier composer.json est introuvable.');
    }

    $driver = strtolower(optionValue($arguments, '--driver') ?? 'sqlite');
    $driver = match ($driver) {
        'mongo' => 'mongodb',
        'postgres', 'postgresql' => 'pgsql',
        default => $driver,
    };
    if (!in_array($driver, ['sqlite', 'mysql', 'mariadb', 'pgsql', 'mongodb'], true)) {
        fail("Pilote de données inconnu : {$driver}. Utilisez sqlite, mysql, mariadb, pgsql ou mongodb.");
    }

    $packages = $driver === 'mongodb'
        ? ['phpaml/data:^0.1@alpha', 'phpaml/data-mongodb:^0.1@alpha']
        : ['phpaml/data:^0.1@alpha'];
    $packageList = implode(' ', $packages);
    output("Installation de {$packageList}…");
    $command = 'cd ' . escapeshellarg($root) . ' && ' . composerCommand()
        . ' require ' . implode(' ', array_map('escapeshellarg', $packages))
        . ' --no-interaction --prefer-dist';
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fail("L’installation Composer de {$packageList} a échoué.", $exitCode);
    }

    runDataCommand('data:install', ['--driver', $driver]);
}

/** @param list<string> $arguments */
function runDataCommand(string $command, array $arguments): never
{
    $root = projectRoot();
    $candidates = [
        projectRuntimePath($root) . '/bin/aml-data',
        projectRuntimePath($root) . '/phpaml/data/bin/aml-data',
        $root . '/vendor/phpaml/data/bin/aml-data',
        PHPAML_FRAMEWORK_ROOT . '/runtime/build/phpaml-data/bin/aml-data',
    ];
    foreach ($candidates as $binary) {
        if (!is_file($binary)) {
            continue;
        }
        $parts = array_map('escapeshellarg', array_merge([$command], $arguments));
        passthru('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binary) . ' ' . implode(' ', $parts), $exitCode);
        exit($exitCode);
    }
    fail("Le module phpaml/data est absent. Exécutez 'aml install data'.");
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
    $isViewApplication = isset(projectInfo($root)['modules']['view']);
    $sourceRoot = $isViewApplication ? 'src' : 'app';
    $namespaceRoot = 'App';
    if ($type === 'controller') {
        $class = className($name, 'Controller');
        $path = $isViewApplication ? "{$sourceRoot}/controllers/{$class}.php" : "{$sourceRoot}/Controllers/{$class}.php";
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespaceRoot}\\Controllers;\n\nuse PHPAML\\Http\\Request;\nuse PHPAML\\Http\\Response;\nuse PHPAML\\Mvc\\Controller;\n\nfinal class {$class} extends Controller\n{\n    public function index(Request \$request): Response\n    {\n        return \$this->json(['controller' => '{$class}']);\n    }\n}\n";
    } elseif ($type === 'model') {
        $class = className($name);
        $path = $isViewApplication ? "{$sourceRoot}/models/{$class}.php" : "{$sourceRoot}/Models/{$class}.php";
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespaceRoot}\\Models;\n\nfinal class {$class}\n{\n}\n";
    } else {
        $class = className($name, 'Middleware');
        $path = $isViewApplication ? "{$sourceRoot}/middleware/{$class}.php" : "{$sourceRoot}/Middleware/{$class}.php";
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespaceRoot}\\Middleware;\n\nuse Closure;\nuse PHPAML\\Http\\Request;\nuse PHPAML\\Http\\Response;\nuse PHPAML\\Middleware\\MiddlewareInterface;\n\nfinal class {$class} implements MiddlewareInterface\n{\n    public function process(Request \$request, Closure \$next): Response\n    {\n        return \$next(\$request);\n    }\n}\n";
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

function generateViewClass(string $type, string $name): void
{
    $root = projectRoot();
    $definitions = [
        'page' => ['suffix' => 'Page', 'parent' => 'Page'],
        'component' => ['suffix' => '', 'parent' => 'Component'],
        'layout' => ['suffix' => 'Layout', 'parent' => 'Layout'],
    ];
    if (!isset($definitions[$type])) {
        fail('Type AML View inconnu.');
    }
    $definition = $definitions[$type];
    $segments = array_values(array_filter(explode('/', str_replace('\\', '/', trim($name, '/\\')))));
    if ($segments === []) {
        fail('Le nom de route AML View est invalide.');
    }
    $routeSegments = [];
    $namespaceSegments = [];
    foreach ($segments as $segmentValue) {
        $dynamic = preg_match('/^\[(\.\.\.)?([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $segmentValue, $match) === 1;
        $sourceName = $dynamic ? $match[2] : $segmentValue;
        $namespaceSegments[] = className($sourceName);
        $routeSegments[] = $dynamic
            ? '[' . (($match[1] ?? '') === '...' ? '...' : '') . $match[2] . ']'
            : strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', className($sourceName)));
    }
    $baseClass = end($namespaceSegments);
    $class = className($baseClass, $definition['suffix']);
    if ($type === 'component') {
        if (count($segments) !== 1) {
            fail('Un composant AML View doit utiliser un nom simple.');
        }
        $path = "src/views/components/{$class}.php";
        $namespace = 'App\\Views\\Components';
    } elseif ($type === 'page') {
        $path = 'src/views/pages/' . implode('/', $routeSegments) . '/page.php';
        $namespace = 'App\\Views\\Pages\\' . implode('\\', $namespaceSegments);
    } else {
        $parentRoutes = array_slice($routeSegments, 0, -1);
        $parentNamespaces = array_slice($namespaceSegments, 0, -1);
        $path = 'src/views/layouts/'
            . ($parentRoutes === [] ? '' : implode('/', $parentRoutes) . '/')
            . $class . '.php';
        $namespace = 'App\\Views\\Layouts'
            . ($parentNamespaces === [] ? '' : '\\' . implode('\\', $parentNamespaces));
    }
    $fullPath = $root . '/' . $path;
    if (is_file($fullPath)) {
        fail("Le fichier '{$path}' existe déjà.");
    }
    $parent = $definition['parent'];
    if ($type === 'layout') {
        $body = "        return MainContent(Slot());";
        $functions = 'use function AML\\View\\{MainContent, Slot};';
    } elseif ($type === 'page') {
        $body = "        return VStack(\n            Heading('{$class}')->size(42)->bold(),\n            Text('Built with AML View.'),\n        )->gap(16)->padding(40);";
        $functions = 'use function AML\\View\\{Heading, Text, VStack};';
    } else {
        $body = "        return Text('{$class}');";
        $functions = 'use function AML\\View\\Text;';
    }
    $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse AML\\View\\{$parent};\nuse AML\\View\\View;\n{$functions}\n\nfinal class {$class} extends {$parent}\n{\n    public function body(): View\n    {\n{$body}\n    }\n}\n";
    if ($type === 'component') {
        $content .= "\nfunction {$class}(mixed ...\$arguments): {$class}\n{\n    return new {$class}(...\$arguments);\n}\n";
    }
    writeNewFile($root, $path, $content);
}

function generateViewState(string $type, string $route = '.'): void
{
    $states = [
        'loading' => ['file' => 'Loading.php', 'class' => 'LoadingPage', 'title' => 'Loading…', 'message' => 'AML View is preparing this interface.'],
        'error' => ['file' => 'Error.php', 'class' => 'ErrorPage', 'title' => 'Something went wrong.', 'message' => 'The incident was contained. You can safely try again.'],
        'not-found' => ['file' => 'NotFound.php', 'class' => 'NotFoundPage', 'title' => 'This page does not exist.', 'message' => 'No AML View page matches this address.'],
    ];
    if (!isset($states[$type])) {
        fail('Type d’état AML View inconnu.');
    }
    $segments = $route === '.' ? [] : array_values(array_filter(explode('/', str_replace('\\', '/', trim($route, '/\\')))));
    $namespaceSegments = [];
    $routeSegments = [];
    foreach ($segments as $segment) {
        if (preg_match('/^\[(\.\.\.)?([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $segment, $match)) {
            $namespaceSegments[] = className($match[2]);
            $routeSegments[] = '[' . (($match[1] ?? '') === '...' ? '...' : '') . $match[2] . ']';
            continue;
        }
        $namespaceSegments[] = className($segment);
        $routeSegments[] = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', className($segment)));
    }
    $namespace = 'App\\Views\\States' . ($namespaceSegments === [] ? '' : '\\' . implode('\\', $namespaceSegments));
    $directory = 'src/views/states' . ($routeSegments === [] ? '' : '/' . implode('/', $routeSegments));
    $state = $states[$type];
    $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse AML\\View\\Page;\nuse AML\\View\\View;\nuse function AML\\View\\{Heading, Text, VStack};\n\nfinal class {$state['class']} extends Page\n{\n    public function body(): View\n    {\n        return VStack(\n            Heading('{$state['title']}')->size(48)->bold(),\n            Text('{$state['message']}'),\n        )->gap(20)->padding(40);\n    }\n}\n";
    writeNewFile(projectRoot(), $directory . '/' . $state['file'], $content);
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
    $directory = projectRoot() . '/runtime/storage/cache';
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

function executeProjectTests(string $root): int
{
    $manifest = projectInfo($root);
    $viewMode = $manifest['modules']['view']['mode'] ?? null;
    if ($viewMode === 'frontend') {
        // Dependency test suites are repository-internal and may rely on their
        // own development layout. A generated application validates its public
        // integration through the project-level AML View suite instead.
        $suites = [$root . '/tests/aml-view.php'];
    } else {
        $suites = [$root . '/tests/run.php'];
    }

    foreach ($suites as $suite) {
        if (!is_file($suite)) {
            output("Suite de tests absente : {$suite}");
            return 1;
        }
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($suite), $exitCode);
        if ($exitCode !== 0) {
            return $exitCode;
        }
    }
    return 0;
}

function runTests(): never
{
    exit(executeProjectTests(projectRoot()));
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

/** @return array{status:string,message:string} */
function doctorDirectoryStatus(string $directory): array
{
    if (!is_dir($directory)) {
        return ['status' => 'error', 'message' => "{$directory} doit être accessible en écriture"];
    }
    if (is_writable($directory)) {
        return ['status' => 'ok', 'message' => 'accessible en écriture'];
    }

    $probe = rtrim($directory, '/\\') . '/.aml-doctor-' . bin2hex(random_bytes(4));
    $writeError = '';
    set_error_handler(static function (int $severity, string $message) use (&$writeError): bool {
        $writeError = $message;
        return true;
    });
    try {
        $written = file_put_contents($probe, 'aml', LOCK_EX);
    } finally {
        restore_error_handler();
    }
    if ($written !== false) {
        @unlink($probe);
        return ['status' => 'ok', 'message' => 'accessible en écriture'];
    }

    if (stripos($writeError, 'Operation not permitted') !== false) {
        return [
            'status' => 'warning',
            'message' => "{$directory} — environnement restreint : écriture interdite",
        ];
    }
    return ['status' => 'error', 'message' => "{$directory} doit être accessible en écriture"];
}

function doctor(?string $requestedPort, bool $offline, bool $json, bool $production = false): never
{
    $checks = [];
    $infoPath = PHPAML_FRAMEWORK_ROOT . '/phpaml.json';
    $infoContent = is_file($infoPath) ? file_get_contents($infoPath) : false;
    $info = json_decode($infoContent ?: '', true);
    $version = is_array($info) && is_string($info['version'] ?? null) ? $info['version'] : null;
    doctorAdd(
        $checks,
        $version !== null ? 'ok' : 'error',
        'AML',
        $version !== null ? "version {$version}" : 'phpaml.json absent ou invalide'
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
        ((string) (getenv('TMPDIR') ?: PHPAML_FRAMEWORK_ROOT . '/runtime/tmp')) => 'Dossier temporaire',
        amlCacheRoot() => 'Cache AML',
    ];
    foreach ($runtimeDirectories as $directory => $label) {
        $directoryStatus = doctorDirectoryStatus($directory);
        doctorAdd($checks, $directoryStatus['status'], $label, $directoryStatus['message']);
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
        $portStatus = 'ok';
        $portMessage = "127.0.0.1:{$port} disponible";
        if (!is_resource($socket)) {
            $portStatus = 'warning';
            if (stripos($error, 'Address already in use') !== false) {
                $portMessage = "127.0.0.1:{$port} occupé";
            } elseif (stripos($error, 'Operation not permitted') !== false
                || stripos($error, 'Permission denied') !== false) {
                $portMessage = "127.0.0.1:{$port} — ouverture interdite par l’environnement ({$error})";
            } else {
                $portMessage = "127.0.0.1:{$port} indisponible ({$error})";
            }
        }
        doctorAdd(
            $checks,
            $portStatus,
            'Port de développement',
            $portMessage
        );
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    $current = getcwd() ?: '';
    $isProject = is_file($current . '/phpaml.json') && is_file($current . '/public/index.php');
    if (!$isProject) {
        doctorAdd($checks, 'info', 'Projet', 'aucun projet PHPAML dans le dossier courant');
    } else {
        $projectInfoContent = file_get_contents($current . '/phpaml.json');
        $projectInfo = json_decode($projectInfoContent ?: '', true);
        doctorAdd($checks, is_array($projectInfo) ? 'ok' : 'error', 'Projet', $current);
        if (is_array($projectInfo) && isset($projectInfo['modules']['view'])) {
            foreach (['views', 'models', 'controllers'] as $requiredDirectory) {
                $present = is_dir($current . '/src/' . $requiredDirectory);
                doctorAdd(
                    $checks,
                    $present ? 'ok' : 'error',
                    'Structure AML View',
                    "src/{$requiredDirectory} — " . ($present ? 'dossier obligatoire présent' : 'dossier obligatoire absent')
                );
            }
        }
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
            $productionCache = $current . '/runtime/storage/cache';
            $cacheReady = is_dir($productionCache) && is_writable($productionCache);
            doctorAdd($checks, $cacheReady ? 'ok' : 'error', 'Cache de production', $cacheReady ? 'prêt et accessible en écriture' : 'runtime/storage/cache doit être prêt et accessible en écriture');
        }
        $framework = $current . '/runtime/framework/Autoloader.php';
        $autoload = $current . '/runtime/autoload.php';
        $installed = is_file($framework) && is_file($autoload);
        doctorAdd(
            $checks,
            $installed ? 'ok' : 'error',
            'Moteur du projet',
            $installed ? 'installé dans runtime' : "absent — exécutez 'aml install'"
        );
        $storage = $current . '/runtime/storage';
        doctorAdd(
            $checks,
            is_dir($storage) && is_writable($storage) ? 'ok' : 'error',
            'Stockage du projet',
            is_dir($storage) && is_writable($storage) ? 'accessible en écriture' : 'runtime/storage doit être accessible en écriture'
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
        if (currentLanguage() === 'fr') {
            output($errors === 0
                ? "Diagnostic réussi avec {$warnings} avertissement(s)."
                : "Diagnostic échoué : {$errors} erreur(s), {$warnings} avertissement(s).");
        } else {
            output($errors === 0
                ? "Diagnostics passed with {$warnings} warning(s)."
                : "Diagnostics failed: {$errors} error(s), {$warnings} warning(s).");
        }
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

require_once __DIR__ . '/ai-debug.php';
require_once __DIR__ . '/deploy.php';

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
        $relativePath = optionValue($arguments, '--path') ?? 'runtime/storage/database.sqlite';
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

function migrateProjectStructure(bool $apply, bool $yes): void
{
    $root = projectRoot();
    $legacyManifest = $root . '/info.json';
    $manifest = $root . '/phpaml.json';
    $legacyRuntime = $root . '/aml_env';
    $runtime = $root . '/runtime';
    $legacyView = $root . '/app/View';
    $ui = $root . '/app/UI';

    $conflicts = [];
    if (is_file($legacyManifest) && is_file($manifest)) {
        $conflicts[] = 'info.json + phpaml.json';
    }
    if (is_dir($legacyRuntime) && is_dir($runtime)) {
        $conflicts[] = 'aml_env/ + runtime/';
    }
    if (is_dir($legacyView) && is_dir($ui)) {
        $conflicts[] = 'app/View/ + app/UI/';
    }
    if ($conflicts !== []) {
        fail('Migration impossible : conflits détectés (' . implode(', ', $conflicts) . ').');
    }

    $renameManifest = is_file($legacyManifest);
    $renameRuntime = is_dir($legacyRuntime);
    $renameView = is_dir($legacyView);
    if (!$renameManifest && !$renameRuntime && !$renameView) {
        output(currentLanguage() === 'fr' ? 'La structure du projet est déjà à jour.' : 'The project structure is already up to date.');
        return;
    }

    output(currentLanguage() === 'fr' ? 'Migration de structure PHPAML' : 'PHPAML structure migration');
    if ($renameManifest) {
        output('  info.json → phpaml.json');
    }
    if ($renameRuntime) {
        output('  aml_env/ → runtime/');
    }
    if ($renameView) {
        output('  app/View/ → app/UI/');
    }
    output(currentLanguage() === 'fr'
        ? '  Les références connues aml_env/info.json seront actualisées.'
        : '  Known aml_env/info.json references will be updated.');

    if (!$apply) {
        output(currentLanguage() === 'fr'
            ? "Aperçu uniquement. Utilisez 'aml migrate:structure --apply --yes' pour appliquer."
            : "Preview only. Use 'aml migrate:structure --apply --yes' to apply.");
        return;
    }
    if (!$yes) {
        fail("Ajoutez '--yes' pour confirmer la migration.");
    }

    $editable = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file): bool {
                return !$file->isDir() || !in_array($file->getFilename(), ['.git', 'runtime', 'aml_env', 'vendor'], true);
            }
        )
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getSize() > 2_000_000) {
            continue;
        }
        $extension = strtolower($file->getExtension());
        if (in_array($extension, ['php', 'json', 'md', 'txt', 'xml', 'yml', 'yaml', 'lock'], true)
            || in_array($file->getFilename(), ['.gitignore', '.env', '.env.example'], true)) {
            $editable[] = $file->getPathname();
        }
    }

    $backupRuntime = $renameRuntime ? $legacyRuntime : $runtime;
    $backup = $backupRuntime . '/storage/migrations/structure-' . gmdate('Ymd-His');
    if (!is_dir($backup) && !mkdir($backup, 0700, true) && !is_dir($backup)) {
        fail('Impossible de créer la sauvegarde de migration.');
    }
    $saved = [];
    try {
        foreach ($editable as $path) {
            $content = file_get_contents($path);
            if (!is_string($content)
                || (!str_contains($content, 'aml_env')
                    && !str_contains($content, 'info.json')
                    && !str_contains($content, 'app/View')
                    && !str_contains($content, 'App\\View'))) {
                continue;
            }
            $relative = ltrim(substr($path, strlen($root)), '/');
            $target = $backup . '/' . $relative;
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            if (!copy($path, $target)) {
                throw new RuntimeException("Unable to back up {$relative}");
            }
            $saved[$path] = $target;
            $updated = str_replace(
                ['aml_env', 'info.json', 'app/View', 'App\\View'],
                ['runtime', 'phpaml.json', 'app/UI', 'App\\UI'],
                $content
            );
            if (file_put_contents($path, $updated) === false) {
                throw new RuntimeException("Unable to update {$relative}");
            }
        }

        if ($renameManifest) {
            $raw = file_get_contents($legacyManifest);
            $data = json_decode($raw ?: '', true, 512, JSON_THROW_ON_ERROR);
            if (isset($data['aml']) && !isset($data['runtime'])) {
                $data['runtime'] = $data['aml'];
                unset($data['aml']);
            }
            if (isset($data['runtime']['environment'])) {
                $data['runtime']['directory'] = $data['runtime']['environment'];
                unset($data['runtime']['environment']);
            }
            if (isset($data['dependencies']) && !isset($data['requirements'])) {
                $data['requirements'] = $data['dependencies'];
                unset($data['dependencies']);
            }
            $data['runtime']['directory'] = 'runtime';
            $data['modules'] ??= [];
            if (file_put_contents($legacyManifest, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL) === false
                || !rename($legacyManifest, $manifest)) {
                throw new RuntimeException('Unable to migrate info.json');
            }
        }
        if ($renameRuntime && !rename($legacyRuntime, $runtime)) {
            throw new RuntimeException('Unable to migrate aml_env');
        }
        if ($renameView && !rename($legacyView, $ui)) {
            throw new RuntimeException('Unable to migrate app/View');
        }
    } catch (Throwable $error) {
        if (is_dir($ui) && !is_dir($legacyView) && $renameView) {
            @rename($ui, $legacyView);
        }
        if (is_dir($runtime) && !is_dir($legacyRuntime) && $renameRuntime) {
            @rename($runtime, $legacyRuntime);
        }
        if (is_file($manifest) && !is_file($legacyManifest) && $renameManifest) {
            @rename($manifest, $legacyManifest);
        }
        foreach ($saved as $path => $copy) {
            @copy($copy, $path);
        }
        fail('Migration annulée : ' . $error->getMessage());
    }

    output(currentLanguage() === 'fr' ? '✓ Structure migrée avec succès.' : '✓ Structure migrated successfully.');
    $finalBackup = $renameRuntime ? str_replace($legacyRuntime, $runtime, $backup) : $backup;
    output((currentLanguage() === 'fr' ? 'Sauvegarde : ' : 'Backup: ') . $finalBackup);
    output(currentLanguage() === 'fr'
        ? "Vérification conseillée : aml doctor --offline, puis aml test."
        : 'Recommended verification: aml doctor --offline, then aml test.');
}

switch ($command) {
    case 'ai:configure':
        aiConfigure($arguments[1] ?? 'deepseek', optionValue($arguments, '--key'), optionValue($arguments, '--model'));
        break;
    case 'ai:show':
        aiShow();
        break;
    case 'debug':
        $debugProblem = isset($arguments[1]) && !str_starts_with($arguments[1], '--') ? $arguments[1] : null;
        aiDebug(in_array('--fix', $arguments, true), in_array('--yes', $arguments, true), $debugProblem);
    case 'debug:history':
        aiDebugHistory(in_array('--json', $arguments, true));
        break;
    case 'debug:show':
        isset($arguments[1]) ? aiDebugShow($arguments[1], in_array('--json', $arguments, true)) : fail('Indiquez l’identifiant du diagnostic.');
        break;
    case 'debug:rollback':
        isset($arguments[1]) ? aiDebugRollback($arguments[1], in_array('--yes', $arguments, true)) : fail('Indiquez l’identifiant du diagnostic.');
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
    case 'create-view-app':
        $destination = isset($arguments[1]) && !str_starts_with($arguments[1], '--') ? $arguments[1] : '.';
        createViewApplication(
            $destination,
            optionValue($arguments, '--template-version') ?? optionValue($arguments, '--version'),
            optionValue($arguments, '--view-version'),
            in_array('--refresh', $arguments, true),
            in_array('--offline', $arguments, true)
        );
        break;
    case 'serve':
        serve($arguments[1] ?? 'localhost:8000');
    case 'build':
        buildProject(in_array('--skip-tests', $arguments, true));
    case 'deploy:configure':
        isset($arguments[1]) ? deployConfigure($arguments[1], $arguments) : fail('Indiquez le nom du profil.');
        break;
    case 'deploy:check':
        isset($arguments[1]) ? deployCheck($arguments[1]) : fail('Indiquez le nom du profil.');
    case 'deploy':
        isset($arguments[1]) ? deployProject($arguments[1], in_array('--skip-build', $arguments, true)) : fail('Indiquez le nom du profil.');
    case 'deploy:rollback':
        isset($arguments[1]) ? deployRollback($arguments[1]) : fail('Indiquez le nom du profil.');
    case 'ssh':
        isset($arguments[1]) ? deployShell($arguments[1]) : fail('Indiquez le nom du profil.');
    case 'sftp':
        isset($arguments[1]) ? deployShell($arguments[1], true) : fail('Indiquez le nom du profil.');
    case 'install':
        if (($arguments[1] ?? null) === 'data') {
            installData(array_slice($arguments, 2));
        }
        if (($arguments[1] ?? null) === 'view') {
            fail("La commande 'aml install view' a été retirée. Utilisez 'aml create-view-app .' pour créer une application AML View.");
        }
        if (($arguments[1] ?? null) === 'i18n') {
            installI18n(optionValue($arguments, '--version'));
        }
        installModules(
            in_array('--production', $arguments, true),
            optionValue($arguments, '--version'),
            in_array('--refresh', $arguments, true),
            in_array('--offline', $arguments, true)
        );
    case '--update':
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
    case 'make:middleware':
        isset($arguments[1])
            ? generateClass(substr($command, 5), $arguments[1])
            : fail('Indiquez le nom de la classe à générer.');
        break;
    case 'make:model':
        isset($arguments[1]) ? runDataCommand('data:make-model', [$arguments[1]]) : fail('Indiquez le nom de la classe à générer.');
    case 'make:migration':
        isset($arguments[1]) ? runDataCommand('data:make-migration', [$arguments[1]]) : fail('Indiquez le nom de la migration.');
    case 'make:seeder':
        isset($arguments[1]) ? runDataCommand('data:make-seeder', [$arguments[1]]) : fail('Indiquez le nom du seeder.');
    case 'make:view-page':
    case 'make:view-component':
    case 'make:view-layout':
        isset($arguments[1])
            ? generateViewClass(substr($command, strlen('make:view-')), $arguments[1])
            : fail('Indiquez le nom de la classe à générer.');
        break;
    case 'make:view-loading':
    case 'make:view-error':
    case 'make:view-not-found':
        generateViewState(substr($command, strlen('make:view-')), $arguments[1] ?? '.');
        break;
    case 'i18n:add':
        isset($arguments[1]) ? i18nAdd($arguments[1]) : fail('Indiquez la langue à ajouter.');
    case 'i18n:list':
        i18nList();
    case 'i18n:check':
        i18nCheck();
    case 'i18n:missing':
        isset($arguments[1]) ? i18nCheck($arguments[1]) : fail('Indiquez la langue à vérifier.');
    case 'i18n:set-default':
        isset($arguments[1]) ? envSet('APP_LOCALE', i18nLocale($arguments[1])) : fail('Indiquez la langue principale.');
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
    case 'data:migrate':
    case 'data:rollback':
    case 'data:seed':
    case 'data:status':
    case 'data:doctor':
        runDataCommand($command, array_slice($arguments, 1));
    case 'migrate:structure':
        migrateProjectStructure(in_array('--apply', $arguments, true), in_array('--yes', $arguments, true));
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
