# Public API stability

PHPAML follows Semantic Versioning for every independently published package.

## Compatibility promise

Once a package reaches a stable release:

- patch releases fix defects without changing the public API;
- minor releases may add APIs but do not remove or alter existing contracts;
- breaking changes require a major release and a migration guide.

During the beta period, breaking changes remain possible, but they must be
detected, explained in the changelog and accompanied by migration instructions.
They must never enter a release unnoticed.

## What is public

Classes, interfaces, traits, enums, attributes, functions and public or
protected methods reachable through a package Composer autoloader are public,
unless their namespace contains `Internal`.

CLI commands, documented options, generated project structure and the
`phpaml.json` schema are also user-facing contracts. Their compatibility is
covered by CLI smoke and previous-project tests rather than PHP reflection.

## Automated verification

Framework, Engine, View, Data and i18n run Roave Backward Compatibility Check
for every pull request and push to `main`. The job compares `HEAD` with the
latest published tag and rejects removed classes, changed signatures and other
incompatible PHP API changes.

A compatibility failure must be resolved by preserving the contract or by
documenting and versioning an intentional breaking change. Disabling the check
is not an acceptable fix.

