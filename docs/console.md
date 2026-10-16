# Programmes console

`aml create-console` génère un programme autonome avec PHP 8.2 ou plus récent.
Les questions et le traitement des arguments sont fournis dans `lib/console.php`,
sous le namespace `AML\Console`. La création fonctionne hors ligne, sans Composer
ni framework Web. `Program::main(array $arguments): int` reste le point d’entrée.

```bash
aml create-console bonjour
cd bonjour
aml run -- André
aml run -- André --repeat=2
aml run -- -i
aml run -- --help
aml test
```

Le programme généré salue une fois par défaut. `-r` est le raccourci de
`--repeat`, qui accepte un entier de 1 à 100. `-i` demande le nom dans le terminal.

## Exemple complet : importer des contacts CSV

Chaque nouveau projet contient `examples/contacts.csv`, le lanceur
`examples/import-csv.php` et la classe `App\Examples\ImportCsv` dans
`src/Examples/ImportCsv.php`. L’exemple fonctionne sans base de données ni
dépendance Composer et enregistre les contacts dans un nouveau fichier JSON.

```bash
php examples/import-csv.php --help
php examples/import-csv.php examples/contacts.csv --dry-run
php examples/import-csv.php examples/contacts.csv --output=contacts.json
```

Le CSV doit être en UTF-8, avec exactement les colonnes `name` et `email`, dans
l’ordre de votre choix. Le BOM UTF-8, les fins de ligne LF/CRLF, les lignes vides,
les virgules entre guillemets et les guillemets doublés sont acceptés. Les espaces
en bordure des valeurs sont retirés. Pour un fichier séparé par des points-virgules :

```bash
php examples/import-csv.php contacts.csv --delimiter=";" --output=contacts.json
```

Le nom et le courriel sont obligatoires, sur une seule ligne et sans caractères
de contrôle. Le courriel doit passer la validation PHP `FILTER_VALIDATE_EMAIL` ;
cet exemple considère les doublons sans tenir compte de la casse. Il accepte
au maximum 10 Mio et 10 000 contacts. Les erreurs indiquent la ligne physique de
début de l’enregistrement. Les 20 premières lignes invalides sont détaillées,
et le bilan compte toutes les lignes de données valides et invalides.

La progression mesure la **validation** : 100 % signifie que toutes les lignes
ont été examinées, pas que l’import a réussi. Si une ligne est invalide, aucun
contact n’est enregistré. `--dry-run` valide les données sans créer de fichier
ni vérifier les droits d’écriture de la destination. Un import réussi produit
un tableau JSON d’objets `{"name": "...", "email": "..."}`. Le dossier de sortie
doit déjà exister ; un fichier ou lien existant n’est jamais remplacé. Un échec
d’écriture détecté supprime le nouveau fichier incomplet. Un arrêt brutal du
processus pendant l’écriture peut toutefois laisser un fichier à retirer.

Le bilan est envoyé sur `STDOUT` ; la progression et les diagnostics sont sur
`STDERR`. Le code de sortie vaut **0** pour un import ou une simulation réussis,
**2** pour une entrée invalide, et **1** pour un échec d’enregistrement.
Une erreur de structure CSV arrête la lecture avant le bilan détaillé.

La classe peut aussi servir de commande : ajoutez
`"import-contacts": "App\\Examples\\ImportCsv"` dans `application.commands` de
`phpaml.json`, puis lancez `aml run import-contacts examples/contacts.csv --dry-run`.
L’exemple n’est pas enregistré automatiquement et conserve le programme d’accueil.
Dans un ancien projet, copiez le lanceur, la classe et le CSV d’un nouveau projet
pour l’essayer avec sa bibliothèque console.

## Définir les arguments

```php
<?php

declare(strict_types=1);

namespace App;

use AML\Console\Console;
use AML\Console\Input;

final class Program
{
    public static function main(array $arguments): int
    {
        $input = Input::define('Préparer un import de données.')
            ->argument('file', 'Fichier à importer', required: true)
            ->option('limit', 'Nombre maximal de lignes', type: 'int', default: 100, shortcut: 'l', min: 1)
            ->flag('dry-run', 'Afficher les paramètres sans importer', shortcut: 'd')
            ->parse($arguments);

        if ($input->helpRequested()) {
            Console::line($input->help());
            return 0;
        }

        Console::line('Fichier : ' . $input->argument('file'));
        Console::line('Limite : ' . $input->option('limit'));
        if ($input->option('dry-run')) Console::line('Simulation demandée.');
        return 0;
    }
}
```

Après avoir remplacé `src/Program.php` par cet exemple :

```bash
aml run -- clients.csv --limit=20 --dry-run
aml run -- clients.csv -l 20 -d
aml run -- --help
```

| Déclaration | Comportement |
| --- | --- |
| `argument('name', default: 'PHP')` | Argument positionnel facultatif, de type texte |
| `argument('file', required: true)` | Argument positionnel obligatoire |
| `option('limit', type: 'int', default: 100, min: 1, max: 1000)` | Option nommée avec type, défaut et bornes |
| `option('token', required: true)` | Option nommée obligatoire, sans valeur par défaut |
| `flag('verbose', shortcut: 'v')` | Booléen : `false` par défaut, `true` si présent |

Les types de valeurs sont `string` et `int`. Les entiers sont décimaux, sans
zéro initial, dans les limites des entiers PHP. `min` et `max` concernent les
entiers. Les arguments obligatoires précèdent les arguments facultatifs.
Une option facultative sans défaut vaut `null`. Un nom inconnu dans
`argument()` ou `option()` sur le résultat est une erreur de programmation.

Les formes `--limit=20`, `--limit 20`, `-l=20` et `-l 20` sont acceptées.
Les raccourcis restent séparés : `-d -v`, sans regroupement `-dv`.
Une option inconnue, répétée, sans valeur ou avec une valeur invalide est refusée.
Un drapeau n’accepte pas de valeur : utilisez `--verbose`, sans `=true`.

Pour une valeur texte commençant par un tiret, utilisez `--name=-value`.
Le premier `--` de `aml run -- ...` est retiré par AML ; un second termine
l’analyse des options du programme. Par exemple, dans le programme généré,
`aml run -- -- --help` salue le nom littéral `--help`.

`-h` et `--help` sont réservés. L’aide contient les noms, descriptions, types,
défauts et bornes. Elle dispense de fournir les entrées obligatoires ; il faut
traiter `helpRequested()` avant d’utiliser les valeurs ou de poser une question.
`Input::define(description: '...', usage: 'php script.php')` personnalise la
ligne d’utilisation pour une exécution indépendante.

## Plusieurs commandes dans un projet

```bash
aml make:command ImportUsers
aml run list
aml run import-users
aml run import-users --help
```

Le générateur crée `src/Commands/ImportUsers.php` et enregistre `import-users`
dans `application.commands` de `phpaml.json`. La classe possède sa propre
méthode `public static function main(array $arguments): int`, une définition
`Input` et le traitement de `--help`. Le squelette affiche un message et ne
réalise aucune opération métier avant votre personnalisation.

Les noms `ImportUsers` et `import-users` produisent la classe `ImportUsers` et
la commande `import-users`. `Admin/HTTPImport` produit
`App\Commands\Admin\HTTPImport`, accessible par `aml run admin:http-import`.
Un nom de commande ou de classe déjà enregistré, un chemin existant ou un nom
PHP réservé est refusé. Le générateur conserve les fichiers de votre programme.
Les noms de fichiers réservés sous Windows (`Con`, `Nul`, `Com1` à `Com9`,
`Lpt1` à `Lpt9`, `Prn`, `Aux`) sont aussi refusés, même sur macOS/Linux, dans
chaque segment du nom. Cela évite de créer une commande impossible à déplacer
sur Windows. Les séparateurs `/` et `\` sont acceptés pour les commandes imbriquées.

Dans la classe `ImportUsers`, vous pouvez remplacer le corps de `main()` par :

```php
$input = \AML\Console\Input::define('Préparer un import.', usage: 'aml run import-users')
    ->argument('file', 'Fichier à importer', required: true)
    ->option('limit', 'Nombre maximal de lignes', type: 'int', default: 100, min: 1)
    ->parse($arguments);

if ($input->helpRequested()) {
    \AML\Console\Console::line($input->help());
    return 0;
}

\AML\Console\Console::line('Fichier : ' . $input->argument('file'));
\AML\Console\Console::line('Limite : ' . $input->option('limit'));
return 0;
```

Vous obtenez alors une commande avec ses propres arguments et sa validation :

```bash
aml run import-users clients.csv --limit=100
aml run import-users --help
```

| Appel | Comportement |
| --- | --- |
| `aml run` | Appelle `Program::main([])` |
| `aml run import-users clients.csv` | Appelle `ImportUsers::main(['clients.csv'])` |
| `aml run list` | Affiche les commandes enregistrées sans charger leurs classes |
| `aml run --help` | Affiche le catalogue si des commandes sont enregistrées ; sinon l’aide du programme |
| `aml run -- André` | Transmet explicitement `André` à `Program::main()` |
| `aml run -- --help` | Demande l’aide de `Program::main()` |

Dès qu’une commande est enregistrée, un nom inconnu après `aml run` est une
erreur de saisie, avec le code de sortie 2. Utilisez `aml run -- ...` pour les
arguments du programme principal. Sans commande enregistrée, l’ancien appel
`aml run André` reste accepté. `list` est réservé au catalogue ;
`aml run -- list` permet de passer ce mot au programme principal.

Le registre est explicite et peut aussi référencer une classe chargée par
Composer. Chaque entrée contient `class` et une `description` facultative :

```json
{
  "application": {
    "type": "console",
    "entry": "App\\Program",
    "commands": {
      "import-users": {
        "class": "App\\Commands\\ImportUsers",
        "description": "Préparer un import d’utilisateurs"
      }
    }
  }
}
```

Une valeur texte seule, par exemple `"import-users": "App\\Commands\\ImportUsers"`,
est également acceptée. Elle permet notamment de déclarer un alias. Le catalogue
utilise les descriptions du manifeste ; l’aide détaillée utilise la définition
`Input` dans la classe. Les arguments transmis à une commande ne contiennent
pas son nom. Son code de sortie est préservé, comme celui du programme principal.

Dans un ancien projet console, `make:command` ajoute `lib/console.php` s’il
manque, sans remplacer une bibliothèque ou un bootstrap existant. `aml run` et
`aml test` chargent cette bibliothèque même avec un ancien bootstrap. Pour vos
tests PHP exécutés directement, ajoutez son chargement à votre bootstrap si
celui-ci ne le fait pas déjà. `make:command` est réservé aux projets console ;
les scripts des projets Web conservent leur fonctionnement.

## Poser des questions

Dans `Program::main()`, après le traitement de l’aide :

```php
$name = Console::ask('Votre nom', 'André');
$continue = Console::confirm('Continuer ?', default: false);
$environment = Console::select('Environnement', [
    'dev' => 'Développement',
    'prod' => 'Production',
], default: 'dev');

if ($continue) {
    Console::line("Bonjour, {$name}. Environnement : {$environment}.");
}
```

- `ask()` retourne le texte saisi, sans espaces de début ou de fin. Une réponse
  vide utilise le défaut ; sans défaut, la question est reposée.
- `confirm()` accepte `oui`, `o`, `yes`, `y`, `non`, `no` et `n`, sans distinction
  de casse. Une réponse vide utilise le défaut, qui est `false`.
- `select()` affiche une liste numérotée. L’utilisateur saisit un numéro ; la
  méthode retourne la clé originale, ici `dev` ou `prod`. Le défaut est une clé.
- À la fin de l’entrée standard, les méthodes utilisent le défaut s’il existe
  ou lèvent `InputException`, sans boucle d’attente infinie.

```php
$password = Console::secret('Mot de passe');
// Utilisez la valeur en mémoire, sans l’afficher ni la journaliser.
```

`secret()` ne montre aucun caractère pendant la frappe. Il nécessite un terminal
interactif pour l’entrée et les diagnostics. Sur macOS/Linux, il utilise `stty`
et restaure l’affichage à la fin de la saisie ou lors d’une interruption. Sur
Windows, il utilise la lecture masquée du clavier via PowerShell. Une entrée
redirigée ou un mécanisme indisponible provoque une erreur, sans saisie visible
de remplacement. `proc_open` doit être disponible.

Les invites et `Console::error()` écrivent sur `STDERR`. `Console::line()` écrit
sur `STDOUT`, ce qui permet de rediriger le résultat sans les questions.
Les séquences de contrôle contenues dans le texte sont retirées ; les couleurs
se demandent avec les méthodes ci-dessous. `echo` reste disponible pour les
sorties brutes.

## Affichage dans le terminal

Les nouveaux projets contiennent une démonstration autonome :

```bash
php examples/display.php
php examples/display.php --fail
php examples/display.php --help
```

`--fail` simule une interruption et retourne le code 2. L’exemple ne lit ni ne
modifie les fichiers nommés dans son tableau.

### Couleurs et messages

```php
Console::line('Rapport des imports', color: 'cyan');
Console::info('Trois fichiers à traiter.');
Console::success('Import terminé.');
Console::warning('Deux lignes ont été ignorées.');
Console::error('Le fichier demandé est introuvable.');
```

`line()` écrit sur `STDOUT`. Les quatre messages d’état utilisent `STDERR` et
gardent un libellé lisible sans couleur : `[INFO]`, `[OK]`, `[AVERTISSEMENT]` et
`[ERREUR]` en français. `error()` affiche un message ; il ne quitte pas le
programme et ne change pas son code de sortie.

Les couleurs proposées sont `black`, `red`, `green`, `yellow`, `blue`, `magenta`,
`cyan`, `white`, `gray`, ainsi que le style `bold`. Une couleur inconnue lève
`InvalidArgumentException` avant toute écriture.

Le rendu est déterminé séparément pour `STDOUT` et `STDERR` :

- Les couleurs sont utilisées seulement sur un terminal compatible.
- Une variable `NO_COLOR` non vide les désactive, y compris `NO_COLOR=0`.
- `TERM=dumb` désactive les couleurs et l’animation de la progression.
- Une sortie redirigée reçoit du texte sans codes de contrôle. Par exemple,
  `php examples/display.php > rapport.txt` garde les messages sur le terminal
  et enregistre uniquement le titre et le tableau dans le fichier.

### Tableaux

```php
Console::table(
    ['Nom', 'Lignes', 'État'],
    [
        ['clients.csv', 120, 'Terminé'],
        ['commandes.csv', 48, 'Terminé'],
    ],
    maxColumnWidth: 32,
);
```

Le tableau utilise des bordures ASCII, des en-têtes en gras sur un terminal
compatible et des colonnes ajustées au contenu. Les lignes peuvent être
fournies par un générateur. Chaque ligne doit être une liste avec autant de
cellules que d’en-têtes ; les cellules acceptent du texte, des nombres, des
booléens ou `null`.

La largeur maximale est de 40 par colonne par défaut, avec un minimum de 4.
Les cellules trop longues sont raccourcies avec `...`. Les sauts de ligne sont
affichés comme ` / ` et les tabulations comme deux espaces. Toutes les lignes
sont validées avant l’affichage afin d’éviter un tableau partiel en cas d’erreur.

L’alignement des caractères larges utilise `mbstring`, inclus dans le runtime
AML. Sans cette extension, l’affichage reste disponible avec une largeur
approximative pour ces caractères. Certains emoji peuvent occuper une largeur
différente selon le terminal. La limite porte sur chaque colonne, pas sur la
largeur totale du tableau.

### Progression

```php
$progress = Console::progress(count($files), 'Import');
try {
    foreach ($files as $file) {
        importFile($file); // Votre traitement.
        $progress->advance();
    }
    $progress->finish();
} catch (\Throwable $error) {
    $progress->abort();
    throw $error;
}
Console::success('Tous les fichiers ont été traités.');
```

La progression écrit sur `STDERR`. Dans un terminal compatible, elle met à jour
la même ligne, avec une fréquence limitée. `COLUMNS` permet d’indiquer la largeur
du terminal ; la valeur par défaut est 80. Lorsque les diagnostics sont redirigés,
ou avec `TERM=dumb`, seuls le début et le résultat final sont écrits, sans animation.
`NO_COLOR` retire les couleurs tout en conservant l’animation sur un terminal compatible.

`advance()` avance d’un élément ; `advance(5)` de cinq. `set(20)` fixe un compteur
absolu sans revenir en arrière ni dépasser le total. Un total de zéro est accepté.
`finish()` marque explicitement le travail comme terminé et ferme la ligne.
`abort()` conserve le compteur réel avec la mention « Interrompu ». Un objet
abandonné sans `finish()` est également marqué comme interrompu lors de sa
destruction normale ; cela ne garantit pas un message après un arrêt brutal du
processus. Une seconde fermeture ne produit pas de nouveau message.

Utilisez une barre à la fois et terminez-la avant d’afficher d’autres messages
ou de poser une question. Les tableaux, couleurs et messages sont disponibles
dans les commandes comme dans `Program::main()`.

## Erreurs, langue et chargement

Une entrée utilisateur invalide lève `AML\Console\InputException`. Avec
`aml run`, elle produit un message sur `STDERR`, sans trace interne, et le code
de sortie **2**. Les autres exceptions produisent le code **1**. Le succès de
l’exemple retourne **0**. Vous pouvez intercepter `InputException` dans
`Program::main()` pour personnaliser le comportement.

La langue AML choisie est transmise au programme. `AML_LANG=fr` ou `AML_LANG=en`
choisit la langue des messages intégrés et des libellés de l’aide ; les
descriptions et questions écrites dans votre application ne sont pas traduites.
Une exécution PHP indépendante utilise l’anglais si `AML_LANG` n’est pas défini.

`bootstrap.php` charge Composer lorsqu’il est installé, puis la bibliothèque et
les classes `App\`. La bibliothèque est également déclarée dans `autoload.files`
de `composer.json`. Les tests générés peuvent donc s’exécuter directement avec
`php tests/run.php`, sans AML installé. Dans un script PHP indépendant, chargez
`bootstrap.php` et gérez vous-même le code de sortie des exceptions.

Les projets existants restent exécutables et ne sont pas réécrits par `aml run`.
Pour y utiliser ces nouvelles API, copiez `lib/console.php` depuis un projet
nouvellement généré et ajoutez `require_once __DIR__ . '/lib/console.php';` à votre
`bootstrap.php`. Si votre projet n’a pas de bootstrap, reprenez aussi celui du
projet généré en conservant vos éventuels chargements personnalisés.

## Vérifier la console avant distribution

Depuis le dépôt de la CLI, avec PHP et `mbstring` disponibles :

```bash
php tests/console-input.php
php tests/console-display.php
php tests/console-smoke.php
php tests/console-commands.php
php tests/console-import.php
```

Le workflow indépendant `Test AML Console` prévoit ces tests sur Linux, macOS
et Windows, avec PHP 8.2 et 8.4. Il couvre notamment les chemins avec espaces et
accents, les arguments littéraux, les entrées CRLF et le déplacement d’un projet.
Sur macOS/Linux, les tests Python `console-secret-tty.py` et
`console-display-tty.py` vérifient aussi le terminal interactif. Les tests Windows
automatiques couvrent les flux redirigés ; la saisie masquée et l’affichage dans
un vrai terminal Windows restent à vérifier sur cette plateforme.

Pour contrôler la console d’un paquet extrait ou installé sur son système cible,
depuis le dépôt correspondant exactement à la version du paquet :

```bash
php tests/console-package.php /chemin/vers/le/paquet
```

Ce test utilise le vrai lanceur `bin/aml` (ou `bin/aml.exe`) pour la création,
les commandes et les tests du projet. Les scripts autonomes utilisent le PHP
embarqué. Il vérifie aussi les arguments littéraux, les codes de sortie,
l’exemple d’affichage et l’import CSV. Il crée uniquement un projet temporaire,
sans installer ni modifier le paquet. Un lanceur absent ou un générateur,
une bibliothèque ou un exemple d’import différents des sources font échouer le test.

Un second chemin facultatif permet de vérifier le lien public de l’installation :

```bash
# Après installation dans une machine de test Linux :
/opt/phpaml/runtime/php/bin/php tests/console-package.php /opt/phpaml /usr/local/bin/aml

# Après installation dans une machine de test macOS :
/usr/local/lib/aml/runtime/php/bin/php tests/console-package.php /usr/local/lib/aml /usr/local/bin/aml
```

Le lanceur choisi doit appartenir au paquet contrôlé. Les scripts de construction
exécutent le contrôle avant de créer les archives. Les workflows de construction
le prévoient aussi après installation : Debian, paquet macOS et installateur
Windows. Le test Windows utilise un dossier temporaire puis désinstalle le paquet.
Ces étapes nécessitent une machine cible ; leur présence dans les workflows ne
prouve pas qu’elles ont été exécutées.

### Vérification manuelle dans Windows Terminal

Dans une machine de test Windows avec le paquet installé, ouvrez PowerShell
dans le dépôt de la CLI correspondant à ce paquet :

```powershell
$amlCommand = (Get-Command aml.exe -ErrorAction Stop).Source
$amlRoot = Split-Path (Split-Path $amlCommand -Parent) -Parent
$amlPhp = Join-Path $amlRoot 'runtime/php/php.exe'
& $amlPhp tests/console-package.php $amlRoot $amlCommand
& $amlPhp tests/console-terminal.php display $amlRoot
& $amlPhp tests/console-terminal.php secret $amlRoot
& $amlPhp tests/console-terminal.php interrupt $amlRoot
```

Exécutez les trois diagnostics séparément, sans redirection. `display` permet
de vérifier la couleur bleue lorsque les couleurs sont activées, l’alignement
du tableau et la progression sur une seule ligne. Avec `NO_COLOR`, les couleurs
doivent disparaître. Le code 0 signifie seulement que l’affichage s’est exécuté :
l’observation visuelle reste nécessaire.

Avec `secret`, saisissez uniquement la valeur fictive `console-test`, puis Entrée.
Aucun caractère frappé ne doit apparaître ; la valeur reçue est vérifiée sans
être réaffichée. Avec `interrupt`, appuyez sur `Ctrl+C` à l’invite, vérifiez
l’interruption, puis tapez une commande ordinaire pour vérifier que la frappe
est de nouveau visible. Ce diagnostic ne doit pas être marqué réussi simplement
parce qu’il a été lancé. Notez le système, la version de PHP, le terminal et le
résultat de chaque contrôle. Les mêmes diagnostics fonctionnent sur macOS/Linux.
