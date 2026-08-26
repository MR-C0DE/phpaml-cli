# Données, validation et sécurité

## Configuration `.env`

Le modèle charge `.env`, puis combine ses valeurs avec `phpaml.json`. PHPAML
génère sa configuration interne dans `runtime/config/app.php` : ce fichier ne
doit jamais être modifié manuellement.

```dotenv
APP_DEBUG=false
DATABASE_DRIVER=sqlite
DATABASE_DSN=sqlite:runtime/storage/database.sqlite
DATABASE_USER=root
DATABASE_PASSWORD=root
```

SQLite fonctionne sans authentification : les valeurs `root`/`root` sont
conservées comme convention de développement et pour faciliter un passage à
MySQL. La configuration peut être créée et modifiée entièrement avec AML :

```bash
aml env:init
aml env:set APP_DEBUG false
aml env:get APP_DEBUG
aml env:list
aml db:configure sqlite
aml db:configure sqlite --path storage/app.sqlite
aml db:show
```

Pour MySQL :

```bash
aml db:configure mysql --host 127.0.0.1 --port 3306 \
  --database phpaml --user root --password root
```

Lecture dans la configuration :

```php
use PHPAML\Config\Env;

Env::get('DATABASE_DSN');
Env::bool('APP_DEBUG', false);
```

Ne publiez jamais `.env` dans Git.

## Connexion PDO

`PHPAML\Data\Connection` configure PDO avec les exceptions, les résultats
associatifs et les requêtes préparées natives. Elle est injectable dans un
constructeur :

```php
use PHPAML\Data\Connection;

final class UserRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(int $id): array|false
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT * FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch();
    }
}
```

## QueryBuilder minimal

```php
$query = new PHPAML\Data\QueryBuilder($connection);
$users = $query->all('users');
$id = $query->insert('users', ['email' => 'user@example.com']);
```

Le QueryBuilder actuel expose seulement `all()` et `insert()`. Pour les autres
opérations, utilisez les requêtes préparées PDO.

## Migrations

```bash
aml make:migration create_users_table
aml migrate
aml migrate:rollback --steps 1
```

Les fichiers sont exécutés dans l'ordre de leur nom sous un verrou exclusif.
Le retour arrière appelle `down()` de la migration la plus récente. Certaines
bases valident automatiquement les instructions DDL : sauvegardez toujours la
base avant une migration de production.

Exemple :

```php
use PHPAML\Data\Connection;
use PHPAML\Data\Migration;

return new class extends Migration {
    public function up(Connection $connection): void
    {
        $connection->pdo()->exec(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)'
        );
    }

    public function down(Connection $connection): void
    {
        $connection->pdo()->exec('DROP TABLE users');
    }
};
```

Les migrations en attente sont exécutées dans une transaction et enregistrées
dans `aml_migrations`. La commande actuelle exécute `up()`; elle ne fournit pas
encore de commande de rollback.

## Validation

```php
use PHPAML\Validation\Validator;

$validator = new Validator();
$valid = $validator->validate($request->input(), [
    'email' => ['required', 'email'],
    'name' => ['required', 'string', 'min:2', 'max:100'],
]);

if (!$valid) {
    return $this->json(['errors' => $validator->errors()], 422);
}
```

Règles disponibles : `required`, `email`, `string`, `min:n` et `max:n`.

## CSRF et sessions

Ajoutez le middleware aux routes qui modifient des données :

```php
use PHPAML\Middleware\CsrfMiddleware;

'POST /users' => [
    'handler' => [UserController::class, 'store'],
    'middleware' => [CsrfMiddleware::class],
],
```

Dans le formulaire :

```php
<?= $this->csrfField() ?>
```

Une application classique ou AML View utilisant une session envoie le token
dans `X-CSRF-Token`. Les méthodes `GET`, `HEAD` et `OPTIONS` ne sont pas
bloquées. Une application créée avec `aml create-api` est une API pure : elle
n’utilise pas le CSRF de formulaire. Protégez ses routes privées avec les tokens
Bearer et les capacités API. Une requête Bearer n’est jamais soumise au CSRF.

## Middlewares globaux

```php
'middlewares' => [
    PHPAML\Middleware\SecurityHeadersMiddleware::class,
],
```

Le middleware fourni ajoute `X-Content-Type-Options`, `X-Frame-Options` et
`Referrer-Policy`. Le gestionnaire d’erreurs masque les détails lorsque
`APP_DEBUG=false`.
