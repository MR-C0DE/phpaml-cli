# Frontend release — CLI 1.7.0-beta.45

This release aligns applications created by the CLI with the modular Engine
distribution. It adds no application feature and preserves the current View,
Engine and CLI APIs. The packages remain independently versioned:

| Component | Version | Selection |
|---|---|---|
| CLI | `1.7.0-beta.45` | Scaffolding, installation and build |
| View | `0.1.0-beta.13` | CLI requires `^0.1.0-beta.13` |
| Engine | `0.1.0-beta.9` | CLI and View require `^0.1.0-beta.9` |
| Template | `0.5.0-beta.10` | Existing starter, unchanged |
| Framework | `0.3.0-beta.7` | Existing server foundation, unchanged |

## Release notes

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
history, CSS, 404 responses and every served Engine asset. WebKit is an engine
test, not a claim that a particular installed Safari version was tested.

`--prepare-only` performs packaging and PHP checks without browsers. It leaves
`browsers: not-run` in the report and is not a complete release validation.
The default output is `dist/frontend-1.7.0-beta.45/` (override with
`PHPAML_RELEASE_OUTPUT`). It contains ZIPs, `SHA256SUMS`, `verification.json`,
logs, the created application, its production copy and browser reports.
Failed runs exit nonzero and preserve their logs for diagnosis.

On macOS, after enabling Safari's remote automation once, supplement the
Playwright run with the installed Safari:

```bash
node tests/frontend-release-safari.mjs
```

It reads `verification.json`, starts its own local servers and WebDriver,
checks navigation/history, CSS, 404 pages and runtime selection in development
and production, and writes `safari.json` with the actual browser version.
The script closes only the sessions and servers it created.

## Publication order

These commands prepare local candidates; they do not tag, upload or publish.
Publish Engine beta.9 before View beta.13, then CLI beta.45 after dependency
resolution against the published packages succeeds. Keep Template and Framework
at their validated versions. Before a public CLI release, also run the existing
Linux, macOS and Windows standalone packaging workflows and installation tests.
Local macOS archives do not establish Windows/Linux installation compatibility.
Use the [release checklist](release-checklist.md) for the remaining release gates.

## Native packages and publication

`Validate native packages` invokes the Linux, macOS and Windows build workflows
without publishing. Each platform checks its actual installation, portable
archive, six packaged source files and console behavior. Linux and Windows also
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
