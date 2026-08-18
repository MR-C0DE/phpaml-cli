# Tests, production et dépannage

## Tests

Le modèle contient `tests/run.php` :

```bash
aml test
```

La commande utilise le PHP privé d’AML. Ajoutez vos tests dans `tests/` et
faites-les appeler par `tests/run.php`.

Avant chaque livraison :

```bash
aml install --production
aml doctor --json
aml routes
aml test
```

## Préparer la production

```bash
aml install --production
```

Cette option exclut les dépendances de développement et optimise l’autoloader.
Configurez au minimum :

```dotenv
APP_DEBUG=false
DATABASE_DSN=...
DATABASE_USER=...
DATABASE_PASSWORD=...
```

Le serveur lancé par `aml serve` est un serveur de développement. En
production, utilisez un serveur HTTP compatible PHP avec `index.php` comme
front controller, HTTPS et des permissions minimales.

PHPAML reste expérimental. Avant une application sensible, réalisez une revue
de sécurité, ajoutez l’authentification adaptée, des journaux, des sauvegardes,
des limites de requêtes et des tests métier.

## Diagnostic

```bash
aml doctor
aml doctor --offline
aml doctor --port 8080
aml doctor --json
```

`--offline` ignore uniquement GitHub. `--json` convient aux pipelines CI.

## Problèmes fréquents

### `aml` est introuvable

Ouvrez un nouveau terminal après l’installation. Vérifiez ensuite que le
dossier suivant est dans le `PATH` :

- Windows : `%LOCALAPPDATA%\Programs\PHPAML\bin` ;
- macOS/Linux : `/usr/local/bin`.

### L’environnement AML est absent

Depuis la racine du projet :

```bash
aml install
```

### Le CSS ou le JavaScript ne se charge pas

Utilisez `/css/index.css` et `/js/main.js`, puis vérifiez
que la commande `aml serve` a été lancée dans le dossier contenant `index.php`.

### Le port 8910 est occupé

```bash
aml doctor --port 8080
aml serve 127.0.0.1:8080
```

### Le diagnostic fonctionne dans un environnement isolé

`aml doctor` distingue un port réellement occupé (`Address already in use`)
d’une ouverture interdite par une sandbox (`Operation not permitted`). Une
restriction de sandbox est affichée comme un avertissement
« environnement restreint », et non comme une panne du système.

La même distinction s’applique au dossier temporaire et au cache AML. Une
interdiction explicite de l’environnement isolé produit un avertissement,
tandis qu’un dossier réellement non accessible en écriture reste une erreur.
Pour confirmer les permissions réelles de la machine, relancez le diagnostic
directement dans le Terminal de l’utilisateur.

### GitHub est inaccessible

Vérifiez la connexion et les éventuelles limites de l’API. Les commandes
`create` et `install` peuvent réutiliser un cache existant :

```bash
aml create projet --offline
aml install --offline
```

### Réinitialiser le cache applicatif

```bash
aml cache:clear
```

Cette commande vide le cache de l’application, sans supprimer le moteur ni les
dépendances.
