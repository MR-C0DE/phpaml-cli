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

`aml env:init` copie `.env.example` vers `.env`. Utilisez `--force` seulement
pour remplacer une configuration existante. `env:list` masque les mots de
passe, secrets, clés et jetons.

La configuration SQLite par défaut crée `aml_env/storage/database.sqlite` et
enregistre `root`/`root` dans `.env` par convention. SQLite n'utilise toutefois
pas ces identifiants. MySQL est aussi configurable :

```bash
aml db:configure mysql --host 127.0.0.1 --port 3306 \
  --database phpaml --user root --password root
```

`aml run <nom>` exécute une entrée de `scripts` déclarée dans `info.json`.

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
aml update --check
aml update
aml update --version 1.3.0
aml update --force
```

`--force` permet de réinstaller la même version. Il peut aussi autoriser une
version explicitement demandée plus ancienne : utilisez-le avec prudence.

## Aide

```bash
aml help
aml --help
aml --version
```
