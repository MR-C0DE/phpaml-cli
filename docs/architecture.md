# Architecture

PHPAML reprend des idées familières de Java EE et d’ASP.NET sans chercher à
reproduire toute leur taille.

| PHPAML | Java EE | ASP.NET Core |
|---|---|---|
| `Controllers` | ressources/contrôleurs | controllers |
| `Models` | entités/services métier | models |
| `configs/app.php` | configuration de l’application | `Program.cs` / configuration |
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
│   ├── public/
│   │   ├── css/index.css
│   │   ├── js/main.js
│   │   └── img/favicon.svg
│   └── views/
│       ├── partials/header.php
│       ├── partials/footer.php
│       └── home.php
├── configs/app.php
├── database/migrations/
├── tests/
├── aml_env/
├── .env
├── composer.json
├── index.php
└── info.json
```

Le développeur travaille principalement dans `app`, `configs`, `database` et
`tests`. `aml_env` contient le moteur, Composer, l’autoloading, le cache et les
données d’exécution ; il ne doit normalement pas être modifié à la main.

## Cycle d’une requête

1. `index.php` charge l’environnement et `configs/app.php`.
2. `WebApplication` prépare le conteneur, la session, les vues et la connexion.
3. Les middlewares globaux reçoivent la requête.
4. Le routeur trouve la route et extrait ses paramètres.
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
