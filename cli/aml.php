#!/usr/bin/env php
<?php

declare(strict_types=1);

define('PHPAML_FRAMEWORK_ROOT', dirname(__DIR__, 2));

function amlCacheRoot(): string
{
    return rtrim((string) (getenv('AML_CACHE_HOME') ?: PHPAML_FRAMEWORK_ROOT . '/runtime/cache'), '/\\');
}

function amlWritableTemporaryRoot(): string
{
    $candidates = [getenv('AML_TMPDIR'), getenv('TMPDIR'), sys_get_temp_dir()];
    if (PHP_OS_FAMILY !== 'Windows') $candidates[] = '/tmp';
    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') continue;
        $candidate = rtrim($candidate, '/\\');
        if ((!is_dir($candidate) && !@mkdir($candidate, 0700, true)) || !is_writable($candidate)) continue;
        $probe = @tempnam($candidate, 'aml-write-');
        if (!is_string($probe)) continue;
        @unlink($probe);
        return $candidate;
    }
    throw new RuntimeException('Aucun dossier temporaire inscriptible n’est disponible.');
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
    if (class_exists(\PHPAML\Config\ApplicationConfig::class)) {
        return \PHPAML\Config\ApplicationConfig::load($root);
    }
    \PHPAML\Config\Env::load($root . '/.env');
    foreach ([$root . '/config/app.php', $root . '/configs/app.php'] as $legacyConfig) {
        if (is_file($legacyConfig)) return require $legacyConfig;
    }
    fail('La configuration du projet ne peut pas être chargée.');
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
            '  create-api <directory>   Create a JSON API application',
            '  serve [host:port]        Start the development server',
            '  install [module]         Install the engine or an optional module',
            '  build [options]          Create a production deployment archive',
            '  deploy <profile>         Build and deploy through SSH/SFTP (--dry-run to preview)',
            '  deploy:status <name>     Compare the local project with production',
            '  deploy:history [name]    Show the private local deployment history',
            '  deploy:configure <name>  Configure a deployment profile',
            '  deploy:check <name>      Test an SSH connection',
            '  deploy:rollback <name>   Activate the previous release',
            '  deploy:prune <name>      Remove old releases (--keep 5)',
            '  ssh <profile>            Open a remote SSH shell',
            '  sftp <profile>           Open an SFTP session',
            '  --update [options]       Update AML itself (also: aml update)',
            '  doctor [options]         Check the installation and current project (--production for deployment)',
            '  debug [problem]          Diagnose with AI (strict by default; --include-code opts in)',
            '  debug:history            List saved AI diagnostics',
            '  debug:show <id>          Show a diagnostic report',
            '  debug:rollback <id>      Roll back changes from a diagnostic',
            '  ai:configure <provider>  Configure DeepSeek, OpenAI or Claude',
            '  ai:show                  Show the active AI provider (key remains hidden)',
            '  routes                   List application routes',
            '  api:install              Enable the PHPAML API defaults',
            '  make:api <name>          Generate REST CRUD (--model --migration --fields)',
            '  api:add-field <name> <fields> Add fields to a generated API resource',
            '  api:rename-field <name> <old> <new> Rename an API resource field',
            '  api:remove-field <name> <field> Remove an API resource field',
            '  api:token <owner>        Create an API token (--name client)',
            '  api:openapi              Generate public/openapi.json',
            '  api:client               Generate the TypeScript API client',
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
    output('  create-api <dossier>      Crée une application API JSON');
    output('  serve [hôte:port]         Lance le serveur de développement');
    output('  install [module]          Installe le moteur ou un module optionnel');
    output('  build [options]           Crée une archive de déploiement production');
    output('  deploy <profil>           Construit et déploie par SSH/SFTP (--dry-run pour prévisualiser)');
    output('  deploy:status <nom>       Compare le projet local à la production');
    output('  deploy:history [nom]      Affiche l’historique local privé des déploiements');
    output('  deploy:configure <nom>    Configure un serveur de déploiement');
    output('  deploy:check <nom>        Teste la connexion SSH');
    output('  deploy:rollback <nom>     Réactive la release précédente');
    output('  deploy:prune <nom>        Supprime les anciennes releases (--keep 5)');
    output('  ssh <profil>              Ouvre une session SSH');
    output('  sftp <profil>             Ouvre une session SFTP');
    output('  --update [options]        Met à jour AML (alias : aml update)');
    output('  doctor [options]          Vérifie l’installation et le projet courant');
    output('  debug [problème]          Diagnostique avec l’IA (strict par défaut; --include-code autorise le code)');
    output('  debug:history             Affiche les diagnostics enregistrés');
    output('  debug:show <id>           Affiche un rapport de diagnostic');
    output('  debug:rollback <id>       Annule les corrections d’un diagnostic');
    output('  ai:configure <fournisseur> Configure DeepSeek, OpenAI ou Claude');
    output('  ai:show                   Affiche le fournisseur IA (clé masquée)');
    output('  routes                    Affiche les routes de l’application');
    output('  api:install               Active la configuration API PHPAML');
    output('  make:api <nom>            Génère un CRUD REST (--model --migration --fields)');
    output('  api:add-field <nom> <champs> Ajoute des champs au CRUD et crée une migration');
    output('  api:rename-field <nom> <ancien> <nouveau> Renomme un champ du CRUD');
    output('  api:remove-field <nom> <champ> Supprime un champ du CRUD');
    output('  api:token <propriétaire>  Crée un token API (--name client)');
    output('  api:openapi               Génère public/openapi.json');
    output('  api:client                Génère le client TypeScript');
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
    bool $offline = false,
    bool $install = true,
    bool $announceDeferred = true
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
    $hashes = [];
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

    if (!is_file($target . '/.env') && is_file($target . '/.env.example')) {
        copy($target . '/.env.example', $target . '/.env');
    }

    if (currentLanguage() === 'en') {
        output("Application '{$projectName}' created with PHPAML v{$template['version']} in {$target}");
    } else {
        output("Application '{$projectName}' créée avec PHPAML v{$template['version']} dans {$target}");
    }

    if ($install) {
        installCreatedProject($target, $offline);
        output(currentLanguage() === 'en'
            ? ($destination === '.' ? 'Ready. Run: aml serve' : "Ready. Run: cd {$destination} && aml serve")
            : ($destination === '.' ? 'Prêt. Lancez : aml serve' : "Prêt. Lancez : cd {$destination} && aml serve"));
    } elseif ($announceDeferred) {
        output(currentLanguage() === 'en'
            ? 'Project files are ready. Run aml install before aml serve.'
            : 'Les fichiers du projet sont prêts. Lancez aml install avant aml serve.');
    }
}

function installCreatedProject(string $target, bool $offline = false): void
{
    $command = 'cd ' . escapeshellarg($target)
        . ' && ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__FILE__)
        . ' install'
        . ($offline ? ' --offline' : '');
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fail("L’installation automatique du projet a échoué. Relancez 'aml install' dans {$target}.", $exitCode);
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
    createProject($destination, $templateVersion, $refresh, $offline, false, false);
    if (!chdir($target)) {
        fail("Impossible d’ouvrir le projet créé dans {$target}.");
    }
    installView($viewVersion, $offline);
}

function removeGeneratedPath(string $path): void
{
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

function createApiApplication(string $destination, ?string $templateVersion = null, bool $refresh = false, bool $offline = false): void
{
    $target = creationTarget($destination);
    createProject($destination, $templateVersion, $refresh, $offline, false, false);
    foreach (['app/views', 'public/css', 'public/js', 'public/img', 'configs', 'config', 'routes'] as $obsolete) removeGeneratedPath($target . '/' . $obsolete);
    foreach (['app/Controllers/HomeController.php', 'app/Models/HomeModel.php'] as $obsolete) @unlink($target . '/' . $obsolete);
    foreach (['src/controllers', 'src/models', 'src/repositories', 'src/requests', 'src/resources', 'src/routes', 'src/middleware'] as $directory) {
        if (!is_dir($target . '/' . $directory) && !mkdir($target . '/' . $directory, 0755, true) && !is_dir($target . '/' . $directory)) fail("Impossible de créer {$directory}.");
    }
    removeGeneratedPath($target . '/app');
    writeNewFile($target, 'src/controllers/HealthController.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Controllers;
use PHPAML\Http\Response;
final class HealthController
{
    public function show(): Response { return Response::json(['status' => 'ok', 'framework' => 'PHPAML']); }
}
PHP
    );
    writeNewFile($target, 'src/routes/ApiRoute.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Routes;
use App\Controllers\HealthController;
use PHPAML\Routing\Route;
final class ApiRoute extends Route
{
    protected string $prefix = '/api/v1';
    protected function routes(): void { $this->get('/health', [HealthController::class, 'show']); }
}
PHP
    );
    $manifest = json_decode((string) file_get_contents($target . '/phpaml.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest['application'] = is_array($manifest['application'] ?? null) ? $manifest['application'] : [];
    $manifest['application']['type'] = 'api';
    $manifest['api'] = apiManifestDefaults();
    unset($manifest['application']['views'], $manifest['seo']);
    writeProjectManifest($target, $manifest);
    $composerPath = $target . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
    $composer['autoload']['psr-4']['App\\'] = 'src/';
    $composer['autoload']['psr-4']['App\\Controllers\\'] = 'src/controllers/';
    $composer['autoload']['psr-4']['App\\Models\\'] = 'src/models/';
    $composer['autoload']['psr-4']['App\\Repositories\\'] = 'src/repositories/';
    $composer['autoload']['psr-4']['App\\Requests\\'] = 'src/requests/';
    $composer['autoload']['psr-4']['App\\Resources\\'] = 'src/resources/';
    $composer['autoload']['psr-4']['App\\Routes\\'] = 'src/routes/';
    $composer['autoload']['psr-4']['App\\Middleware\\'] = 'src/middleware/';
    file_put_contents($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    $indexPath = $target . '/public/index.php';
    $index = (string) file_get_contents($indexPath);
    $index = str_replace("'App\\\\' => \$root . '/app'", "'App\\\\' => \$root . '/src'", $index);
    $index = preg_replace(
        '/^\s*foreach \(\[[^\r\n]*\] as \$watchedRoot\) \{$/m',
        "    foreach ([\$root . '/src', __DIR__] as \$watchedRoot) {",
        $index,
    ) ?? $index;
    $modernConfigLoader = "\$config = \\PHPAML\\Config\\ApplicationConfig::load(\$root);";
    $index = str_replace([
        "\\PHPAML\\Config\\Env::load(\$root . '/.env');\n\$config = require \$root . '/configs/app.php';",
        "\\PHPAML\\Config\\Env::load(\$root . '/.env');\n\$config = require \$root . '/config/app.php';",
        "\$config = require \$root . '/configs/app.php';",
        "\$config = require \$root . '/config/app.php';",
    ], $modernConfigLoader, $index);
    if (!str_contains($index, 'ApplicationConfig::load($root)')) {
        fail('Le point d’entrée public/index.php du modèle ne peut pas être modernisé.');
    }
    file_put_contents($indexPath, $index, LOCK_EX);
    $phpstanPath = $target . '/phpstan.neon';
    if (is_file($phpstanPath)) {
        $phpstan = (string) file_get_contents($phpstanPath);
        $phpstan = preg_replace('/\s*- app\/Controllers\R\s*- app\/Models\R?/', "\n        - src\n", $phpstan) ?? $phpstan;
        $phpstan = str_replace('        - routes' . PHP_EOL, '', $phpstan);
        file_put_contents($phpstanPath, $phpstan, LOCK_EX);
    }
    @unlink($target . '/tests/run.php');
    writeNewFile($target, 'tests/run.php', <<<'PHP'
<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require $root . '/runtime/autoload.php';
$config = \PHPAML\Config\ApplicationConfig::load($root);
$response = (new \PHPAML\WebApplication($config))->handle(new \PHPAML\Http\Request('GET', '/api/v1/health'));
if ($response->status() !== 200 || !str_contains($response->content(), '"status":"ok"')) throw new RuntimeException('The generated API health route failed.');
echo "✓ Generated API health route\n";
PHP
    );
    installCreatedProject($target, $offline);
    output(currentLanguage() === 'en'
        ? "API '{$manifest['name']}' is ready in {$target}. Run: cd {$destination} && aml serve"
        : "L’API '{$manifest['name']}' est prête dans {$target}. Lancez : cd {$destination} && aml serve");
}

/** @return list<string> */
function projectRequiredPhpExtensions(string $root): array
{
    $extensions = [];
    foreach ([$root . '/composer.json', $root . '/composer.lock'] as $path) {
        if (!is_file($path)) continue;
        $document = json_decode((string) file_get_contents($path), true);
        if (!is_array($document)) continue;
        $requirements = [];
        if ($path === $root . '/composer.json') {
            $requirements[] = $document['require'] ?? [];
        } else {
            $requirements[] = $document['platform'] ?? [];
            foreach (array_merge($document['packages'] ?? [], $document['packages-dev'] ?? []) as $package) {
                if (is_array($package)) $requirements[] = $package['require'] ?? [];
            }
        }
        foreach ($requirements as $require) {
            if (!is_array($require)) continue;
            foreach (array_keys($require) as $name) {
                if (is_string($name) && str_starts_with(strtolower($name), 'ext-')) {
                    $extensions[] = strtolower(substr($name, 4));
                }
            }
        }
    }
    $extensions = array_values(array_unique(array_filter($extensions)));
    sort($extensions);
    return $extensions;
}

/** @return list<string> */
function phpRuntimeCandidates(): array
{
    $candidates = [];
    $configured = trim((string) getenv('AML_PHP_BINARY'));
    if ($configured !== '') $candidates[] = $configured;
    $candidates[] = PHP_BINARY;
    $executable = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
    foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
        if ($directory !== '') $candidates[] = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $executable;
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        array_push($candidates, '/opt/homebrew/bin/php', '/usr/local/bin/php', '/usr/bin/php');
    }
    return array_values(array_unique($candidates));
}

/** @param list<string> $extensions */
function phpRuntimeSupports(string $binary, array $extensions, array &$missing = []): bool
{
    $missing = [];
    if (!is_file($binary) || (PHP_OS_FAMILY !== 'Windows' && !is_executable($binary))) return false;
    $probe = '$required=' . var_export($extensions, true) . ';'
        . '$missing=array_values(array_filter($required,static fn(string $extension):bool=>!extension_loaded($extension)));'
        . 'if(PHP_VERSION_ID<80200){$missing[]="php>=8.2";}'
        . 'fwrite(STDOUT,implode(",",$missing));exit($missing===[]?0:1);';
    $pipes = [];
    $process = proc_open(
        [$binary, '-r', $probe],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) return false;
    $reported = trim((string) stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $missing = $reported === '' ? [] : array_values(array_filter(explode(',', $reported)));
    return $exitCode === 0;
}

/** @param list<string> $extensions */
function compatibleProjectPhp(array $extensions): string
{
    $bestMissing = null;
    foreach (phpRuntimeCandidates() as $candidate) {
        $missing = [];
        if (phpRuntimeSupports($candidate, $extensions, $missing)) return $candidate;
        if ($missing !== [] && ($bestMissing === null || count($missing) < count($bestMissing))) $bestMissing = $missing;
    }
    $details = implode(', ', $bestMissing ?? $extensions);
    if ($details === '') $details = 'PHP >= 8.2';
    fail(currentLanguage() === 'en'
        ? 'No compatible PHP runtime was found. Missing requirements: ' . $details . '.'
        : 'Aucun runtime PHP compatible trouvé. Prérequis absents : ' . $details . '.');
}

function serve(string $address): never
{
    if (!preg_match('/^[a-zA-Z0-9.\-]+:\d{1,5}$/', $address)) {
        fail("Adresse invalide : {$address}");
    }
    $separator = (int) strrpos($address, ':');
    $host = substr($address, 0, $separator);
    $port = (int) substr($address, $separator + 1);
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
    $manifest = projectInfo($root);
    if (isset($manifest['modules']['view'])
        && !projectRuntimeLoads($root, [\AML\View\FileApplication::class, \AML\Engine\EngineRuntime::class, \PHPAML\Security\CspNonce::class])) {
        fail("Le runtime AML View est incomplet. Exécutez 'aml install', puis recréez le projet avec 'aml create-view-app' si le problème persiste.");
    }

    $requestedPort = $port;
    while ($port <= 65535) {
        $errno = 0;
        $error = '';
        $probe = @stream_socket_server("tcp://{$host}:{$port}", $errno, $error);
        if (is_resource($probe)) {
            fclose($probe);
            break;
        }
        $port++;
    }

    if ($port > 65535) {
        fail("Aucun port disponible à partir de {$requestedPort}.");
    }

    $address = "{$host}:{$port}";
    if ($port !== $requestedPort) {
        output(currentLanguage() === 'en'
            ? "Port {$requestedPort} is in use; using {$port}."
            : "Le port {$requestedPort} est occupé ; utilisation du port {$port}.");
    }
    output("PHPAML écoute sur http://{$address}");
    output('Utilisez Ctrl+C pour arrêter le serveur.');
    $projectIniDirectory = $root . '/configs/php';
    if (is_dir($projectIniDirectory)) {
        $scanDirectories = getenv('PHP_INI_SCAN_DIR');
        $scanDirectories = is_string($scanDirectories) && $scanDirectories !== ''
            ? $scanDirectories . PATH_SEPARATOR . $projectIniDirectory
            : PATH_SEPARATOR . $projectIniDirectory;
        putenv('PHP_INI_SCAN_DIR=' . $scanDirectories);
    }
    $requiredExtensions = projectRequiredPhpExtensions($root);
    $serverPhp = compatibleProjectPhp($requiredExtensions);
    if ($serverPhp !== PHP_BINARY) {
        output(currentLanguage() === 'en'
            ? "Project requirements need a compatible PHP runtime; using {$serverPhp}."
            : "Les prérequis du projet nécessitent un runtime PHP compatible ; utilisation de {$serverPhp}.");
    }
    passthru(
        escapeshellarg($serverPhp) . ' -S ' . escapeshellarg($address)
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

    $secretFindings = productionSecretFindings($root);
    if ($secretFindings !== []) {
        fail("Le build contient des fichiers ou secrets sensibles :\n- " . implode("\n- ", $secretFindings));
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
    $excludedRoots = ['.git', '.github', '.env', 'tests', 'output', 'tmp', 'tools', 'readme', 'deliverables'];
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
            || productionBuildDevelopmentFile($relative)
            || str_starts_with($relative, 'runtime/storage/debug-')
            || str_starts_with($relative, 'runtime/storage/log')
            || str_starts_with($relative, 'runtime/storage/rate-limits/')
            || str_starts_with($relative, 'runtime/storage/sessions/')
            || str_starts_with($relative, 'runtime/storage/cache/')
            || in_array(strtolower($file->getExtension()), $excludedExtensions, true)) continue;
        if (in_array($relative, [
            'runtime/composer/autoload_files.php',
            'runtime/composer/autoload_static.php',
        ], true)) {
            $contents = productionComposerAutoload((string) file_get_contents($file->getPathname()));
            $zip->addFromString($relative, $contents);
            $hashes[$relative] = hash('sha256', $contents);
        } else {
            $zip->addFile($file->getPathname(), $relative);
            $fileHash = hash_file('sha256', $file->getPathname());
            if (!is_string($fileHash)) {
                fail("Impossible de calculer l'empreinte de {$relative}.");
            }
            $hashes[$relative] = $fileHash;
        }
        $files[] = $relative;
    }
    sort($files);
    ksort($hashes);
    $manifest = [
        'built_at' => date(DATE_ATOM),
        'aml_version' => projectInfo(PHPAML_FRAMEWORK_ROOT)['version'] ?? null,
        'project' => projectInfo($root)['name'] ?? basename($root),
        'entrypoint' => 'public/index.php',
        'document_root' => 'public',
        'clean_urls' => true,
        'files' => $files,
        'hashes' => $hashes,
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

function productionBuildDevelopmentFile(string $relative): bool
{
    $relative = str_replace('\\', '/', $relative);
    if ($relative === 'phpstan.neon'
        || str_starts_with($relative, 'runtime/phpstan/')
        || in_array($relative, ['runtime/bin/phpstan', 'runtime/bin/phpstan.phar'], true)) {
        return true;
    }

    if (!str_starts_with($relative, 'runtime/phpaml/')) return false;

    $segments = explode('/', $relative);
    foreach (['tests', 'docs', 'examples', '.github'] as $developmentDirectory) {
        if (in_array($developmentDirectory, $segments, true)) return true;
    }

    $basename = basename($relative);
    return in_array($basename, [
        '.gitignore',
        'CHANGELOG.md',
        'CONTRIBUTING.md',
        'README.md',
        'SECURITY.md',
        'SPECIFICATION.md',
        'package.json',
        'package-lock.json',
        'playwright.config.mjs',
    ], true);
}

function productionComposerAutoload(string $contents): string
{
    $lines = preg_split('/\R/', $contents);
    if (!is_array($lines)) throw new RuntimeException('Impossible de préparer l’autoload Composer de production.');
    $lines = array_values(array_filter(
        $lines,
        static fn (string $line): bool => !str_contains(
            str_replace('\\', '/', $line),
            '/phpstan/phpstan/bootstrap.php',
        ),
    ));
    return implode(PHP_EOL, $lines) . (str_ends_with($contents, "\n") ? PHP_EOL : '');
}

/** @return list<string> */
function productionSecretFindings(string $root): array
{
    $findings = [];
    $sensitiveNames = ['id_rsa', 'id_dsa', 'id_ecdsa', 'id_ed25519', 'credentials', 'credentials.json'];
    $sensitiveExtensions = ['pem', 'key', 'p12', 'pfx', 'jks', 'keystore', 'crt', 'cer'];
    $contentExtensions = ['php', 'json', 'yaml', 'yml', 'ini', 'conf', 'config', 'txt'];
    $patterns = [
        '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
        '/\bAKIA[0-9A-Z]{16}\b/',
        '/\b(?:ghp|github_pat)_[A-Za-z0-9_]{20,}\b/',
        '/\bsk-[A-Za-z0-9_-]{20,}\b/',
        '/\b(?:xox[baprs]-)[A-Za-z0-9-]{20,}\b/',
    ];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, '.git/') || str_starts_with($relative, 'output/')) continue;
        $name = strtolower($file->getBasename());
        $extension = strtolower($file->getExtension());
        if (((str_starts_with($name, '.env.') && $name !== '.env.example'))
            || in_array($name, $sensitiveNames, true)
            || in_array($extension, $sensitiveExtensions, true)) {
            $findings[] = "{$relative} (fichier sensible)";
            continue;
        }
        if ($file->getSize() > 1048576 || !in_array($extension, $contentExtensions, true)) continue;
        $content = (string) file_get_contents($file->getPathname());
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                $findings[] = "{$relative} (secret détecté)";
                break;
            }
        }
    }
    sort($findings);
    return array_values(array_unique($findings));
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

/** @param list<class-string> $classes */
function projectRuntimeLoads(string $root, array $classes): bool
{
    $autoload = $root . '/runtime/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }

    $probe = <<<'PHP'
$autoload = $argv[1] ?? '';
require $autoload;
foreach (array_slice($argv, 2) as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, $class . PHP_EOL);
        exit(1);
    }
}
PHP;
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($probe)
        . ' ' . escapeshellarg($autoload);
    foreach ($classes as $class) {
        $command .= ' ' . escapeshellarg($class);
    }
    exec($command, $output, $exitCode);

    return $exitCode === 0;
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
        if (is_dir($legacyPath)) {
            $legacyStat = @stat($legacyPath);
            $sourceStat = @stat($sourcePath);
            $sameDirectory = is_array($legacyStat)
                && is_array($sourceStat)
                && $legacyStat['dev'] === $sourceStat['dev']
                && $legacyStat['ino'] === $sourceStat['ino'];
            if ($sameDirectory) {
                $temporaryPath = $root . '/src/.phpaml-case-' . strtolower($legacyDirectory);
                if (!rename($legacyPath, $temporaryPath) || !rename($temporaryPath, $sourcePath)) {
                    fail("Impossible de normaliser src/{$legacyDirectory} en src/{$sourceDirectory}.");
                }
            } elseif (!file_exists($sourcePath) && !rename($legacyPath, $sourcePath)) {
                fail("Impossible de migrer src/{$legacyDirectory} vers src/{$sourceDirectory}.");
            }
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
        $publicIndex = str_replace(
            "[\$root . '/app', \$root . '/configs', \$root . '/database', __DIR__]",
            "[\$root . '/src', \$root . '/configs', \$root . '/database', __DIR__]",
            $publicIndex,
        );
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
    $requiredRuntimeFiles = [
        'runtime/phpaml/view/src/FileApplication.php' => 'phpaml/view v0.1.0-beta.3 ou plus récent',
        'runtime/phpaml/engine/src/EngineRuntime.php' => 'phpaml/engine',
        'runtime/framework/Security/CspNonce.php' => 'phpaml/framework v0.2.1-beta.1 ou plus récent',
    ];
    foreach ($requiredRuntimeFiles as $relative => $requirement) {
        if (!is_file($root . '/' . $relative)) {
            fail("Installation AML View incomplète : {$requirement} est absent. Relancez Composer avec une mise à jour des dépendances.");
        }
    }
    if (!projectRuntimeLoads($root, [\AML\View\FileApplication::class, \AML\Engine\EngineRuntime::class, \PHPAML\Security\CspNonce::class])) {
        fail("Installation AML View incomplète : l’autoload du projet ne charge pas FileApplication, EngineRuntime et CspNonce. Relancez 'aml create-view-app' dans un nouveau dossier.");
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
:root { color-scheme:dark; --bg:#09070d; --panel:#15111f; --panel-soft:#100d17; --ink:#f8f7ff; --muted:#aaa6ba; --line:rgba(255,255,255,.11); --violet:#9b72ff; --lime:#c7ff3d; --header-bg:rgba(9,7,13,.82); --soft-fill:rgba(255,255,255,.04); --shadow:0 24px 70px rgba(0,0,0,.28); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
* { box-sizing:border-box; }
html { scroll-behavior:smooth; }
body { margin:0; min-width:20rem; overflow-x:hidden; color:var(--ink); background:radial-gradient(circle at 78% 8%,rgba(139,92,246,.18),transparent 32rem),radial-gradient(circle at 10% 48%,rgba(199,255,61,.055),transparent 28rem),var(--bg); line-height:1.65; transition:color .2s ease,background-color .2s ease; }
a { color:inherit; }
.shell { width:min(74rem,calc(100% - 3rem)); margin-inline:auto; }
.button { min-height:3.1rem; padding:0 1.25rem; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--line); border-radius:.65rem; font:inherit; font-size:.9rem; font-weight:800; text-decoration:none; cursor:pointer; transition:transform .18s ease,filter .18s ease,border-color .18s ease,background .18s ease; }
.button:hover { transform:translateY(-2px); }
.button-primary { border-color:var(--lime); color:#17110d; background:var(--lime); }
.button-secondary { background:var(--soft-fill); }
@media (max-width:38rem) { .shell { width:min(100% - 1.4rem,74rem); } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/components/navigation.css', <<<'CSS'
.view-header { position:sticky; top:0; z-index:50; border-bottom:1px solid var(--line); background:var(--header-bg); backdrop-filter:blur(20px) saturate(150%); }
.view-nav { min-height:4.75rem; display:flex; align-items:center; gap:1.5rem; }
.view-brand { display:inline-flex; align-items:center; gap:.7rem; font-weight:900; letter-spacing:.055em; text-decoration:none; }
.view-brand-logo { width:2.35rem; height:2.35rem; object-fit:contain; }
.view-nav-links { min-width:0; margin-left:auto; display:flex; align-items:center; justify-content:flex-end; gap:1.15rem; }
.view-nav-links a { color:var(--muted); font-size:.9rem; font-weight:750; text-decoration:none; }
.view-nav-links .nav-github { padding:.6rem .85rem; border:1px solid var(--line); border-radius:.6rem; color:var(--ink); background:var(--soft-fill); }
.theme-switcher { display:flex; gap:.2rem; padding:.2rem; border:1px solid var(--line); border-radius:.65rem; background:color-mix(in srgb,var(--panel) 72%,transparent); }
.theme-switcher button { padding:.4rem .55rem; border:0; border-radius:.35rem; color:var(--muted); background:transparent; cursor:pointer; }
.theme-switcher button[aria-pressed="true"] { color:var(--ink); background:var(--panel); }
@media (max-width:48rem) { .view-nav { gap:.75rem; } .view-nav-links { gap:.65rem; } .view-nav-links > a:not(.nav-github) { display:none; } }
@media (max-width:32rem) { .view-brand strong,.view-nav-links .nav-github { display:none; } .view-nav { min-height:4.25rem; } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/layouts/app.css', <<<'CSS'
.view-app { min-height:100vh; display:flex; flex-direction:column; }
.view-content { flex:1; }
.view-footer { padding-block:2.25rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; border-top:1px solid var(--line); color:var(--muted); font-size:.8rem; }
.view-footer a { color:var(--ink); text-decoration:none; }
@media (max-width:38rem) { .view-footer { align-items:flex-start; flex-direction:column; } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/pages/home.css', <<<'CSS'
.view-hero { min-height:calc(100vh - 4.75rem); padding-block:clamp(4.5rem,9vw,8rem); display:grid; grid-template-columns:minmax(0,1.15fr) minmax(17rem,.85fr); align-items:center; gap:clamp(2.5rem,7vw,6.5rem); }
.hero-content { min-width:0; }
.eyebrow { color:var(--lime); font:.76rem/1.2 ui-monospace,SFMono-Regular,Menlo,monospace; font-weight:850; letter-spacing:.15em; }
.view-hero h1 { max-width:49rem; margin:0; font-size:clamp(3.25rem,6.6vw,6.25rem); line-height:.94; letter-spacing:-.067em; text-wrap:balance; }
.hero-copy,.demo-copy { max-width:42rem; color:var(--muted); font-size:clamp(1rem,2vw,1.15rem); }
.hero-actions { display:flex; flex-wrap:wrap; gap:.8rem; }
.hero-logo { width:min(100%,25rem); height:auto; justify-self:center; filter:drop-shadow(0 2rem 4rem rgba(0,0,0,.45)); }
.view-demo { padding-block:clamp(4.5rem,8vw,7rem); border-top:1px solid var(--line); }
.view-demo > .eyebrow,.view-demo > h2,.view-demo > .demo-copy { display:block; max-width:47rem; margin-inline:auto; text-align:center; }
.view-demo h2 { margin:.75rem 0 1rem; font-size:clamp(2.2rem,5vw,4rem); letter-spacing:-.055em; }
.counter-card { max-width:58rem; margin:3rem auto 0; padding:1.5rem; display:grid; grid-template-columns:minmax(5rem,.65fr) auto auto minmax(7rem,1fr); align-items:center; gap:1rem; border:1px solid color-mix(in srgb,var(--violet) 45%,var(--line)); border-radius:1rem; background:linear-gradient(145deg,color-mix(in srgb,var(--panel) 86%,var(--violet) 14%),var(--panel-soft)); box-shadow:var(--shadow); }
.counter-value { min-width:5rem; font:700 clamp(2.4rem,8vw,5rem)/1 ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--violet); }
.counter-button { margin-left:0; }
.counter-button:disabled { opacity:.65; cursor:wait; }
.counter-message { justify-self:end; color:var(--muted); font-size:.85rem; }
.details-toggle { margin-top:1rem; }
.details-toggle.is-active { color:var(--bg); border-color:var(--lime); background:var(--lime); }
.details-panel { margin-top:1rem; padding:1.25rem; border-left:3px solid var(--lime); background:rgba(199,255,61,.06); }
.local-form-card { max-width:58rem; margin:1rem auto 0; padding:1.5rem; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); align-items:center; gap:1rem; border:1px solid var(--line); border-radius:1rem; background:var(--panel-soft); box-shadow:var(--shadow); }
.local-input { width:100%; padding:.85rem 1rem; border:1px solid var(--line); border-radius:.55rem; color:var(--ink); background:var(--panel); font:inherit; }
.local-preview { color:var(--lime); font-weight:800; }
.local-form-card [role="alert"] { grid-column:1/-1; color:#ff8585; font-size:.82rem; }
.api-card { max-width:58rem; margin:1rem auto 0; display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:1rem; }
.api-status { color:var(--lime); font-weight:800; }
.api-error { color:#ff8585; }
.task-card { max-width:58rem; margin:1rem auto 0; padding:1.5rem; display:flex; align-items:center; flex-wrap:wrap; gap:.75rem; border:1px solid var(--line); border-radius:1rem; background:var(--panel-soft); box-shadow:var(--shadow); }
.task-card h3 { width:100%; margin:0; }
.task-card ul { width:100%; margin:.5rem 0 0; padding-left:1.25rem; color:var(--muted); }
.task-card li { padding:.25rem 0; }
.view-features { padding-block:1rem 6rem; gap:1rem !important; }
.view-feature { min-height:15rem; padding:1.7rem; border:1px solid var(--line); border-radius:1rem; background:linear-gradient(145deg,var(--soft-fill),transparent),var(--panel-soft); transition:transform .2s ease,border-color .2s ease; }
.view-feature:hover { transform:translateY(-4px); border-color:color-mix(in srgb,var(--violet) 42%,var(--line)); }
.view-feature h3 { margin:2rem 0 0; font-size:1.25rem; }
.view-feature > span:last-child { color:var(--muted); font-size:.92rem; }
.feature-number { color:var(--violet); font:800 .75rem/1 ui-monospace,SFMono-Regular,Menlo,monospace; }
@media (max-width:58rem) { .view-hero { min-height:auto; grid-template-columns:1fr; gap:2rem; text-align:center; } .hero-content { align-items:center; } .hero-copy { margin-inline:auto; } .hero-actions { justify-content:center; } .hero-logo { width:min(52vw,19rem); grid-row:1; } .counter-card { grid-template-columns:1fr 1fr; } .counter-value,.counter-message { grid-column:1/-1; justify-self:center; text-align:center; } .view-features { grid-template-columns:1fr !important; } }
@media (max-width:38rem) { .view-hero { padding-block:3.5rem; } .view-hero h1 { font-size:clamp(2.75rem,14vw,4rem); overflow-wrap:anywhere; } .hero-actions { flex-direction:column; } .hero-actions .button { width:100%; } .counter-card { align-items:stretch; flex-direction:column; } .counter-button { width:100%; margin-left:0; } .local-form-card { grid-template-columns:1fr; } }
CSS
    );
    writeNewFile($root, 'src/views/stylesheets/states/route-states.css', <<<'CSS'
.route-state { min-height:calc(100vh - 10rem); justify-content:center; }
.loading-title { color:var(--lime); font-size:1.15rem; font-weight:850; }
CSS
    );
    writeNewFile($root, 'src/views/themes/light/tokens.css', <<<'CSS'
[data-theme="light"] { color-scheme:light; --bg:#f7f6fa; --panel:#ffffff; --panel-soft:#f0edf6; --ink:#17121f; --muted:#625d6d; --line:rgba(23,18,31,.13); --violet:#6d3de8; --lime:#6f9600; --header-bg:rgba(247,246,250,.86); --soft-fill:rgba(23,18,31,.035); --shadow:0 24px 65px rgba(48,34,71,.11); }
CSS
    );
    writeNewFile($root, 'src/views/themes/dark/tokens.css', <<<'CSS'
[data-theme="dark"] { color-scheme:dark; --bg:#09070d; --panel:#15111f; --panel-soft:#100d17; --ink:#f8f7ff; --muted:#aaa6ba; --line:rgba(255,255,255,.11); --violet:#9b72ff; --lime:#c7ff3d; --header-bg:rgba(9,7,13,.82); --soft-fill:rgba(255,255,255,.04); --shadow:0 24px 70px rgba(0,0,0,.28); }
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
        $anchors = [
            '$config = phpamlComposeApplication(\\PHPAML\\Config\\ApplicationConfig::load($root), $root);',
            '$config = \\PHPAML\\Config\\ApplicationConfig::load($root);',
            '$config = require $root . \'/configs/app.php\';',
            '$config = require $root . \'/config/app.php\';',
        ];
        $anchor = array_values(array_filter($anchors, static fn (string $candidate): bool => str_contains($index, $candidate)))[0] ?? '';
        if ($anchor === '') {
            fail("public/index.php ne contient pas le point d’intégration attendu.");
        }
        $integration = <<<'PHP'
// AML View integration
$viewApp = new \AML\View\FileApplication($root . '/src/views');
$config = \PHPAML\Config\ApplicationConfig::load($root);
if (function_exists('phpamlComposeApplication')) {
    $config = phpamlComposeApplication($config, $root);
}
$application = new \PHPAML\WebApplication($config);
if (!preg_match('#^/api(?:/|$)#', $requestPath)) {
    $request = \PHPAML\Http\Request::capture();
    $response = $application->handle($request, static function (\PHPAML\Http\Request $viewRequest) use ($application, $viewApp, $requestPath): \PHPAML\Http\Response {
        if (method_exists(\AML\Engine\EngineRuntime::class, 'assetFilename')
            && $requestPath === '/_aml/' . \AML\Engine\EngineRuntime::assetFilename(true)) {
            $runtime = file_get_contents(\AML\Engine\EngineRuntime::assetPath(true));
            if ($runtime === false) {
                return new \PHPAML\Http\Response('AML Engine asset unavailable.', 500);
            }
            return new \PHPAML\Http\Response($runtime, 200, [
                'Content-Type' => 'text/javascript; charset=utf-8',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }
        if (method_exists(\AML\Engine\EngineRuntime::class, 'assetFilename')
            && $requestPath === '/_aml/' . \AML\Engine\EngineRuntime::assetFilename(true) . '.map') {
            $sourceMap = \AML\Engine\EngineRuntime::assetPath(true) . '.map';
            $runtimeMap = file_get_contents($sourceMap);
            if ($runtimeMap === false) {
                return new \PHPAML\Http\Response('AML Engine source map unavailable.', 404);
            }
            return new \PHPAML\Http\Response($runtimeMap, 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }
        if ($requestPath === '/_aml/styles.css') {
            return new \PHPAML\Http\Response($viewApp->styles(), 200, [
                'Content-Type' => 'text/css; charset=utf-8',
                'Cache-Control' => 'no-cache',
            ]);
        }
        $status = 200;
        try {
            $result = $viewApp->mount($requestPath);
        } catch (OutOfBoundsException) {
            $status = 404;
            $result = $viewApp->notFound($requestPath);
        } catch (Throwable $error) {
            $status = 500;
            $result = $viewApp->error($requestPath, $error);
        }
        $session = $application->container()->get(\PHPAML\Session\Session::class);
        $head = $result instanceof \AML\View\PageResult ? $viewApp->head($requestPath) : '';
        $body = $result instanceof \AML\View\PageResult ? $result->rootHtml() : (string) $result;
        $liveReloadMeta = PHP_SAPI === 'cli-server' ? '<meta name="aml-live-reload" content="/_aml/live-reload">' : '';
        $cspNonce = \PHPAML\Security\CspNonce::from($viewRequest);
        $engineScript = method_exists(\AML\Engine\EngineRuntime::class, 'externalScript')
            ? \AML\Engine\EngineRuntime::externalScript()
            : \AML\Engine\EngineRuntime::script($cspNonce);
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . $session->csrfMeta() . $liveReloadMeta . $head
            . '<link rel="icon" href="/favicon.svg"><link rel="stylesheet" href="/_aml/styles.css">'
            . '</head><body>' . $body . $engineScript . '</body></html>';
        return \PHPAML\Http\Response::html($html, $status);
    });
    $response->send();
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
        $migratedIndex = str_replace(
            [
                "if (\$requestPath === '/_aml/' . \\AML\\Engine\\EngineRuntime::assetFilename(true)) {",
                "if (\$requestPath === '/_aml/' . \\AML\\Engine\\EngineRuntime::assetFilename(true) . '.map') {",
                '$html = \'<!doctype html><html lang="en"><head><meta charset="utf-8">\'',
                ". '</head><body>' . \$body . \\AML\\Engine\\EngineRuntime::externalScript() . '</body></html>';",
            ],
            [
                "if (method_exists(\\AML\\Engine\\EngineRuntime::class, 'assetFilename')\n            && \$requestPath === '/_aml/' . \\AML\\Engine\\EngineRuntime::assetFilename(true)) {",
                "if (method_exists(\\AML\\Engine\\EngineRuntime::class, 'assetFilename')\n            && \$requestPath === '/_aml/' . \\AML\\Engine\\EngineRuntime::assetFilename(true) . '.map') {",
                "\$cspNonce = \\PHPAML\\Security\\CspNonce::from(\$viewRequest);\n        \$engineScript = method_exists(\\AML\\Engine\\EngineRuntime::class, 'externalScript')\n            ? \\AML\\Engine\\EngineRuntime::externalScript()\n            : \\AML\\Engine\\EngineRuntime::script(\$cspNonce);\n        \$html = '<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">'",
                ". '</head><body>' . \$body . \$engineScript . '</body></html>';",
            ],
            $migratedIndex
        );
        if ($migratedIndex !== $index) {
            file_put_contents($indexPath, $migratedIndex, LOCK_EX);
            output('Migré : intégration public/index.php vers app/UI');
        }
    }

    $manifest = projectInfo($root);
    $manifest['modules'] = is_array($manifest['modules'] ?? null) ? $manifest['modules'] : [];
    $manifest['application'] = is_array($manifest['application'] ?? null) ? $manifest['application'] : [];
    $manifest['application']['type'] = 'view';
    $manifest['application']['views'] = 'src/views';
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
        $anchors = [
            '$config = phpamlComposeApplication(\\PHPAML\\Config\\ApplicationConfig::load($root), $root);',
            "\\PHPAML\\Config\\Env::load(\$root . '/.env');",
        ];
        $anchor = array_values(array_filter(
            $anchors,
            static fn (string $candidate): bool => str_contains($index, $candidate)
        ))[0] ?? '';
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
        if ($anchor === '') {
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
    $sourcePrefix = null;
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        if (preg_match('#^([^/]+/src/)#', $entry, $matches) === 1) {
            $sourcePrefix = $matches[1];
            break;
        }
    }
    if ($sourcePrefix === null) {
        $zip->close();
        fail('L’archive du moteur ne contient pas de dossier src/.');
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
        if (!str_starts_with($entry, $sourcePrefix) || str_ends_with($entry, '/')) {
            continue;
        }
        $relative = substr($entry, strlen($sourcePrefix));
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
        ? ['phpaml/data:^0.2@alpha', 'phpaml/data-mongodb:^0.1@alpha']
        : ['phpaml/data:^0.2@alpha'];
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
        projectRuntimePath($root) . '/build/phpaml-data/bin/aml-data',
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
    $application = new \PHPAML\WebApplication($config);
    $routes = $application->router()->routes();
    if ($routes === []) {
        output('Aucune route trouvée.');
        return;
    }
    output(str_pad('MÉTHODE', 12) . str_pad('ROUTE', 20) . 'ACTION');
    output(str_repeat('-', 64));
    foreach ($routes as $routeConfig) {
        $method = (string) ($routeConfig['method'] ?? '');
        $route = (string) ($routeConfig['path'] ?? '');
        [$controller, $action] = $routeConfig['handler'];
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

function installApi(): void
{
    $root = projectRoot();
    $manifest = projectInfo($root);
    if (isset($manifest['application'])) {
        $manifest['api'] = is_array($manifest['api'] ?? null) ? $manifest['api'] : apiManifestDefaults();
        writeProjectManifest($root, $manifest);
        foreach (['src/controllers', 'src/models', 'src/repositories', 'src/requests', 'src/resources', 'src/routes', 'src/middleware'] as $directory) {
            if (!is_dir($root . '/' . $directory) && !mkdir($root . '/' . $directory, 0755, true) && !is_dir($root . '/' . $directory)) {
                fail("Impossible de créer {$directory}.");
            }
        }
        $example = $root . '/.env.example';
        $env = is_file($example) ? (string) file_get_contents($example) : '';
        if (!str_contains($env, 'API_CORS_ORIGINS=')) {
            file_put_contents($example, rtrim($env) . "\nAPI_CORS_ORIGINS=http://localhost:5173\n", LOCK_EX);
        }
        output('✓ PHPAML API est configuré dans phpaml.json.');
        return;
    }
    writeNewFile($root, 'configs/api.php', <<<'PHP'
<?php

declare(strict_types=1);

use PHPAML\Config\Env;

$origins = array_values(array_filter(array_map('trim', explode(',', (string) Env::get('API_CORS_ORIGINS', 'http://localhost:5173')))));

return [
    'enabled' => true,
    'prefix' => '/api',
    'cors' => [
        'origins' => $origins,
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'headers' => ['Accept', 'Content-Type', 'Authorization'],
    ],
    'tokens' => [
        'storage_path' => dirname(__DIR__) . '/runtime/storage/api-tokens.json',
        'ttl' => 86400,
    ],
    'auth' => [
        'enabled' => true,
    ],
    'version' => [
        'name' => 'v1',
    ],
    'production' => [
        'idempotency' => true,
        'idempotency_path' => dirname(__DIR__) . '/runtime/storage/idempotency',
        'idempotency_ttl' => 86400,
        'http_cache' => true,
        'cache_max_age' => 0,
        'cache_public' => false,
    ],
];
PHP
    );
    writeNewFile($root, 'configs/api-routes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n];\n");
    $apiRoutesPath = $root . '/configs/api-routes.php';
    $apiRoutes = (string) file_get_contents($apiRoutesPath);
    if (!str_contains($apiRoutes, 'POST /api/v1/login')) {
        $authImports = "use PHPAML\\Api\\AuthController;\nuse PHPAML\\Api\\ApiDocumentationController;\nuse PHPAML\\Middleware\\ApiAuthMiddleware;\nuse PHPAML\\Middleware\\AuthRateLimitMiddleware;\n";
        $apiRoutes = str_replace("declare(strict_types=1);", "declare(strict_types=1);\n\n{$authImports}", $apiRoutes);
        $authEntries = "    'POST /api/v1/register' => ['handler' => [AuthController::class, 'register'], 'middleware' => [AuthRateLimitMiddleware::class]],\n"
            . "    'POST /api/v1/login' => ['handler' => [AuthController::class, 'login'], 'middleware' => [AuthRateLimitMiddleware::class]],\n"
            . "    'GET /api/v1/me' => ['handler' => [AuthController::class, 'me'], 'middleware' => [ApiAuthMiddleware::class]],\n"
            . "    'POST /api/v1/logout' => ['handler' => [AuthController::class, 'logout'], 'middleware' => [ApiAuthMiddleware::class]],\n"
            . "    'POST /api/v1/logout-all' => ['handler' => [AuthController::class, 'logoutAll'], 'middleware' => [ApiAuthMiddleware::class]],\n"
            . "    'POST /api/v1/token/rotate' => ['handler' => [AuthController::class, 'rotate'], 'middleware' => [ApiAuthMiddleware::class]],\n";
        $authEntries .= "    'GET /api/openapi.json' => [ApiDocumentationController::class, 'openapi'],\n"
            . "    'GET /api/docs' => [ApiDocumentationController::class, 'docs'],\n";
        $position = strrpos($apiRoutes, '];');
        if ($position === false) { fail('configs/api-routes.php est invalide.'); }
        $apiRoutes = substr($apiRoutes, 0, $position) . $authEntries . substr($apiRoutes, $position);
        if (file_put_contents($apiRoutesPath, $apiRoutes, LOCK_EX) === false) { fail('Impossible d’ajouter les routes d’authentification.'); }
    }

    $appPath = $root . '/configs/app.php';
    $app = is_file($appPath) ? (string) file_get_contents($appPath) : '';
    if ($app === '') { fail('configs/app.php est introuvable.'); }
    $changed = false;
    if (!str_contains($app, "'api' => require __DIR__ . '/api.php'")) {
        $anchor = "    'routes' => [";
        if (!str_contains($app, $anchor)) { fail("configs/app.php ne contient pas la section 'routes'."); }
        $app = str_replace($anchor, "    'api' => require __DIR__ . '/api.php',\n" . $anchor, $app, $count);
        $changed = $changed || $count > 0;
    }
    if (!str_contains($app, "'project_root' => dirname(__DIR__)")) {
        $anchor = "return [";
        $app = str_replace($anchor, $anchor . "\n    'project_root' => dirname(__DIR__),", $app, $count);
        $changed = $changed || $count > 0;
    }
    if (!str_contains($app, "...require __DIR__ . '/api-routes.php'")) {
        $anchor = "    'routes' => [";
        $app = str_replace($anchor, $anchor . "\n        ...require __DIR__ . '/api-routes.php',", $app, $count);
        $changed = $changed || $count > 0;
    }
    if ($changed && file_put_contents($appPath, $app, LOCK_EX) === false) { fail('Impossible de modifier configs/app.php.'); }

    $example = $root . '/.env.example';
    $env = is_file($example) ? (string) file_get_contents($example) : '';
    if (!str_contains($env, 'API_CORS_ORIGINS=')) {
        file_put_contents($example, rtrim($env) . "\nAPI_CORS_ORIGINS=http://localhost:5173\n", LOCK_EX);
    }
    output('✓ PHPAML API est prêt sur /api/v1.');
}

/** @return array<string, mixed> */
function apiManifestDefaults(): array
{
    return [
        'enabled' => true,
        'prefix' => '/api',
        'cors' => [
            'origins' => ['http://localhost:5173'],
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'headers' => ['Accept', 'Content-Type', 'Authorization'],
        ],
        'tokens' => ['storage_path' => 'runtime/storage/api-tokens.json', 'ttl' => 86400],
        'auth' => ['enabled' => true],
        'version' => ['name' => 'v1'],
        'production' => [
            'idempotency' => true,
            'idempotency_path' => 'runtime/storage/idempotency',
            'idempotency_ttl' => 86400,
            'http_cache' => true,
            'cache_max_age' => 0,
            'cache_public' => false,
        ],
    ];
}

/** @return list<array{name:string,type:string,nullable:bool,php:string,rule:string,schema:string}> */
function apiFields(?string $definition): array
{
    $definition = trim((string) ($definition ?? 'name:string'));
    if ($definition === '') { fail("L'option --fields ne peut pas être vide."); }
    $types = [
        'string' => ['string', 'string', 'string'], 'text' => ['string', 'string', 'text'],
        'integer' => ['int', 'integer', 'integer'], 'int' => ['int', 'integer', 'integer'],
        'decimal' => ['float', 'numeric', 'decimal'], 'float' => ['float', 'numeric', 'decimal'],
        'boolean' => ['bool', 'boolean', 'boolean'], 'bool' => ['bool', 'boolean', 'boolean'],
        'datetime' => ['string', 'string', 'dateTime'],
    ];
    $fields = [];
    foreach (explode(',', $definition) as $item) {
        [$name, $type] = array_pad(explode(':', trim($item), 2), 2, 'string');
        $nullable = str_ends_with($type, '?');
        $type = rtrim(strtolower($type), '?');
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1 || $name === 'id') { fail("Champ API invalide : {$name}."); }
        if (!isset($types[$type])) { fail("Type API inconnu : {$type}."); }
        [$php, $rule, $schema] = $types[$type];
        $fields[] = compact('name', 'type', 'nullable', 'php', 'rule', 'schema');
    }
    return $fields;
}

/** @return array<string, string> */
function apiFieldDefaults(?string $definition): array
{
    if ($definition === null || trim($definition) === '') { return []; }
    $defaults = [];
    foreach (explode(',', $definition) as $item) {
        [$name, $value] = array_pad(explode('=', trim($item), 2), 2, null);
        if ($value === null || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) { fail('Format --default attendu : champ=valeur.'); }
        $defaults[$name] = $value;
    }
    return $defaults;
}

function apiPlural(string $singular): string
{
    if (preg_match('/[^aeiou]y$/i', $singular) === 1) {
        return substr($singular, 0, -1) . 'ies';
    }
    if (preg_match('/(?:s|x|z|ch|sh)$/i', $singular) === 1) {
        return $singular . 'es';
    }
    return $singular . 's';
}

/** @return array{model:string,resource:string,create:string,update:string,controller:string} */
function apiResourcePaths(string $root, string $resource): array
{
    if (is_dir($root . '/src')) {
        return [
            'model' => "src/models/{$resource}.php",
            'resource' => "src/resources/{$resource}Resource.php",
            'create' => "src/requests/Create{$resource}Request.php",
            'update' => "src/requests/Update{$resource}Request.php",
            'controller' => "src/controllers/{$resource}Controller.php",
        ];
    }
    return [
        'model' => "src/models/{$resource}.php",
        'resource' => "app/Resources/{$resource}Resource.php",
        'create' => "app/Requests/Create{$resource}Request.php",
        'update' => "app/Requests/Update{$resource}Request.php",
        'controller' => "app/Controllers/Api/{$resource}Controller.php",
    ];
}

/** @param array{name:string,type:string,nullable:bool,php:string,rule:string,schema:string} $field */
function apiFieldDefaultValue(array $field, string $value): mixed
{
    return match ($field['php']) {
        'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : fail("La valeur de {$field['name']} doit être un entier."),
        'float' => is_numeric($value) ? (float) $value : fail("La valeur de {$field['name']} doit être numérique."),
        'bool' => match (strtolower($value)) { '1', 'true', 'yes', 'on' => true, '0', 'false', 'no', 'off' => false, default => fail("La valeur de {$field['name']} doit être booléenne.") },
        default => $value,
    };
}

/** @param list<string> $arguments */
function addApiFields(string $name, string $definition, array $arguments = []): void
{
    $root = projectRoot();
    $resource = className($name);
    $route = apiPlural(strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $resource)));
    $table = str_replace('-', '_', $route);
    $paths = apiResourcePaths($root, $resource);
    $files = [];
    foreach ($paths as $key => $path) {
        if (!is_file($root . '/' . $path)) { fail("{$path} est introuvable. Utilisez d’abord make:api --fields."); }
        $files[$key] = (string) file_get_contents($root . '/' . $path);
    }
    $fields = apiFields($definition);
    $defaults = apiFieldDefaults(optionValue($arguments, '--default'));
    $csvOption = static fn (string $option): array => array_values(array_filter(array_map('trim', explode(',', (string) (optionValue($arguments, $option) ?? '')))));
    $unique = $csvOption('--unique');
    $indexed = $csvOption('--index');
    $names = array_column($fields, 'name');
    if (count($names) !== count(array_unique($names))) { fail('Un champ est présent plusieurs fois.'); }
    foreach (array_keys($defaults) as $field) { if (!in_array($field, $names, true)) { fail("Champ --default inconnu : {$field}."); } }
    foreach (array_merge($unique, $indexed) as $field) { if (!in_array($field, $names, true)) { fail("Champ d’index inconnu : {$field}."); } }

    foreach ($fields as $field) {
        $fieldName = $field['name'];
        if (preg_match('/public\s+[^;$]+\$' . preg_quote($fieldName, '/') . '\b/', $files['model']) === 1) { fail("Le champ {$fieldName} existe déjà."); }
        $value = array_key_exists($fieldName, $defaults) ? apiFieldDefaultValue($field, $defaults[$fieldName]) : null;
        $nullableMark = $field['nullable'] ? '?' : '';
        $propertyDefault = $field['nullable'] ? ' = null' : (array_key_exists($fieldName, $defaults) ? ' = ' . var_export($value, true) : '');
        $files['model'] = preg_replace('/\n}\s*$/', "\n    public {$nullableMark}{$field['php']} \${$fieldName}{$propertyDefault};\n}\n", $files['model'], 1, $count) ?? $files['model'];
        if ($count !== 1) { fail("Structure inattendue dans {$paths['model']}."); }
        foreach (['resource' => "            '{$fieldName}' => \$item->{$fieldName},\n", 'create' => "            '{$fieldName}' => [" . ($field['nullable'] || array_key_exists($fieldName, $defaults) ? '' : "'required', ") . "'{$field['rule']}'],\n", 'update' => "            '{$fieldName}' => ['{$field['rule']}'],\n"] as $key => $line) {
            $files[$key] = str_replace("        ];\n    }", $line . "        ];\n    }", $files[$key], $count);
            if ($count !== 1) { fail("Structure inattendue dans {$paths[$key]}."); }
        }
        $cast = match ($field['php']) { 'int' => '(int) ', 'float' => '(float) ', 'bool' => '(bool) ', default => '' };
        $fallback = array_key_exists($fieldName, $defaults) ? var_export($value, true) : 'null';
        $assigned = $field['nullable'] || array_key_exists($fieldName, $defaults) ? "(isset(\$data['{$fieldName}']) ? {$cast}\$data['{$fieldName}'] : {$fallback})" : "{$cast}\$data['{$fieldName}']";
        $files['controller'] = str_replace("        \$this->items()->add(\$item);", "        \$item->{$fieldName} = {$assigned};\n        \$this->items()->add(\$item);", $files['controller'], $count);
        if ($count !== 1) { fail("Structure inattendue dans {$paths['controller']}."); }
        $files['controller'] = str_replace("        \$this->items()->update(\$item);", "        if (array_key_exists('{$fieldName}', \$data)) { \$item->{$fieldName} = {$cast}\$data['{$fieldName}']; }\n        \$this->items()->update(\$item);", $files['controller'], $count);
        if ($count !== 1) { fail("Structure inattendue dans {$paths['controller']}."); }
    }
    if (preg_match('/new CollectionQuery\((\[[^\)]*\]), (\[[^\)]*\]), (\[[^\)]*\])\)/', $files['controller'], $match) !== 1) { fail('CollectionQuery généré non reconnu.'); }
    $append = static function (string $array, array $values): string {
        $items = array_filter([trim($array, '[] '), implode(', ', array_map(static fn (string $value): string => "'{$value}'", $values))]);
        return '[' . implode(', ', $items) . ']';
    };
    $searchable = array_column(array_filter($fields, static fn (array $field): bool => in_array($field['type'], ['string', 'text'], true)), 'name');
    $files['controller'] = str_replace($match[0], 'new CollectionQuery(' . $append($match[1], $names) . ', ' . $append($match[2], $names) . ', ' . $append($match[3], $searchable) . ')', $files['controller']);

    $schemaLines = '';
    foreach ($fields as $field) {
        $fieldName = $field['name'];
        $chain = $field['nullable'] ? '->nullable()' : '';
        if (array_key_exists($fieldName, $defaults)) { $chain .= '->default(' . var_export(apiFieldDefaultValue($field, $defaults[$fieldName]), true) . ')'; }
        $schemaLines .= "            \$table->{$field['schema']}('{$fieldName}'){$chain};\n";
        if (in_array($fieldName, $unique, true)) { $schemaLines .= "            \$table->index('{$fieldName}', null, true);\n"; }
        elseif (in_array($fieldName, $indexed, true)) { $schemaLines .= "            \$table->index('{$fieldName}');\n"; }
    }
    $drops = implode("\n", array_map(static fn (string $field): string => "            \$table->dropColumn('{$field}');", array_reverse($names)));
    $stamp = date('YmdHis');
    foreach (glob($root . '/runtime/database/migrations/*.php') ?: [] as $existingMigration) {
        if (preg_match('/\/(\d{14})_/', $existingMigration, $stampMatch) === 1 && $stampMatch[1] >= $stamp) {
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $stampMatch[1]);
            if ($date !== false) { $stamp = $date->modify('+1 second')->format('YmdHis'); }
        }
    }
    $migration = 'runtime/database/migrations/' . $stamp . '_add_' . implode('_', $names) . '_to_' . $table . '_table.php';
    if (is_file($root . '/' . $migration)) { fail('Une migration portant ce nom existe déjà. Réessayez dans une seconde.'); }
    $migrationContent = "<?php\n\ndeclare(strict_types=1);\n\nuse AML\\Data\\Connection;\nuse AML\\Data\\Migrations\\Migration;\nuse AML\\Data\\Schema\\{AlterTable, Schema};\n\nreturn new class extends Migration {\n    public function up(Connection \$connection): void\n    {\n        (new Schema(\$connection))->table('{$table}', function (AlterTable \$table): void {\n{$schemaLines}        });\n    }\n    public function down(Connection \$connection): void\n    {\n        (new Schema(\$connection))->table('{$table}', function (AlterTable \$table): void {\n{$drops}\n        });\n    }\n};\n";
    foreach ($files as $key => $content) { if (file_put_contents($root . '/' . $paths[$key], $content, LOCK_EX) === false) { fail("Impossible de modifier {$paths[$key]}."); } }
    writeNewFile($root, $migration, $migrationContent);
    output('✓ Champs ajoutés à ' . $resource . ' : ' . implode(', ', $names));
    output('  Migration créée : ' . $migration);
}

/** @return array{root:string,resource:string,table:string,paths:array<string,string>,files:array<string,string>} */
function editableApiResource(string $name): array
{
    $root = projectRoot();
    $resource = className($name);
    $route = apiPlural(strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $resource)));
    $paths = apiResourcePaths($root, $resource);
    $files = [];
    foreach ($paths as $key => $path) {
        if (!is_file($root . '/' . $path)) { fail("{$path} est introuvable. Utilisez une ressource persistante générée."); }
        $files[$key] = (string) file_get_contents($root . '/' . $path);
    }
    return compact('root', 'resource', 'paths', 'files') + ['table' => str_replace('-', '_', $route)];
}

function nextApiMigrationStamp(string $root): string
{
    $stamp = date('YmdHis');
    foreach (glob($root . '/runtime/database/migrations/*.php') ?: [] as $path) {
        if (preg_match('/\/(\d{14})_/', $path, $match) === 1 && $match[1] >= $stamp) {
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $match[1]);
            if ($date !== false) { $stamp = $date->modify('+1 second')->format('YmdHis'); }
        }
    }
    return $stamp;
}

/** @return array{unique:bool,index:bool} */
function apiFieldIndexes(string $root, string $field): array
{
    $all = '';
    foreach (glob($root . '/runtime/database/migrations/*.php') ?: [] as $path) { $all .= (string) file_get_contents($path); }
    return [
        'unique' => str_contains($all, "index('{$field}', null, true)"),
        'index' => str_contains($all, "index('{$field}')"),
    ];
}

function renameApiField(string $name, string $old, string $new): void
{
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $old) !== 1 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $new) !== 1 || in_array('id', [$old, $new], true)) { fail('Nom de champ invalide.'); }
    $api = editableApiResource($name);
    if (preg_match('/\$' . preg_quote($old, '/') . '\b/', $api['files']['model']) !== 1) { fail("Le champ {$old} n’existe pas."); }
    if (preg_match('/\$' . preg_quote($new, '/') . '\b/', $api['files']['model']) === 1) { fail("Le champ {$new} existe déjà."); }
    foreach ($api['files'] as $key => $content) {
        $content = str_replace("'{$old}'", "'{$new}'", $content);
        $api['files'][$key] = preg_replace('/\$' . preg_quote($old, '/') . '\b/', '\$' . $new, $content) ?? $content;
    }
    $indexes = apiFieldIndexes($api['root'], $old);
    $dropIndexes = ($indexes['unique'] ? "            \$table->dropIndexIfExists('{$api['table']}_{$old}_unique');\n" : '')
        . ($indexes['index'] ? "            \$table->dropIndexIfExists('{$api['table']}_{$old}_index');\n" : '');
    $createIndexes = ($indexes['unique'] ? "            \$table->index('{$new}', null, true);\n" : '')
        . ($indexes['index'] ? "            \$table->index('{$new}');\n" : '');
    $dropNewIndexes = ($indexes['unique'] ? "            \$table->dropIndexIfExists('{$api['table']}_{$new}_unique');\n" : '')
        . ($indexes['index'] ? "            \$table->dropIndexIfExists('{$api['table']}_{$new}_index');\n" : '');
    $restoreIndexes = ($indexes['unique'] ? "            \$table->index('{$old}', null, true);\n" : '')
        . ($indexes['index'] ? "            \$table->index('{$old}');\n" : '');
    $stamp = nextApiMigrationStamp($api['root']);
    $migration = "runtime/database/migrations/{$stamp}_rename_{$old}_to_{$new}_on_{$api['table']}_table.php";
    $body = "<?php\n\ndeclare(strict_types=1);\n\nuse AML\\Data\\Connection;\nuse AML\\Data\\Migrations\\Migration;\nuse AML\\Data\\Schema\\{AlterTable, Schema};\n\nreturn new class extends Migration {\n    public function up(Connection \$connection): void\n    {\n        (new Schema(\$connection))->table('{$api['table']}', function (AlterTable \$table): void {\n{$dropIndexes}            \$table->renameColumn('{$old}', '{$new}');\n{$createIndexes}        });\n    }\n    public function down(Connection \$connection): void\n    {\n        (new Schema(\$connection))->table('{$api['table']}', function (AlterTable \$table): void {\n{$dropNewIndexes}            \$table->renameColumn('{$new}', '{$old}');\n{$restoreIndexes}        });\n    }\n};\n";
    foreach ($api['files'] as $key => $content) { if (file_put_contents($api['root'] . '/' . $api['paths'][$key], $content, LOCK_EX) === false) { fail("Impossible de modifier {$api['paths'][$key]}."); } }
    writeNewFile($api['root'], $migration, $body);
    output("✓ {$api['resource']}.{$old} renommé en {$new}");
}

function removeApiField(string $name, string $field): void
{
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) !== 1 || $field === 'id') { fail('Nom de champ invalide.'); }
    $api = editableApiResource($name);
    if (preg_match('/^\s*public\s+(\??)(string|int|float|bool)\s+\$' . preg_quote($field, '/') . '(?:\s*=\s*([^;]+))?;\s*$/m', $api['files']['model'], $property) !== 1) { fail("Le champ {$field} n’existe pas ou son type n’est pas pris en charge."); }
    $schema = ['string' => 'string', 'int' => 'integer', 'float' => 'decimal', 'bool' => 'boolean'][$property[2]];
    foreach (['model', 'resource', 'create', 'update'] as $key) {
        $api['files'][$key] = preg_replace('/^.*(?:\$' . preg_quote($field, '/') . '\b|\'' . preg_quote($field, '/') . '\').*\R/m', '', $api['files'][$key]) ?? $api['files'][$key];
    }
    $api['files']['controller'] = preg_replace('/^.*(?:\$item->' . preg_quote($field, '/') . '\b|array_key_exists\(\'' . preg_quote($field, '/') . '\').*\R/m', '', $api['files']['controller']) ?? $api['files']['controller'];
    $api['files']['controller'] = preg_replace_callback('/new CollectionQuery\((\[[^\)]*\]), (\[[^\)]*\]), (\[[^\)]*\])\)/', static function (array $match) use ($field): string {
        $clean = static fn (string $array): string => preg_replace(["/'" . preg_quote($field, '/') . "'\s*,\s*/", "/,\s*'" . preg_quote($field, '/') . "'/", "/'" . preg_quote($field, '/') . "'/"], ['', '', ''], $array) ?? $array;
        return 'new CollectionQuery(' . $clean($match[1]) . ', ' . $clean($match[2]) . ', ' . $clean($match[3]) . ')';
    }, $api['files']['controller']) ?? $api['files']['controller'];
    $chain = $property[1] === '?' ? '->nullable()' : '';
    if (isset($property[3]) && trim($property[3]) !== 'null') { $chain .= '->default(' . trim($property[3]) . ')'; }
    $stamp = nextApiMigrationStamp($api['root']);
    $indexes = apiFieldIndexes($api['root'], $field);
    $dropIndexes = ($indexes['unique'] ? "            \$table->dropIndexIfExists('{$api['table']}_{$field}_unique');\n" : '')
        . ($indexes['index'] ? "            \$table->dropIndexIfExists('{$api['table']}_{$field}_index');\n" : '');
    $restoreIndexes = ($indexes['unique'] ? "            \$table->index('{$field}', null, true);\n" : '')
        . ($indexes['index'] ? "            \$table->index('{$field}');\n" : '');
    $migration = "runtime/database/migrations/{$stamp}_remove_{$field}_from_{$api['table']}_table.php";
    $body = "<?php\n\ndeclare(strict_types=1);\n\nuse AML\\Data\\Connection;\nuse AML\\Data\\Migrations\\Migration;\nuse AML\\Data\\Schema\\{AlterTable, Schema};\n\nreturn new class extends Migration {\n    public function up(Connection \$connection): void\n    {\n        (new Schema(\$connection))->table('{$api['table']}', function (AlterTable \$table): void {\n{$dropIndexes}            \$table->dropColumn('{$field}');\n        });\n    }\n    public function down(Connection \$connection): void\n    {\n        (new Schema(\$connection))->table('{$api['table']}', function (AlterTable \$table): void {\n            \$table->{$schema}('{$field}'){$chain};\n{$restoreIndexes}        });\n    }\n};\n";
    foreach ($api['files'] as $key => $content) { if (file_put_contents($api['root'] . '/' . $api['paths'][$key], $content, LOCK_EX) === false) { fail("Impossible de modifier {$api['paths'][$key]}."); } }
    writeNewFile($api['root'], $migration, $body);
    output("✓ Champ {$api['resource']}.{$field} supprimé");
}

/** @param list<string> $arguments */
function generateApi(string $name, array $arguments = []): void
{
    $root = projectRoot();
    $manifest = projectInfo($root);
    $modernApi = ($manifest['application']['type'] ?? null) === 'api';
    if (!$modernApi && !is_file($root . '/configs/api.php')) { installApi(); }
    $resource = className($name);
    $controller = $resource . 'Controller';
    $route = apiPlural(strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $resource)));
    $paths = apiResourcePaths($root, $resource);
    $path = $paths['controller'];
    foreach (["app/Controllers/Api/{$controller}.php", "src/controllers/api/{$controller}.php", "src/controllers/{$controller}.php"] as $existingController) {
        if (is_file($root . '/' . $existingController)) {
            fail("Le fichier '{$existingController}' existe déjà.");
        }
    }
    $persistent = in_array('--model', $arguments, true) || in_array('--migration', $arguments, true) || optionValue($arguments, '--fields') !== null;
    if (!$persistent) {
        $content = str_replace('{{CONTROLLER}}', $controller, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use PHPAML\Api\ApiResponse;
use PHPAML\Http\Request;
use PHPAML\Http\Response;

final class {{CONTROLLER}}
{
    public function index(Request $request): Response { return ApiResponse::ok([]); }
    public function store(Request $request): Response { return ApiResponse::created($request->input()); }
    public function show(Request $request): Response { return ApiResponse::ok(['id' => $request->attribute('id')]); }
    public function update(Request $request): Response { return ApiResponse::ok(array_merge(['id' => $request->attribute('id')], $request->input())); }
    public function destroy(Request $request): Response { return ApiResponse::noContent(); }
}
PHP
        );
    } else {
        bootstrapProject($root);
        if (!class_exists(\AML\Data\Entity::class)) { fail("Le module phpaml/data est absent. Exécutez 'aml install data'."); }
        $manifest = projectInfo($root);
        if ($modernApi) {
            if (!is_array($manifest['data'] ?? null)) { fail("La section data de phpaml.json est absente. Exécutez 'aml install data'."); }
        } else {
            if (!is_file($root . '/configs/data.php')) { fail("configs/data.php est absent. Exécutez 'aml install data'."); }
            $appPath = $root . '/configs/app.php';
            $appConfig = (string) file_get_contents($appPath);
            if (!str_contains($appConfig, "'data' => require __DIR__ . '/data.php'")) {
                $appConfig = str_replace("    'api' => require __DIR__ . '/api.php',", "    'api' => require __DIR__ . '/api.php',\n    'data' => require __DIR__ . '/data.php',", $appConfig);
                if (file_put_contents($appPath, $appConfig, LOCK_EX) === false) { fail('Impossible de brancher configs/data.php.'); }
            }
        }
        $fields = apiFields(optionValue($arguments, '--fields'));
        $table = str_replace('-', '_', $route);
        $modelProperties = '';
        $resourceProperties = "            'id' => \$item->id,\n";
        $assignCreate = '';
        $assignUpdate = '';
        $createRules = '';
        $updateRules = '';
        $schemaLines = '';
        $fieldNames = [];
        $searchableNames = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $nullableMark = $field['nullable'] ? '?' : '';
            $default = $field['nullable'] ? ' = null' : '';
            $required = $field['nullable'] ? '' : "'required', ";
            $modelProperties .= "    public {$nullableMark}{$field['php']} \${$fieldName}{$default};\n";
            $resourceProperties .= "            '{$fieldName}' => \$item->{$fieldName},\n";
            $cast = match ($field['php']) { 'int' => '(int) ', 'float' => '(float) ', 'bool' => '(bool) ', default => '' };
            $assigned = $field['nullable']
                ? "(isset(\$data['{$fieldName}']) ? {$cast}\$data['{$fieldName}'] : null)"
                : "{$cast}\$data['{$fieldName}']";
            $assignCreate .= "        \$item->{$fieldName} = {$assigned};\n";
            $assignUpdate .= "        if (array_key_exists('{$fieldName}', \$data)) { \$item->{$fieldName} = {$cast}\$data['{$fieldName}']; }\n";
            $createRules .= "            '{$fieldName}' => [{$required}'{$field['rule']}'],\n";
            $updateRules .= "            '{$fieldName}' => ['{$field['rule']}'],\n";
            $nullable = $field['nullable'] ? '->nullable()' : '';
            $schemaLines .= "            \$table->{$field['schema']}('{$fieldName}'){$nullable};\n";
            $fieldNames[] = $fieldName;
            if (in_array($field['type'], ['string', 'text'], true)) { $searchableNames[] = $fieldName; }
        }
        writeNewFile($root, $paths['model'], "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\nuse AML\\Data\\Entity;\nuse AML\\Data\\Metadata\\{Key, Table};\n\n#[Table('{$table}')]\nfinal class {$resource} extends Entity\n{\n    #[Key]\n    public int \$id;\n{$modelProperties}}\n");
        writeNewFile($root, $paths['resource'], "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Resources;\n\nuse App\\Models\\{$resource};\n\nfinal class {$resource}Resource\n{\n    /** @return array<string, mixed> */\n    public static function make({$resource} \$item): array\n    {\n        return [\n{$resourceProperties}        ];\n    }\n}\n");
        writeNewFile($root, $paths['create'], "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Requests;\n\nuse PHPAML\\Api\\ApiRequest;\n\nfinal class Create{$resource}Request extends ApiRequest\n{\n    public function rules(): array\n    {\n        return [\n{$createRules}        ];\n    }\n}\n");
        writeNewFile($root, $paths['update'], "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Requests;\n\nuse PHPAML\\Api\\ApiRequest;\n\nfinal class Update{$resource}Request extends ApiRequest\n{\n    public function rules(): array\n    {\n        return [\n{$updateRules}        ];\n    }\n}\n");
        if (in_array('--migration', $arguments, true) || optionValue($arguments, '--fields') !== null) {
            $migration = 'runtime/database/migrations/' . date('YmdHis') . '_create_' . $table . '_table.php';
            writeNewFile($root, $migration, "<?php\n\ndeclare(strict_types=1);\n\nuse AML\\Data\\Connection;\nuse AML\\Data\\Migrations\\Migration;\nuse AML\\Data\\Schema\\{Schema, Table};\n\nreturn new class extends Migration {\n    public function up(Connection \$connection): void\n    {\n        (new Schema(\$connection))->create('{$table}', function (Table \$table): void {\n            \$table->id();\n{$schemaLines}            \$table->timestamps();\n        });\n    }\n    public function down(Connection \$connection): void { (new Schema(\$connection))->dropIfExists('{$table}'); }\n};\n");
        }
        $content = str_replace(
            ['{{RESOURCE}}', '{{CONTROLLER}}', '{{ROUTE}}', '{{CREATE_ASSIGN}}', '{{UPDATE_ASSIGN}}', '{{FIELDS_ARRAY}}', '{{SEARCHABLE_ARRAY}}'],
            [$resource, $controller, $route, rtrim($assignCreate), rtrim($assignUpdate), "['" . implode("', '", $fieldNames) . "']", $searchableNames === [] ? '[]' : "['" . implode("', '", $searchableNames) . "']"],
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use AML\Data\Connection;
use AML\Data\DbSet;
use App\Models\{{RESOURCE}};
use App\Requests\Create{{RESOURCE}}Request;
use App\Requests\Update{{RESOURCE}}Request;
use App\Resources\{{RESOURCE}}Resource;
use PHPAML\Api\ApiResponse;
use PHPAML\Api\CollectionQuery;
use PHPAML\Http\Request;
use PHPAML\Http\Response;

final class {{CONTROLLER}}
{
    public function __construct(private Connection $connection) {}
    private function items(): DbSet { return new DbSet($this->connection, {{RESOURCE}}::class); }
    public function index(Request $request): Response
    {
        $parameters = (new CollectionQuery({{FIELDS_ARRAY}}, {{FIELDS_ARRAY}}, {{SEARCHABLE_ARRAY}}))->parse($request->query());
        $query = $this->items();
        foreach ($parameters['filters'] as $field => $value) { $query = $query->where($field, '=', $value); }
        foreach ($parameters['sort'] as $sort) { $query = $query->orderBy($sort['field'], $sort['direction']); }
        if ($parameters['search'] !== null && $parameters['searchable'] !== []) {
            $query = $query->where($parameters['searchable'][0], 'LIKE', '%' . $parameters['search'] . '%');
        }
        $result = $query->paginate($parameters['page'], $parameters['per_page']);
        return ApiResponse::collection(array_map({{RESOURCE}}Resource::make(...), $result->items), [
            'current_page' => $result->page, 'per_page' => $result->perPage,
            'total' => $result->total, 'last_page' => $result->lastPage(),
        ]);
    }
    public function store(Request $request): Response
    {
        $data = (new Create{{RESOURCE}}Request())->validated($request);
        $item = new {{RESOURCE}}();
{{CREATE_ASSIGN}}
        $this->items()->add($item);
        return ApiResponse::created({{RESOURCE}}Resource::make($item));
    }
    public function show(Request $request): Response
    {
        $item = $this->items()->find((string) $request->attribute('id'));
        return $item === null ? ApiResponse::error('NOT_FOUND', '{{RESOURCE}} introuvable.', 404) : ApiResponse::ok({{RESOURCE}}Resource::make($item));
    }
    public function update(Request $request): Response
    {
        $item = $this->items()->find((string) $request->attribute('id'));
        if ($item === null) { return ApiResponse::error('NOT_FOUND', '{{RESOURCE}} introuvable.', 404); }
        $data = (new Update{{RESOURCE}}Request())->validated($request);
{{UPDATE_ASSIGN}}
        $this->items()->update($item);
        return ApiResponse::ok({{RESOURCE}}Resource::make($item));
    }
    public function destroy(Request $request): Response
    {
        $item = $this->items()->find((string) $request->attribute('id'));
        if ($item === null) { return ApiResponse::error('NOT_FOUND', '{{RESOURCE}} introuvable.', 404); }
        $this->items()->remove($item);
        return ApiResponse::noContent();
    }
}
PHP
        );
    }
    if ($modernApi) {
        $content = str_replace('namespace App\\Controllers\\Api;', 'namespace App\\Controllers;', $content);
    }
    if (!writeNewFile($root, $path, $content)) { fail("Le fichier '{$path}' existe déjà."); }

    if ($modernApi) {
        $routeClass = $resource . 'Route';
        writeNewFile($root, "src/routes/{$routeClass}.php", "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Routes;\n\nuse App\\Controllers\\{$controller};\nuse PHPAML\\Routing\\Route;\n\nfinal class {$routeClass} extends Route\n{\n    protected string \$prefix = '/api/v1';\n\n    protected function routes(): void\n    {\n        \$this->apiResource('/{$route}', {$controller}::class);\n    }\n}\n");
        $composerPath = $root . '/composer.json';
        if (is_file($composerPath)) {
            $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
            $composer['autoload']['psr-4'] = is_array($composer['autoload']['psr-4'] ?? null) ? $composer['autoload']['psr-4'] : [];
            foreach ([
                'App\\Controllers\\' => 'src/controllers/',
                'App\\Models\\' => 'src/models/',
                'App\\Repositories\\' => 'src/repositories/',
                'App\\Requests\\' => 'src/requests/',
                'App\\Resources\\' => 'src/resources/',
                'App\\Routes\\' => 'src/routes/',
                'App\\Middleware\\' => 'src/middleware/',
            ] as $namespace => $directory) {
                $composer['autoload']['psr-4'][$namespace] = $directory;
            }
            file_put_contents($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
        }
        output("✓ API {$resource} créée : /api/v1/{$route}");
        return;
    }

    $routesPath = $root . '/configs/api-routes.php';
    $routes = (string) file_get_contents($routesPath);
    $import = "use App\\Controllers\\Api\\{$controller};";
    if (!str_contains($routes, $import)) {
        $routes = str_replace("declare(strict_types=1);", "declare(strict_types=1);\n\n{$import}", $routes);
    }
    $auth = in_array('--auth', $arguments, true);
    if ($auth) {
        foreach (['use PHPAML\\Middleware\\ApiAuthMiddleware;', 'use PHPAML\\Middleware\\AbilityMiddleware;'] as $middlewareImport) {
            if (!str_contains($routes, $middlewareImport)) {
                $routes = str_replace("declare(strict_types=1);", "declare(strict_types=1);\n\n{$middlewareImport}", $routes);
            }
        }
    }
    $readAbility = optionValue($arguments, '--read-ability') ?? str_replace('-', '.', $route) . '.read';
    $writeAbility = optionValue($arguments, '--write-ability') ?? str_replace('-', '.', $route) . '.write';
    $entry = static function (string $handler, string $ability) use ($controller, $auth): string {
        if (!$auth) { return "[{$controller}::class, '{$handler}']"; }
        return "['handler' => [{$controller}::class, '{$handler}'], 'middleware' => [ApiAuthMiddleware::class, AbilityMiddleware::class], 'abilities' => ['{$ability}']]";
    };
    $entries = "    'GET /api/v1/{$route}' => " . $entry('index', $readAbility) . ",\n"
        . "    'POST /api/v1/{$route}' => " . $entry('store', $writeAbility) . ",\n"
        . "    'GET /api/v1/{$route}/{id}' => " . $entry('show', $readAbility) . ",\n"
        . "    'PUT /api/v1/{$route}/{id}' => " . $entry('update', $writeAbility) . ",\n"
        . "    'DELETE /api/v1/{$route}/{id}' => " . $entry('destroy', $writeAbility) . ",\n";
    $position = strrpos($routes, '];');
    if ($position === false) { fail('configs/api-routes.php est invalide.'); }
    $routes = substr($routes, 0, $position) . $entries . substr($routes, $position);
    if (file_put_contents($routesPath, $routes, LOCK_EX) === false) { fail('Impossible de modifier configs/api-routes.php.'); }
    output("✓ API {$resource} créée : /api/v1/{$route}");
}

function issueApiToken(string $owner, string $name = 'cli'): void
{
    $config = loadProjectConfig();
    $application = new \PHPAML\WebApplication($config);
    $manager = $application->container()->get(\PHPAML\Api\TokenManager::class);
    if (!$manager instanceof \PHPAML\Api\TokenManager) { fail('Le gestionnaire de tokens API est indisponible.'); }
    output($manager->issue($owner, $name));
}

/** @return array<string, mixed> */
function apiOpenApiDocument(): array
{
    $root = projectRoot();
    bootstrapProject($root);
    $config = loadProjectConfig();
    $application = new \PHPAML\WebApplication($config);
    $routes = [];
    foreach ($application->router()->routes() as $route) {
        $routes[$route['method'] . ' ' . $route['path']] = ['name' => $route['name'] ?? null];
    }
    return (new \PHPAML\Api\OpenApiGenerator(
        (string) ($config['name'] ?? 'PHPAML API'),
        (string) (projectInfo($root)['version'] ?? '1.0.0')
    ))->generate($routes, '/api');
}

function generateOpenApi(?string $outputPath = null): void
{
    $root = projectRoot();
    $relative = $outputPath ?? 'public/openapi.json';
    $path = str_starts_with($relative, '/') ? $relative : $root . '/' . ltrim($relative, '/');
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) { fail('Impossible de créer le dossier OpenAPI.'); }
    $json = json_encode(apiOpenApiDocument(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) { fail('Impossible de générer OpenAPI.'); }
    output('✓ OpenAPI généré : ' . $relative);
}

function generateApiClient(?string $outputPath = null): void
{
    $root = projectRoot();
    bootstrapProject($root);
    $relative = $outputPath ?? 'resources/ts/phpaml-api.ts';
    $path = str_starts_with($relative, '/') ? $relative : $root . '/' . ltrim($relative, '/');
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) { fail('Impossible de créer le dossier du client API.'); }
    $client = (new \PHPAML\Api\TypeScriptClientGenerator())->generate(apiOpenApiDocument());
    if (file_put_contents($path, $client, LOCK_EX) === false) { fail('Impossible de générer le client TypeScript.'); }
    output('✓ Client TypeScript généré : ' . $relative);
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
    $relative = 'runtime/database/migrations/' . date('Ymd_His') . '_' . $className . '.php';
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
    $migrator = new \PHPAML\Data\Migrator($connection, $root . '/runtime/database/migrations');
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
        try {
            $temporaryRoot = amlWritableTemporaryRoot();
        } catch (RuntimeException $error) {
            output($error->getMessage());
            return 1;
        }
        $command = escapeshellarg(PHP_BINARY)
            . ' -d ' . escapeshellarg('session.save_path=' . $temporaryRoot)
            . ' ' . escapeshellarg($suite);
        passthru($command, $exitCode);
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

    $port = $requestedPort === null ? 8910 : filter_var($requestedPort, FILTER_VALIDATE_INT);
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
        $declarativeConfig = is_array($projectInfo['application'] ?? null);
        $legacyConfig = is_file($current . '/config/app.php') || is_file($current . '/configs/app.php');
        doctorAdd(
            $checks,
            ($declarativeConfig || $legacyConfig) ? 'ok' : 'error',
            'Configuration',
            $declarativeConfig ? 'phpaml.json déclaratif' : ($legacyConfig ? 'configuration PHP historique' : 'configuration absente')
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

/** @return array<string, mixed> */
function seoConfig(): array
{
    $manifest = projectInfo();
    if (is_array($manifest['seo'] ?? null)) {
        return $manifest['seo'];
    }
    $legacy = projectRoot() . '/configs/seo.json';
    if (is_file($legacy)) {
        $config = json_decode((string) file_get_contents($legacy), true);
        if (is_array($config)) return $config;
    }
    fail("La configuration SEO est absente. Exécutez 'aml seo:init'.");
}

/** @param array<string, mixed> $config */
function writeSeoConfig(array $config): void
{
    $manifest = projectInfo();
    $manifest['seo'] = $config;
    writeProjectManifest(projectRoot(), $manifest);
}

function seoInit(bool $force = false): void
{
    $info = projectInfo();
    if (is_array($info['seo'] ?? null) && !$force) {
        fail("La section seo de phpaml.json existe déjà. Utilisez 'aml seo:init --force' pour la remplacer.");
    }
    $environment = readEnvValues(projectRoot() . '/.env');
    $name = (string) ($info['name'] ?? 'PHPAML application');
    writeSeoConfig([
        'site_name' => $name,
        'title' => $name,
        'description' => '',
        'base_url' => rtrim((string) ($environment['APP_URL'] ?? 'http://127.0.0.1:8910'), '/'),
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
    output('✓ Configuration SEO créée dans phpaml.json');
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
    $manifest = projectInfo();
    $seo = is_array($manifest['seo'] ?? null) || is_file(projectRoot() . '/configs/seo.json') ? seoConfig() : [];
    $url ??= (string) ($seo['base_url'] ?? 'http://127.0.0.1:8910');
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
    $legacyDatabase = $root . '/database';
    $runtimeDatabase = $root . '/runtime/database';

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
    if (is_dir($legacyDatabase) && is_dir($runtimeDatabase)) {
        $conflicts[] = 'database/ + runtime/database/';
    }
    if ($conflicts !== []) {
        fail('Migration impossible : conflits détectés (' . implode(', ', $conflicts) . ').');
    }

    $renameManifest = is_file($legacyManifest);
    $renameRuntime = is_dir($legacyRuntime);
    $renameView = is_dir($legacyView);
    $renameDatabase = is_dir($legacyDatabase);
    if (!$renameManifest && !$renameRuntime && !$renameView && !$renameDatabase) {
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
    if ($renameDatabase) {
        output('  database/ → runtime/database/');
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
                    && !str_contains($content, 'App\\View')
                    && !str_contains($content, 'database/migrations')
                    && !str_contains($content, 'database/seeders'))) {
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
            $protected = str_replace(
                ['runtime/database/migrations', 'runtime/database/seeders'],
                ['__PHPAML_RUNTIME_MIGRATIONS__', '__PHPAML_RUNTIME_SEEDERS__'],
                $content
            );
            $updated = str_replace(
                ['aml_env', 'info.json', 'app/View', 'App\\View', 'database/migrations', 'database/seeders'],
                ['runtime', 'phpaml.json', 'app/UI', 'App\\UI', 'runtime/database/migrations', 'runtime/database/seeders'],
                $protected
            );
            $updated = str_replace(
                ['__PHPAML_RUNTIME_MIGRATIONS__', '__PHPAML_RUNTIME_SEEDERS__'],
                ['runtime/database/migrations', 'runtime/database/seeders'],
                $updated
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
        if ($renameDatabase) {
            if (!is_dir($runtime) && !mkdir($runtime, 0755, true) && !is_dir($runtime)) {
                throw new RuntimeException('Unable to create runtime');
            }
            if (!rename($legacyDatabase, $runtimeDatabase)) {
                throw new RuntimeException('Unable to migrate database');
            }
        }
        if ($renameView && !rename($legacyView, $ui)) {
            throw new RuntimeException('Unable to migrate app/View');
        }
    } catch (Throwable $error) {
        if (is_dir($runtimeDatabase) && !is_dir($legacyDatabase) && $renameDatabase) {
            @rename($runtimeDatabase, $legacyDatabase);
        }
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

if (defined('PHPAML_CLI_LIBRARY_MODE') && PHPAML_CLI_LIBRARY_MODE === true) {
    return;
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
        aiDebug(in_array('--fix', $arguments, true), in_array('--yes', $arguments, true), in_array('--include-code', $arguments, true), $debugProblem);
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
            in_array('--offline', $arguments, true),
            !in_array('--no-install', $arguments, true)
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
    case 'create-api':
        $destination = isset($arguments[1]) && !str_starts_with($arguments[1], '--') ? $arguments[1] : '.';
        createApiApplication(
            $destination,
            optionValue($arguments, '--template-version') ?? optionValue($arguments, '--version'),
            in_array('--refresh', $arguments, true),
            in_array('--offline', $arguments, true)
        );
        break;
    case 'serve':
        serve($arguments[1] ?? '127.0.0.1:8910');
    case 'build':
        buildProject(in_array('--skip-tests', $arguments, true));
    case 'deploy:configure':
        isset($arguments[1]) ? deployConfigure($arguments[1], $arguments) : fail('Indiquez le nom du profil.');
        break;
    case 'deploy:check':
        isset($arguments[1]) ? deployCheck($arguments[1]) : fail('Indiquez le nom du profil.');
    case 'deploy':
        isset($arguments[1]) ? deployProject(
            $arguments[1],
            in_array('--skip-build', $arguments, true),
            in_array('--dry-run', $arguments, true)
        ) : fail('Indiquez le nom du profil.');
    case 'deploy:status':
        isset($arguments[1]) ? deployProject(
            $arguments[1],
            in_array('--skip-build', $arguments, true),
            true,
            true
        ) : fail('Indiquez le nom du profil.');
    case 'deploy:history':
        deployShowHistory(isset($arguments[1]) && !str_starts_with($arguments[1], '--') ? $arguments[1] : null);
    case 'deploy:rollback':
        isset($arguments[1]) ? deployRollback($arguments[1]) : fail('Indiquez le nom du profil.');
    case 'deploy:prune':
        isset($arguments[1]) ? deployPrune($arguments[1], (int) (optionValue($arguments, '--keep') ?? 5)) : fail('Indiquez le nom du profil.');
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
    case 'api:install':
        installApi();
        break;
    case 'make:api':
        isset($arguments[1]) ? generateApi($arguments[1], $arguments) : fail('Indiquez le nom de la ressource API.');
        break;
    case 'api:add-field':
        isset($arguments[1], $arguments[2]) ? addApiFields($arguments[1], $arguments[2], $arguments) : fail('Usage : aml api:add-field <ressource> <champs>.');
        break;
    case 'api:rename-field':
        isset($arguments[1], $arguments[2], $arguments[3]) ? renameApiField($arguments[1], $arguments[2], $arguments[3]) : fail('Usage : aml api:rename-field <ressource> <ancien> <nouveau>.');
        break;
    case 'api:remove-field':
        isset($arguments[1], $arguments[2]) ? removeApiField($arguments[1], $arguments[2]) : fail('Usage : aml api:remove-field <ressource> <champ>.');
        break;
    case 'api:token':
        isset($arguments[1]) ? issueApiToken($arguments[1], optionValue($arguments, '--name') ?? 'cli') : fail('Indiquez le propriétaire du token.');
        break;
    case 'api:openapi':
        generateOpenApi(optionValue($arguments, '--output'));
        break;
    case 'api:client':
        generateApiClient(optionValue($arguments, '--output'));
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
