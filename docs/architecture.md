# Architecture

PHPAML reprend des idées familières de Java EE et d’ASP.NET sans chercher à
reproduire toute leur taille.

| PHPAML | Java EE | ASP.NET Core |
|---|---|---|
| `app/Controllers` ou `src/controllers` | ressources/contrôleurs | controllers |
| `app/Models` ou `src/models` | entités/services métier | models |
| `phpaml.json` et `.env` | configuration de l’application | `Program.cs` / configuration |
| `MiddlewareInterface` | filtres/intercepteurs | middleware |
| `Container` | injection de dépendances | service container |
| `DbContext` / `Connection` | couche de persistance | `DbContext` |
| `app/views` | templates | Razor views |

Cette comparaison décrit l’organisation, pas une compatibilité avec Java EE ou
ASP.NET.

## Arborescence d’un projet

```text
mon-projet/
├── app/
│   ├── Controllers/
│   ├── Models/
│   └── views/
│       ├── partials/header.php
│       ├── partials/footer.php
│       └── home.php
├── public/
│   ├── index.php
│   ├── css/index.css
│   ├── js/main.js
│   └── img/favicon.svg
├── routes/WebApp.php
├── runtime/database/migrations/
├── tests/
├── runtime/
├── .env
├── composer.json
└── phpaml.json
```

Le développeur travaille principalement dans `app`, `routes`, `public` et
`tests`. `runtime` contient le moteur, Composer, l’autoloading, la configuration générée, la base locale, le
cache et les données d’exécution ; il ne doit normalement pas être modifié à la
main. `phpaml.json` est le manifeste du projet : il décrit notamment le nom, la
version, l’application, l’API et Data. Les secrets et les valeurs propres à une
machine restent dans `.env`. PHPAML génère automatiquement
`runtime/config/app.php`; ce fichier ne doit jamais être modifié à la main.

Les projets créés avant cette convention peuvent être inspectés puis convertis
avec `aml migrate:structure` et `aml migrate:structure --apply --yes`. Une
sauvegarde est créée dans `runtime/storage/migrations/structure-<date>/` avant
toute modification.

## Variante AML View

Une application créée avec `aml create-view-app` remplace `app/` par `src/`.
Elle conserve le modèle MVC obligatoire tout en regroupant la présentation dans
`src/views` :

```text
src/
├── controllers/
├── models/
├── middleware/
└── views/
    ├── pages/
    ├── components/
    ├── layouts/
    ├── states/
    ├── stylesheets/
    └── assets/
```

Consultez [AML View](aml-view.md) pour les pages déclaratives, l’état réactif,
les effets et la navigation sans rechargement complet.

## Cycle d’une requête

1. `public/index.php` charge `phpaml.json` et `.env` avec `ApplicationConfig`.
2. `WebApplication` prépare le conteneur, la session, les vues et la connexion.
3. Les middlewares globaux reçoivent la requête.
4. Le routeur découvre `routes/` et `src/routes/`, puis extrait les paramètres.
5. Le conteneur construit le contrôleur et injecte ses dépendances.
6. L’action retourne obligatoirement une `Response`.
7. La réponse traverse les middlewares puis est envoyée au navigateur.

## Injection de dépendances

Les classes concrètes typées dans un constructeur sont résolues
automatiquement :

```php
use PHPAML\Data\Connection;

final class UserRepository
{
    public function __construct(private Connection $connection)
    {
    }
}
```

Le conteneur détecte les dépendances circulaires. Un paramètre scalaire doit
avoir une valeur par défaut ou être fourni par une configuration personnalisée.
