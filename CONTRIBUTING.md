# Contributing to PHPAML CLI

Thank you for helping make PHPAML easier to install, understand, and use.

## Before you start

- Search existing issues before opening a new one.
- Keep proposals focused on one problem or outcome.
- Never include passwords, tokens, private URLs, or unredacted environment
  files in an issue or test fixture.
- For security vulnerabilities, follow [SECURITY.md](SECURITY.md) instead of
  opening a public issue.

## Report an installation or first-run problem

Please include:

- Operating system, version, and architecture
- PHPAML CLI version from `aml version`
- Installation method
- Exact command that failed
- Expected and actual result
- Sanitized terminal output
- Result of `aml doctor` when available

These reports help PHPAML improve first-project success without hidden usage
tracking.

## Propose a change

Open an issue before investing in a large feature or architectural change. For
small fixes, documentation, and tests, a focused pull request is welcome.

1. Fork and clone `MR-C0DE/phpaml-cli`.
2. Create a short, descriptive branch.
3. Make the smallest change that completely solves the problem.
4. Add or update tests and documentation when behavior changes.
5. Run the relevant smoke tests from `tests/`.
6. Open a pull request describing the problem, solution, and verification.

Pull requests should avoid unrelated formatting or refactoring. Generated
archives, downloaded build dependencies, logs, credentials, and local machine
configuration must not be committed.

## Documentation and language

English is the primary public language. User-facing CLI output and core guides
should remain available in English and French. When changing shared concepts,
update both languages in the same pull request whenever possible.

## License

By contributing, you agree that your contributions are licensed under the
[MIT License](LICENSE). Existing copyright notices must be preserved.

## Français

Les rapports de bogues, résultats d’installation, améliorations de la
documentation et pull requests ciblées sont bienvenus. Avant une modification
importante, ouvrez une issue pour valider la direction. N’incluez jamais de
secret ou de fichier d’environnement non nettoyé.

Indiquez au minimum votre système, la version retournée par `aml version`, la
commande concernée, le résultat attendu, le résultat obtenu et la sortie
nettoyée. En contribuant, vous acceptez que votre contribution soit distribuée
sous licence MIT et que les mentions de copyright existantes soient conservées.

