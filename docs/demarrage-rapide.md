# Démarrage rapide

## Créer une application

Dans un nouveau sous-dossier :

```bash
aml create mon-projet
cd mon-projet
```

Dans le dossier courant :

```bash
aml create .
```

Le générateur refuse d’écraser un fichier existant. Il télécharge le modèle
officiel, vérifie son SHA-256, initialise `.env`, installe le moteur et les
dépendances, puis conserve les téléchargements dans le cache AML. Utilisez
`--no-install` uniquement pour différer volontairement l’installation.

Contrôlez ensuite le projet :

```bash
aml doctor
```

Pour recréer manuellement `.env` lorsqu’il a été supprimé :

```bash
aml env:init
```

## Lancer le serveur

```bash
aml serve
```

Ouvrez <http://127.0.0.1:8910>. Si ce port est occupé, PHPAML utilise automatiquement 8911, puis 8912, etc. Pour choisir l’adresse :

```bash
aml serve 127.0.0.1:8080
```

Le navigateur s’actualise après une modification PHP, CSS, JavaScript, HTML,
JSON ou SVG. Arrêtez le serveur avec `Ctrl+C`.

## Modifier la page d’accueil

- contrôleur : `app/Controllers/HomeController.php` ;
- modèle : `app/Models/HomeModel.php` ;
- vue : `app/views/home.php` ;
- header : `app/views/partials/header.php` ;
- footer : `app/views/partials/footer.php` ;
- point d’entrée public : `public/index.php` ;
- CSS : `public/css/index.css` ;
- JavaScript : `public/js/main.js` ;
- favicon : `public/img/favicon.svg`.

## Vérifier le projet

```bash
aml routes
aml test
aml doctor
```
