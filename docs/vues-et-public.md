# Vues, partials et fichiers publics

## Rendre une vue

Dans un contrôleur :

```php
return $this->view('home.php', [
    'title' => 'Accueil',
    'message' => 'Bienvenue',
]);
```

Dans `app/views/home.php` :

```php
<h1><?= $this->escape($title) ?></h1>
<p><?= $this->escape($message) ?></p>
```

Utilisez `$this->escape()` pour toute valeur non fiable. Le moteur bloque les
chemins qui tentent de sortir de `app/views`.

## Header et footer

Les composants partagés se trouvent dans `app/views/partials` :

```php
<?php $this->partial('header.php', ['title' => $title]) ?>

<main>
    <!-- contenu -->
</main>

<?php $this->partial('footer.php') ?>
```

Le modèle officiel fournit déjà :

```text
app/views/partials/header.php
app/views/partials/footer.php
```

## CSS, JavaScript et favicon

Les fichiers publics restent dans :

```text
public/css/index.css
public/js/main.js
public/img/favicon.svg
```

Chargez-les avec leurs chemins depuis la racine HTTP :

```html
<link rel="icon" href="/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/css/index.css">
<script src="/js/main.js"></script>
```

`index.php` laisse le serveur PHP servir directement les fichiers existants.
Si le CSS ne s’affiche pas, vérifiez :

1. que le chemin commence par `/css/`, `/js/` ou `/img/` ;
2. que le nom et les majuscules correspondent au fichier ;
3. que `aml serve` est lancé depuis la racine du projet ;
4. que la réponse CSS n’est pas une page HTML d’erreur.

## Actualisation automatique

Avec `aml serve`, le modèle surveille les extensions `php`, `css`, `js`,
`html`, `json` et `svg`. Les fichiers de `.git` et `aml_env` sont ignorés.
Cette fonction est réservée au serveur de développement.
