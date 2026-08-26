# PHPAML package boundaries

PHPAML is a modular platform. An application may combine every package, but a
package must not force unrelated features on its users.

## Public packages

| Package | Responsibility | Allowed PHPAML dependency |
|---|---|---|
| `phpaml/framework` | HTTP, routing, middleware, controllers and security | none |
| `phpaml/engine` | Browser runtime, state, effects and client navigation | none |
| `phpaml/view` | Declarative user interfaces | `phpaml/engine` |
| `phpaml/data` | SQL persistence, entities, queries and transactions | none |
| `phpaml/data-mongodb` | MongoDB adapter for Data | `phpaml/data` |
| `phpaml/i18n` | Locales, translation and regional formats | none |
| PHPAML CLI | Installation, scaffolding, build and deployment | orchestrates packages |

“Independent” does not mean isolated. Applications compose packages in their
bootstrap. It means that Data can be used without View, i18n without Engine,
and Framework without installing every optional feature.

## Dependency direction

```text
CLI (installation and project tooling)

Framework       Data       i18n
                    ↑
              Data MongoDB

Engine
  ↑
View
```

Application code is the composition root. It may depend on several packages
and connect them. A lower-level package must not import application code or an
unrelated package to perform that composition.

## Current compatibility debt

New applications register PHPAML Data through the generic `bootstrappers`
configuration at their composition root (`public/index.php`). The Framework
therefore does not need Data for the normal path.

`Framework/src/WebApplication.php` still contains a disabled-by-new-projects
fallback using `class_exists()` to preserve existing applications. The
dependency is optional at runtime, but the compatibility path still knows
concrete Data class names. This is a temporary migration bridge, not the target
architecture.

The replacement will move Data registration to application bootstrap code. It
must preserve projects created by the current beta before the bridge is
removed.

## Automated rule

`tests/architecture-boundaries.php` verifies Composer dependencies and source
namespace references. CI rejects any new dependency that violates this table.
The known compatibility bridge is limited to one exact file so the debt cannot
spread silently.
