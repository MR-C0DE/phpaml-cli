# Frontend release — CLI 1.7.0-beta.46

This candidate finalizes explicit CSS imports, Style and the existing beta.46
Picker, Toggle and Calendar work. The Style API scope is frozen for beta.46;
finalization fixes declaration clearing, reactive priority, CSS-wide keywords
and PHP test parity.
The verification app includes the distributed `/controls`, `/style-check` and
`/style-conditions` fixtures in development and production. These test pages are
not inserted into normal starters.

The finalization preserves the current View, Engine and CLI APIs. The packages
remain independently versioned:

| Component | Version | Selection |
|---|---|---|
| CLI | `1.7.0-beta.46` | Scaffolding, installation and build |
| View | `0.1.0-beta.14` | CLI requires `^0.1.0-beta.14` |
| Engine | `0.1.0-beta.10` | CLI and View require `^0.1.0-beta.10` |
| Template | `0.5.0-beta.10` | Classic MVC foundation, unchanged |
| Framework | `0.3.0-beta.7` | Existing server foundation, unchanged |

## Release notes

The CLI-generated View starter is refreshed separately from the classic MVC
template: native semantic components, colocated `.style.css` modules, fluid
typography, container queries and shared light/dark design tokens. Its reusable
`Playground` component demonstrates TextField, Picker, Toggle and automatic
State-to-Style bindings. No new View/Engine API is introduced. The `--empty`
starter stays minimal and existing projects are left intact.

Style supports fluent/named/composed PHP styles, local CSS files, reactive
values, native pseudo states/elements, media/supports/container conditions and
scoped keyframes. The finalization adds no Style API. Empty ordinary values
restore the surrounding CSS cascade; important priority belongs to the
declaration, not its storage variable. Malformed updates retain the prior value
and report a recoverable error. TestView follows the same binding contracts.

New View applications serve the full Engine asset manifest and use the modular
entry by default. State, events, DOM rendering, conditions, lists, navigation,
lifecycle, local forms, diagnostics and module loading stay in Engine Core.
Files, Browser APIs and Animations load only when the interface requires their
existing behavior. The seven optional PHP families are Unicode, Intl, BCMath,
Regex, Date, SPL and Reflection. These are chunks shipped inside Engine, not
separate Composer packages. No Charts, Realtime, Media, Canvas or 3D API is
introduced by this release.

Existing `EngineRuntime::script()`, `assetPath()` and `externalScript()` calls
retain their behavior. Existing applications may keep the complete runtime.
For a modular integration, use `externalScript(modular: true)` together with
the allowlisted asset route; deploy all files from the same build, preserving
their relative names. The CLI's `--empty`, `create-view-app` alias and
`--view-version` option remain available. Earlier console work is retained.

The integration check exposed a server composition bug: a declarative View
404/error page was replaced by the Framework's generic error body. The CLI now
preserves the View page after middleware processing, with the original error
status and final security headers. This repairs an existing rendering path.

En français : cette version harmonise les versions installées, conserve les API
et vérifie une application réellement créée par le CLI. La séparation des
modules change le chargement du code, pas les composants de l'application.

## Additional View and Engine changes

The release also includes functional pages/components with local `State()` and
optional `body()`, constructor-derived component factories, reactive `IList()`
and optional Lucide-backed `Icon()`. Class components remain supported. Lucide
is installed explicitly with `aml install lucide` and removed with
`aml uninstall lucide`; its assets are served by the application.

The final stabilization verifies nested conditions and loops, component bindings,
CSS/Style and icon recovery. Completed compiler blocks are serialized once per
compilation. Loop and serialization guards remain enabled, with precise PHP source
diagnostics and recoverable errors. The full and modular runtimes share these
behaviors; the compatibility guide defines the supported PHP frontend scope.

## Reproduce the distribution check

Check out the five repositories as siblings named `phpaml-cli`, `phpaml-view`,
`phpaml-engine`, `phpaml-template` and `phpaml-framework`. Use PHP 8.2+, Composer,
Node.js and the Engine development dependencies. Install the Playwright browsers
using the version locked in Engine, then run from the CLI repository:

```bash
node scripts/test-frontend-release.mjs
```

The script snapshots tracked and non-ignored source files into release ZIPs,
respecting `export-ignore`, without changing the source Git indexes. It checks
Engine build reproducibility and the version policy before packaging. It then
extracts the CLI, caches the real Template/Framework archives, executes
`create-view --offline` and lets real Composer install the candidate View and
Engine ZIPs. The starter's PHPStan dependency still uses Composer normally;
the CLI's offline flag only covers Template and Framework caching.

`PHPAML_ENGINE_SOURCE`, `PHPAML_VIEW_SOURCE`, `PHPAML_TEMPLATE_SOURCE` and
`PHPAML_FRAMEWORK_SOURCE` can select different checkout roots.
`PHP_BINARY` and `COMPOSER_BINARY` can select installed or bundled executables.
For a completely offline run, `PHPAML_EXTRA_PACKAGES_REPOSITORY` can name a
JSON array containing genuine Composer package definitions with local `dist`
archives for development dependencies. This disables Packagist; it does not
replace Composer with a stub. `COMPOSER_DISABLE_NETWORK=1` prevents fallback.
`PHPAML_COMPOSER_CACHE_SOURCE` copies an existing Composer cache into the
isolated test cache, including archives already referenced by the starter's lockfile.

The check verifies the resolved lockfile and autoload origins, runs the generated
application's tests and diagnostics, builds its production ZIP, checks every
manifest hash, and diagnoses the extracted production app with local test
deployment settings. It then verifies development and production with both
complete and modular runtimes on Chromium, Firefox and WebKit: navigation,
history, CSS, 404 responses and every served Engine asset. The starter checks
also exercise input escaping, reset, keyboard toggle, reactive styles without
DOM replacement, scoped stylesheet cleanup, narrow layouts, dark mode and
reduced motion. WebKit is an engine
test, not a claim that a particular installed Safari version was tested.

`--prepare-only` performs packaging and PHP checks without browsers. It leaves
`browsers: not-run` in the report and is not a complete release validation.
The default output is `dist/frontend-1.7.0-beta.46/` (override with
`PHPAML_RELEASE_OUTPUT`). It contains ZIPs, `SHA256SUMS`, `verification.json`,
logs, the created application, its production copy and browser reports.
Failed runs exit nonzero and preserve their logs for diagnosis.

The installed Style regressions exercise reactive priority, empty declarations,
composition, error recovery, container/supports rules and keyframes. Their PHP
counterparts run against the installed autoloader, including TestView. Update
channel checks execute the extracted CLI resolver against mocked stable/beta
release metadata for macOS, Linux and Windows; they do not publish a release,
install an operating-system package or establish live update availability.

On macOS, after enabling Safari's remote automation once, supplement the
Playwright run with the installed Safari:

```bash
node tests/frontend-release-safari.mjs
```

It reads `verification.json`, starts its own local servers and WebDriver,
checks navigation/history, CSS, 404 pages and runtime selection in development
and production, and writes `safari.json` with the actual browser version.
The script closes only the sessions and servers it created.

For a local finalization run, set `CHROMIUM_EXECUTABLE_PATH` to the installed
Google Chrome binary to verify Chrome itself, and keep the Safari report beside
the same archive checksums. A fresh source edit requires rebuilding the archive
before treating its previous verification as evidence for that edit.

## Publication order

These commands prepare local candidates; they do not tag, upload or publish.
Publish Engine beta.10 before View beta.14, then CLI beta.46 after dependency
resolution against the published packages succeeds. Keep Template and Framework
at their validated versions. Before a public CLI release, also run the existing
Linux, macOS and Windows standalone packaging workflows and installation tests.
Local macOS archives do not establish Windows/Linux installation compatibility.
Use the [release checklist](release-checklist.md) for the remaining release gates.

## Native packages and publication

Beta.46 pins PHP `8.4.27` in both native build configurations and in
`phpaml.json`. It includes the upstream fixes for CVE-2026-109618 and
CVE-2026-109619. Package checks execute the bundled binary and reject an older
or different version, including after installation and portable extraction.
The macOS publication verifier repeats this check on the signed payload.
See the [PHP security release](https://www.php.net/releases/8_4_27.php).

`Validate native packages` invokes the Linux, macOS and Windows build workflows
without publishing. Each platform checks its actual installation, portable
archive, every packaged PHP source file and console behavior. Linux and Windows also
check uninstallation. A local macOS payload extraction alone is not an installer
validation.

`Publish CLI release` is dispatched explicitly from `main` with an existing
version tag. It rejects a tag that differs from its `phpaml.json`, incomplete or
failed latest builds, builds from a different commit, missing or unexpected
assets and incorrect SHA-256 checksums. All three native builds must originate
from a push of that exact tag. The release includes their commit/run provenance
and the dated changelog section. Existing releases are never overwritten.

For prepublication CI, the Engine Tests workflow accepts `view_ref`, View Tests
and API compatibility accept `engine_ref`, and the CLI frontend-distribution
workflow accepts both. Use exact candidate commit IDs; their default remains the
normal published dependencies and main source branches.
