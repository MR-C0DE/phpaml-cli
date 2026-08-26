# Versioning and compatibility

PHPAML uses Semantic Versioning independently for each component:

- AML CLI stable: `1.6.0`
- AML CLI preview: `1.7.0-beta.18`
- PHPAML Framework preview: `0.3.0-beta.3`
- PHPAML Template preview: `0.5.0-beta.3`

The CLI release manifest selects compatible framework and template versions. A CLI update does not imply that all three components share the same number.

Before `1.0.0`, framework and template minor versions may contain breaking changes and must include migration notes. After `1.0.0`, incompatible public API changes are reserved for major versions; minor versions add backward-compatible features and patch versions contain compatible fixes. Deprecations must be documented for at least one minor release before removal.

Every incompatible release must provide a migration guide covering configuration, directory layout, commands, public APIs, database migrations, and deployment changes.
