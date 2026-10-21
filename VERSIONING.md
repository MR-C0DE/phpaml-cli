# Versioning and compatibility

PHPAML uses Semantic Versioning independently for each component:

- AML CLI stable: `1.6.0`
- AML CLI preview: `1.7.0-beta.46`
- PHPAML Framework preview: `0.3.0-beta.7`
- PHPAML Template preview: `0.5.0-beta.10`
- AML View preview: `0.1.0-beta.14`
- PHPAML Engine preview: `0.1.0-beta.10`

These preview values are checked against the package manifests by the CLI test
workflow. A version bump that leaves this document behind fails CI.

The CLI release manifest selects compatible framework and template versions.
`create-view` selects View `^0.1.0-beta.14` and Engine `^0.1.0-beta.10`;
View also requires Engine `^0.1.0-beta.10`. These are minimum compatible
versions, not a shared version number. The integration test installs the
exact candidate archives and records the resolved versions in `composer.lock`.
See [frontend release verification](docs/frontend-release.md).

Before `1.0.0`, framework and template minor versions may contain breaking changes and must include migration notes. After `1.0.0`, incompatible public API changes are reserved for major versions; minor versions add backward-compatible features and patch versions contain compatible fixes. Deprecations must be documented for at least one minor release before removal.

Every incompatible release must provide a migration guide covering configuration, directory layout, commands, public APIs, database migrations, and deployment changes.
