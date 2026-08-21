# Routage et contrôleurs

## Déclarer une route

Une application classique place ses routes dans `routes/WebApp.php`. Une API
ou le backend d’une application AML View utilise une classe par ressource dans
`src/routes`, par exemple `MovieRoute.php` :

```php
namespace App\Routes;

use App\Controllers\MovieController;
use PHPAML\Routing\Route;

final class MovieRoute extends Route
{
    protected string $prefix = '/api/v1';

    protected function routes(): void
    {
        $this->apiResource('/movies', MovieController::class);
    }
}
```

Les méthodes `get`, `post`, `put`, `patch`, `delete` et `options` sont aussi
disponibles. Une URL inconnue
retourne `404`; une méthode incorrecte sur un chemin connu retourne `405` avec
l’en-tête `Allow`.

## Lire un paramètre dynamique

```php
use PHPAML\Http\Request;
use PHPAML\Http\Response;
use PHPAML\Mvc\Controller;

final class UserController extends Controller
{
    public function show(Request $request): Response
    {
        $id = $request->attribute('id');

        return $this->json(['id' => $id]);
    }
}
```

Chaque action doit retourner une instance de `PHPAML\Http\Response`.

## Lire la requête

```php
$request->method();
$request->path();
$request->query('page', 1);
$request->input('email');
$request->cookie('session');
$request->header('Accept');
$request->server('REMOTE_ADDR');
$request->attribute('id');
```

`input()` fusionne les paramètres de requête et le corps. Les corps JSON avec
`Content-Type: application/json` sont décodés automatiquement.

## Produire une réponse

```php
use PHPAML\Http\Response;

return Response::html('<h1>Bonjour</h1>');
return Response::json(['ok' => true], 201);
return Response::redirect('/connexion');
```

Depuis un contrôleur :

```php
return $this->view('users/show.php', ['user' => $user]);
return $this->json(['user' => $user]);
```

Un en-tête peut être ajouté sans modifier la réponse originale :

```php
return $response->withHeader('Cache-Control', 'no-store');
```

## Middleware propre à une route

```php
'POST /users' => [
    'handler' => [UserController::class, 'store'],
    'middleware' => [AuthMiddleware::class],
    'name' => 'users.store',
],
```

Affichez le tableau final avec `aml routes`.
