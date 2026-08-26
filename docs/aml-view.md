# AML View — declarative interfaces / interfaces déclaratives

AML View is the optional frontend layer for PHPAML. PHP renders the first HTML
document; PHPAML Engine then manages state, effects, collections and navigation
inside the browser. Classic PHPAML projects can continue to use PHP templates.

AML View est la couche frontend optionnelle de PHPAML. PHP produit le premier
document HTML, puis PHPAML Engine gère l’état, les effets, les collections et la
navigation dans le navigateur. Les projets classiques peuvent garder leurs vues PHP.

## Create an application / Créer une application

```bash
aml create-view-app my-project
cd my-project
aml serve
```

Use `aml create-view-app .` in the current directory. AML installs View and
Engine automatically. `aml create` keeps creating a classic MVC application
without AML View.

## Project structure / Structure du projet

```text
src/
├── controllers/              # HTTP and API controllers
├── models/                   # domain and data models
├── middleware/               # optional middleware
├── services/                 # optional services
├── locales/                  # optional JSON translation catalogs
└── views/
    ├── pages/
    │   ├── home/page.php     # /
    │   ├── about/page.php    # /about
    │   └── users/[id]/page.php
    ├── components/
    ├── layouts/
    ├── states/               # Loading, Error and NotFound
    ├── stylesheets/
    ├── themes/
    └── assets/
routes/
└── WebApp.php                # classic web routes
public/
├── index.php                 # only PHP entry point
├── favicon.svg
├── robots.txt
└── sitemap.xml
runtime/                      # generated engine, config and private data
tests/
phpaml.json
.env
```

`src/views`, `src/controllers` and `src/models` are mandatory. `public/` is
reserved for files needing a direct URL. Interface images belong in
`src/views/assets`; CSS belongs in `src/views/stylesheets`. Shared settings are
stored in `phpaml.json`, secrets in `.env`, and generated configuration in
`runtime/config/app.php`; never edit the generated file manually.

## Pages and components / Pages et composants

```php
<?php

use AML\View\Attributes\State;
use AML\View\Page;
use AML\View\View;
use function AML\View\{Button, Heading, Text, VStack};

final class Home extends Page
{
    #[State]
    public int $count = 0;

    public function body(): View
    {
        return VStack(
            Heading('AML View')->size(42)->bold(),
            Text("Current value: {$this->count}"),
            Button('Add one')->onClick(fn () => $this->count++),
        )->class('home-hero')->gap(16)->padding(40);
    }
}
```

Built-in elements are called without `new`. `View(...)` groups siblings without
adding a visual container, like a fragment.

## Stylesheets and assets / Styles et ressources

AML View collects every `.css` file below `src/views/stylesheets`, regardless
of folder organization. Keep selectors class-based and attach them explicitly:

```php
Heading('Welcome')->class('home-title', 'featured');
```

```css
.home-title { font-size: clamp(2.5rem, 8vw, 5rem); }
.featured { color: var(--accent); }
```

Use `src/views/assets` for imported images. Keep `favicon.svg`, `robots.txt`,
`sitemap.xml` and direct downloads in `public/`.

## Reactive state and collections / État réactif et collections

`#[State]` values are serialized into the initial document. Signed declarative
actions update them locally through PHPAML Engine; a simple click does not call
the PHP server again.

Collections support append, prepend, keyed update and removal, sorting,
filtering, reversal, clearing and atomic transactions. Keyed rendering preserves
the DOM identity of unchanged elements. Asynchronous actions expose loading,
disabled, success and error targets and can discard stale responses.

## Effects / Effets

Effects react to state dependencies, timers, browser events or API calls. They
are mounted and cleaned up with their component. Debounce, throttle,
pause/resume, cycle protection and latest-request cancellation are available.
Closures are never sent to the browser: supported operations compile to signed
declarative actions.

## Navigation and route states / Navigation et états de route

Internal links are intercepted by Engine. It fetches the next document, swaps
the navigation boundary, updates history, metadata and focus, and keeps the
current browser document alive.

```text
src/views/pages/users/[id]/page.php  → /users/42
src/views/states/Loading.php         → pending navigation
src/views/states/Error.php           → HTTP/network failure
src/views/states/NotFound.php        → HTTP 404
```

`RouterView()` renders the current page and `Slot()` inserts a page in its
layout.

## Internationalization / Internationalisation

```bash
aml install i18n
aml i18n:add es
aml i18n:check
```

Translations are JSON files below `src/locales/{locale}` and can be organized
freely. English is the recommended primary locale and French can be configured
as a secondary locale through `APP_LOCALE` and `APP_FALLBACK_LOCALE`.

## Production / Production

```bash
aml test
aml build
aml deploy:check production
aml deploy production
```

The build supports classic `app/` and AML View `src/` projects through
`build-manifest.json`. The checksum is verified before SSH/SFTP transfer. See
[Build and deployment](build-deployment.md) for all strategies.

## Migrate an older project / Migrer un ancien projet

```bash
aml migrate:structure
aml migrate:structure --apply --yes
```

The dry run lists changes first. Applying creates a backup, moves `aml_env/` to
`runtime/`, `info.json` to `phpaml.json`, legacy `database/` to
`runtime/database/`, and updates known references. AML refuses ambiguous
structures where old and new directories coexist.
