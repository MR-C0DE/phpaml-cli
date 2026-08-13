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
| `aml create .` | crée le projet dans le dossier courant |
| `aml create nom` | crée le projet dans `nom/` |
| `aml install` | installe moteur et dépendances |
| `aml serve` | démarre sur `localhost:8000` |
| `aml serve hôte:port` | démarre sur une adresse choisie |
| `aml routes` | affiche les routes enregistrées |
| `aml test` | exécute `tests/run.php` |
| `aml build` | produit l’archive de déploiement dans `output/` |

Options de `create` :

```bash
aml create projet --version 0.1.0
aml create projet --refresh
aml create projet --offline
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

Le build vérifie les règles d’URL propres, exécute les tests, exclut `.env`,
les journaux, bases SQLite, tests, fichiers temporaires, `output/` et
`deliverables/`, puis produit
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
```

Stratégies disponibles :

```bash
--strategy releases
--strategy public-html --public-path /home/user/domains/site/public_html
--strategy sftp-only --public-path /public_html
```

Les profils sont privés dans `~/.phpaml/deploy.json` et ne contiennent aucun
mot de passe. `aml deploy` construit l’application, transfère l’archive dans
`releases/`, puis active atomiquement le lien `current`. Configurez la racine
du domaine sur `<chemin>/current/public`.

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
est conservée dans `.phpaml-backups/`.

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
