# Référence de la commande AML

## Langue du CLI

Au premier lancement interactif, AML demande de choisir `English` ou
`Français`. Le choix est enregistré pour toutes les commandes, y compris les
messages d'erreur et de diagnostic.

```bash
aml language
aml language en
aml language fr
```

La variable temporaire `AML_LANG=en` ou `AML_LANG=fr` permet de remplacer la
langue pour une seule exécution. L'installateur Windows présente également le
choix de langue et le transmet au CLI.

## Projet et serveur

| Commande | Description |
|---|---|
| `aml create .` | crée et installe le projet dans le dossier courant |
| `aml create nom` | crée et installe le projet dans `nom/` |
| `aml install` | réinstalle le moteur et les dépendances d’un projet existant |
| `aml serve` | démarre sur `127.0.0.1:8910` et choisit automatiquement le port suivant s’il est occupé |
| `aml serve hôte:port` | démarre sur une adresse choisie |

Avant de démarrer le serveur, AML lit les prérequis `ext-*` de
`composer.json` et `composer.lock`. Le runtime privé reste prioritaire lorsqu’il
est compatible. Si une extension du projet n’y est pas disponible, AML choisit
automatiquement un autre exécutable PHP compatible présent dans `PATH`. La
variable `AML_PHP_BINARY` permet de définir explicitement le PHP à essayer en
premier.
| `aml routes` | affiche les routes enregistrées |
| `aml test` | exécute `tests/run.php`, ou la suite déclarative `tests/aml-view.php` dans une application AML View |
| `aml build` | produit l’archive de déploiement dans `output/` |

Options de `create` :

```bash
aml create projet --version 0.1.0
aml create projet --refresh
aml create projet --offline
aml create projet --no-install
```

Options de `install` :

```bash
aml install --version 0.1.0
aml install --refresh
aml install --offline
aml install --production
```

## Build de production

```bash
aml build
aml build --skip-tests
```

Le build vérifie les règles d’URL propres, exécute les tests et exclut `.env`,
les journaux, bases SQLite, tests, fichiers temporaires, `output/` et
`deliverables/`. Il bloque aussi les variantes `.env.*`, clés privées,
certificats et signatures de secrets connues, même lorsqu’ils se trouvent dans
un sous-dossier. Il produit ensuite
`output/phpaml-build.zip`, son checksum SHA-256 et `output/manifest.json`.
Le serveur de production doit utiliser `public/` comme racine du domaine. Les
routes publiques sont ainsi `/`, `/about` et `/contact`, jamais `/index.php`.

## SSH, SFTP et déploiement

```bash
aml deploy:configure production --host example.com --user deploy \
  --path /home/deploy/site --port 22 --key ~/.ssh/id_ed25519
aml deploy:check production
aml ssh production
aml sftp production
aml deploy production
aml deploy:rollback production
aml deploy:prune production --keep 5
```

Stratégies disponibles :

```bash
--strategy releases
--strategy public-html --public-path /home/user/domains/site/public_html
--strategy sftp-only --public-path /public_html
```

Les profils sont privés dans `~/.phpaml/deploy.json` et ne contiennent aucun
mot de passe. Au premier lancement, `aml deploy` transfère tous les fichiers du
build. Ensuite, il compare les empreintes du manifeste distant et n'envoie que
les fichiers ajoutés ou modifiés ; il retire aussi les fichiers supprimés du
projet. Avec `releases`, AML reconstruit la nouvelle release sur le serveur,
vérifie ses empreintes, puis active atomiquement le lien `current`. Configurez
la racine du domaine sur `<chemin>/current/public`.

Pour `public-html` et `sftp-only`, `--public-path` peut être extérieur à
`--path`, notamment pour un sous-domaine Hostinger. Lors d’une première
activation `releases`, un dossier `current` préexistant est sauvegardé sous
`current.pre-aml-<release>` au lieu d’être écrasé.

Si GitHub retourne une erreur temporaire (connexion, HTTP 408, 429 ou 5xx comme
503), AML retente le téléchargement jusqu’à trois fois. Si le disque est plein
ou si `output/` n’est pas accessible, `aml build` échoue et supprime les fichiers
incomplets au lieu d’annoncer un faux succès.

## Génération de code

```bash
aml make:controller User
aml make:model User
aml make:middleware Auth
aml make:migration create_users_table
```

AML refuse de remplacer une classe existante.

## API JSON

Créez une API sans vues ni dossier `configs/` :

```bash
aml create-api movies-api
cd movies-api
aml serve
```

Une ressource simple produit `src/controllers/MovieController.php` et
`src/routes/MovieRoute.php` :

```bash
aml make:api Movie
```

Pour une ressource persistée avec PHPAML Data :

```bash
aml install data --driver sqlite
aml make:api Movie --fields "title:string,year:integer,genre:string?" --migration
aml data:migrate
aml serve
```

Les routes REST sont découvertes automatiquement :

```text
GET     /api/v1/movies
POST    /api/v1/movies
GET     /api/v1/movies/{id}
PUT     /api/v1/movies/{id}
PATCH   /api/v1/movies/{id}
DELETE  /api/v1/movies/{id}
```

La configuration modifiable se trouve dans `phpaml.json` et `.env`.
`runtime/config/app.php` est généré automatiquement et ne doit pas être édité.
Les API pures n’exigent pas de jeton CSRF de formulaire; les routes privées
utilisent un token Bearer et, au besoin, des capacités.

## Données

SQLite est le pilote par défaut :

```bash
aml install data
```

Le moteur peut être choisi explicitement sans allonger les commandes suivantes :

```bash
aml install data --driver mysql
aml install data --driver mariadb
aml install data --driver pgsql
aml install data --driver mongodb
```

AML installe `phpaml/data` pour les moteurs SQL et `phpaml/data-mongodb` pour
MongoDB, puis crée `src/models/`, `runtime/database/migrations/` et
`runtime/database/seeders/`. La configuration et le pilote sont enregistrés dans
la section `data` de `phpaml.json`; `.env` peut les surcharger.

```bash
aml make:model User
aml make:migration create_users_table
aml make:seeder UserSeeder
aml data:migrate
aml data:rollback --steps 1
aml data:seed
aml data:status
aml data:doctor
```

Les commandes acceptent `--connection <nom>` lorsque l’application configure
plusieurs connexions.

## AML View

Créez directement une application avec la bibliothèque d’interfaces web
déclaratives et son intégration sécurisée :

```bash
aml create-view-app .
aml create-view-app mon-interface
```

La commande crée le projet, installe son moteur et `phpaml/view` depuis
Packagist, génère `AML_VIEW_SECRET`, enregistre le module dans `phpaml.json`
et prépare une organisation fondée sur `src/` :

```text
src/
├── views/
│   ├── pages/
│   │   ├── home/page.php
│   │   ├── about/page.php
│   │   └── users/[id]/page.php
│   ├── components/Navigation.php
│   ├── layouts/AppLayout.php
│   ├── states/
│   │   ├── Loading.php
│   │   ├── Error.php
│   │   └── NotFound.php
│   ├── stylesheets/
│       ├── base.css
│       ├── pages/home.css
│       ├── components/navigation.css
│       ├── layouts/app.css
│       └── states/route-states.css
│   └── themes/
│       ├── light/tokens.css
│       └── dark/tokens.css
├── controllers/
├── models/
├── middleware/ (optionnel)
└── services/ (optionnel)
public/
├── index.php
├── favicon.svg
├── phpaml-logo-violet-lime.png
└── assets/
```

Les dossiers placés sous `src/views/pages/` deviennent automatiquement les URL
du site. Les layouts et états sont associés depuis leurs dossiers dédiés.
Aucun registre manuel n’est nécessaire.

Les styles suivent la même organisation que les vues. Le moteur regroupe
automatiquement tous les fichiers de `src/views/stylesheets/` et les expose à
`/_aml/styles.css`. `public/index.php` charge uniquement cette feuille générée :
aucun CSS applicatif n’est dispersé dans `public/`.
Le dossier `public/assets/` reçoit les images et ressources ajoutées par
l’application. Les documents qui doivent conserver une URL directe restent à
la racine de `public/` : logo, favicon, `robots.txt`, `sitemap.xml`, etc.

Les classes s’appliquent directement depuis les éléments déclaratifs :

```php
Section(...)->class('home-hero', 'featured');
```

Les règles globales restent dans `stylesheets/base.css`. Les feuilles des
pages, composants, layouts et états utilisent des sélecteurs par classe.

Les applications générées incluent `ThemeProvider()` dans le layout et
`ThemeSwitcher()` dans la navigation. Les modes clair, sombre et système sont
gérés automatiquement, avec persistance du choix dans le navigateur.

Chaque page peut également redéfinir `metadata()` avec `PageMetadata` pour
déclarer son titre, sa description, son URL canonique, l’indexation et ses
aperçus Open Graph/Twitter. `public/index.php` injecte automatiquement ces
métadonnées dans le document; aucune balise SEO n’est écrite dans la page.

Les nouvelles applications chargent également `phpaml/engine`. Les actions
locales comme `ClientAction::increment('count')` s’exécutent entièrement dans
le navigateur. Le serveur fournit le premier HTML et l’état initial, mais un
clic frontend ordinaire ne contacte pas `/_aml/view`.

Le générateur ne crée plus cet endpoint et ne charge plus `BrowserRuntime`.
Après le rendu HTML initial, les interactions ordinaires appartiennent
exclusivement à PHPAML Engine. Toute communication avec PHP doit être déclarée
par une action `Api::*()`.

Les liens internes sont pris en charge par le routeur de PHPAML Engine : la
racine AML est actualisée sans recharger le document, l’URL et les boutons
précédent/suivant restent synchronisés, et le lien actif reçoit
`aria-current="page"`.

Le backend n’est contacté que par une action explicite comme
`Api::get('/api/health')` ou `Api::post('/api/profile', $data)`. Le résultat,
le chargement et l’erreur peuvent être liés à des propriétés `#[State]` avec
`storeIn()`, `loadingIn()` et `errorIn()`.

Les contrôles liés avec `bindClient()` acceptent les règles frontend
`required()`, `email()` et `minLength()`. PHPAML Engine affiche les erreurs de
manière accessible et bloque la soumission invalide sans contacter le serveur.
Le backend doit toujours valider à nouveau les données reçues par une API.
Une vérification distante peut être déclarée avec
`validateWith(Api::get(...), debounce: 300)`. L’API répond avec `valid` et un
éventuel `message`; le moteur annule automatiquement les requêtes devenues
obsolètes et refait la vérification avant la soumission.

Le moteur gère également le cycle de vie `mount`, `update` et `unmount`. Les
frontières nommées avec `->component('nom')` reçoivent ces événements. Lors du
démontage, les écouteurs enregistrés sont nettoyés et les requêtes API encore
actives sont annulées automatiquement.

`Actions::sequence()` enchaîne plusieurs instructions locales ou API dans leur
ordre de déclaration. `Actions::when()` choisit une branche selon une valeur de
l’état frontend, sans exécuter de closure PHP dans le navigateur.

Les modificateurs `showWhen()`, `classWhen()` et `disabledWhen()` contrôlent la
présentation depuis l’état client. Leur valeur initiale est rendue par PHP, puis
PHPAML Engine les actualise localement.

`Each()` affiche une collection réactive (`foreach` étant réservé par PHP).
Les actions `append`, `prepend`, `removeAt` et `clear` modifient la collection
dans le navigateur. Le premier rendu de la liste reste produit par PHP.

Les routes dynamiques utilisent les crochets :
`src/views/pages/users/[id]/page.php` répond à `/users/42`. Les contrôleurs,
modèles, middlewares et services restent directement dans `src/`. Les vues PHP classiques ne
sont pas ajoutées aux applications AML View.

`src/views`, `src/models` et `src/controllers` constituent la structure
obligatoire d’une application AML View. `aml doctor` signale leur absence.
Les dossiers `src/middleware` et `src/services` sont optionnels.

La page de départ est accessible sur `/`. Les commandes de génération sont :

```bash
aml make:view-page Home
aml make:view-component Navigation
aml make:view-layout Dashboard
aml make:view-loading dashboard
aml make:view-error dashboard
aml make:view-not-found dashboard
```

Elles créent respectivement `src/views/pages/home/page.php`,
`src/views/components/Navigation.php` et `src/views/layouts/DashboardLayout.php`.

Une version précise d’AML View peut être demandée avec
`aml create-view-app mon-interface --view-version 0.1.0-beta.3`.

`aml create` reste réservé aux projets PHPAML classiques et n’ajoute pas AML
View.

## Internationalisation

Installez le module JSON indépendant dans une application PHPAML classique ou
AML View :

```bash
aml install i18n
```

La commande installe `phpaml/i18n`, configure `APP_LOCALE` et
`APP_FALLBACK_LOCALE`, puis crée :

```text
src/locales/
├── en/common.json
└── fr/common.json
```

L’organisation sous chaque langue reste libre. Le chemin produit la clé :
`src/locales/fr/pages/home.json` et la valeur `title` donnent
`pages.home.title`.

```php
use function AML\I18n\t;

Heading(t('pages.home.title'));
Text(t('common.welcome', ['name' => $user->name]));
```

Commandes disponibles :

```bash
aml i18n:add es
aml i18n:list
aml i18n:check
aml i18n:missing fr
aml i18n:set-default en
```

## Base, cache et scripts

```bash
aml env:init
aml env:list
aml env:get APP_DEBUG
aml env:set APP_DEBUG false
aml db:configure sqlite
aml db:show
aml migrate
aml migrate:rollback --steps 1
aml cache:clear
aml run start
```

### Migrer un ancien projet vers la structure actuelle

```bash
aml migrate:structure
aml migrate:structure --apply --yes
```

La première commande affiche uniquement les opérations prévues. La seconde
renomme `aml_env/` en `runtime/`, remplace `info.json` par `phpaml.json`, adapte
le manifeste et les références textuelles connues. AML refuse d’agir si les
anciens et nouveaux chemins coexistent. Une sauvegarde des fichiers modifiés
est conservée dans `runtime/storage/migrations/structure-<date>/`.

`aml env:init` copie `.env.example` vers `.env`. Utilisez `--force` seulement
pour remplacer une configuration existante. `env:list` masque les mots de
passe, secrets, clés et jetons.

La configuration SQLite par défaut crée `runtime/storage/database.sqlite` et
enregistre `root`/`root` dans `.env` par convention. SQLite n'utilise toutefois
pas ces identifiants. MySQL est aussi configurable :

```bash
aml db:configure mysql --host 127.0.0.1 --port 3306 \
  --database phpaml --user root --password root
```

`aml run <nom>` exécute une entrée de `scripts` déclarée dans `phpaml.json`.

## SEO

```bash
aml seo:init
aml seo:set title "My website"
aml seo:disallow /admin
aml seo:allow /public
aml seo:remove disallow /admin
aml seo:show --json
aml seo:generate
aml seo:audit https://example.com
```

Consultez le [guide SEO](seo.md) pour la configuration des métadonnées,
Open Graph, Schema.org, `robots.txt`, le sitemap et l’audit automatisé.

## Diagnostic

```bash
aml doctor
aml doctor --port 8080
aml doctor --offline
aml doctor --json
```

Une erreur obligatoire produit un code de sortie différent de zéro. Les
avertissements, comme un port occupé ou un `.env` absent, sont affichés sans
faire échouer un environnement autrement sain.

## Version et mise à jour

```bash
aml version
aml --update --check
aml --update
aml --update --version 1.3.0
aml --update --force
```

La forme historique `aml update` reste disponible comme alias.

`--force` permet de réinstaller la même version. Il peut aussi autoriser une
version explicitement demandée plus ancienne : utilisez-le avec prudence.

## Aide

```bash
aml help
aml --help
aml --version
```
