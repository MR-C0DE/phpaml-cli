# Référence de la commande AML

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
aml migrate
aml cache:clear
aml run start
```

`aml run <nom>` exécute une entrée de `scripts` déclarée dans `info.json`.

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
aml update --version 1.2.0
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
