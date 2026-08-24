# PHPAML CLI 1.7.0-beta.16 contributor backlog

These issue drafts are intentionally small, independently verifiable, and
suitable for a first contribution. Publish them with `good first issue` and
`help wanted` after confirming that no equivalent pull request is active.

## 1. Document one clean first run on Windows, macOS, or Linux

Labels: `documentation`, `good first issue`, `help wanted`

### Goal

Validate the public `1.7.0-beta.16` first-project workflow on one clean machine
and add the observed result to the installation documentation.

### Work

1. Download the official installer for one supported platform.
2. Run:

   ```bash
   aml create-view-app first-phpaml-app
   cd first-phpaml-app
   aml doctor
   aml serve
   ```

3. Record the operating-system version, installer used, CLI version, selected
   port, and whether the generated page opened successfully.
4. Add a short verified-platform note to the appropriate installation page.
5. Do not include usernames, home-directory paths, tokens, or other private
   machine information.

### Acceptance criteria

- The report uses an official `1.7.0-beta.16` package and checksum.
- The documentation identifies exactly one tested platform.
- Commands and expected results remain valid in English and French navigation.
- `tests/docs-smoke.php` passes.

## 2. Add a regression for a Framework archive with an unexpected root name

Labels: `good first issue`, `help wanted`

### Goal

Strengthen the beta.16 archive fix by proving that Framework extraction depends
on the `src/` structure rather than a hard-coded repository folder name.

### Work

Extend `tests/view-smoke.sh` with a second fixture whose archive root is not
`phpaml-framework` or `phpaml-framework-<version>`, for example
`downloaded-source/src/`. Confirm that `Autoloader.php` and
`Security/CspNonce.php` are installed into `runtime/framework/`.

### Acceptance criteria

- The new fixture fails against the pre-beta.16 hard-coded extraction logic.
- The current extraction logic passes without production-only test branches.
- The test verifies file contents or loadable classes, not only directories.
- `PHP_BINARY=php ./tests/view-smoke.sh` passes.

## 3. Add a copy-and-run AML View counter to the documentation

Labels: `documentation`, `good first issue`, `help wanted`

### Goal

Give a new visitor one minimal interactive AML View example immediately after
creating a project.

### Work

Add a compact counter page to the English and French documentation. It should
use the current public View API, contain local reactive state, display the
current value, and increment it from a button without handwritten JavaScript.
Include the destination file path and the command used to launch the project.

### Acceptance criteria

- The example works in a project created by `aml create-view-app`.
- English and French versions contain equivalent executable code.
- Every imported symbol is shown or already present in the generated page.
- The example states that AML View and Engine are installed automatically.
- `tests/docs-smoke.php` passes.

