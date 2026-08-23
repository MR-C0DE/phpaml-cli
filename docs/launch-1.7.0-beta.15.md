# PHPAML CLI 1.7.0-beta.15 launch kit

This document is a publication draft. Verify every version, asset, checksum,
and link against the release candidate before publishing it.

## Core message

PHPAML is a self-contained PHP platform for building structured web
applications, declarative interfaces, and APIs without manually setting up
PHP, Composer, or a development environment.

## GitHub release — English

### PHPAML becomes an open-source platform

PHPAML CLI 1.7.0-beta.15 makes the platform easier to understand, try, and
contribute to.

Install PHPAML on Windows, macOS, or Linux and create a ready-to-run project
without installing PHP or Composer globally. Choose a structured MVC
application, a declarative AML View interface, or a JSON API from the same
`aml` workflow.

```bash
aml create-view-app my-interface
cd my-interface
aml doctor
aml serve
```

Then open `http://127.0.0.1:8910`.

### What changed

- PHPAML CLI, Framework, and the official Template now use the MIT License.
- The repository now leads with the self-contained platform promise.
- English is the primary README language, with a complete French version.
- The quick starts show the result for MVC, AML View, and API projects.
- The relationship between CLI, Framework, View, Engine, and Data is explicit.
- A current application screenshot and public demos provide immediate proof.
- Contribution, conduct, security, and pull-request guidance are available.
- Installation reports measure successful and failed first projects without
  hidden telemetry.

PHPAML is still in beta. Validate production applications with the documented
production checklist.

**Try it:** https://phpaml.com/download

**Documentation:** https://phpaml.com/docs

**Live demo:** https://phpaml-book-reader-demo.onrender.com

**Share your first-run result:**
https://github.com/MR-C0DE/phpaml-cli/issues/new?template=installation-report.yml

## GitHub release — Français

### PHPAML devient une plateforme open source

PHPAML CLI 1.7.0-beta.15 rend la plateforme plus facile à comprendre, essayer
et améliorer.

Installez PHPAML sur Windows, macOS ou Linux et créez un projet prêt à
fonctionner sans installer globalement PHP ou Composer. Utilisez le même flux
`aml` pour une application MVC structurée, une interface déclarative AML View
ou une API JSON.

```bash
aml create-view-app mon-interface
cd mon-interface
aml doctor
aml serve
```

Ouvrez ensuite `http://127.0.0.1:8910`.

### Ce qui change

- PHPAML CLI, Framework et le Template officiel adoptent la licence MIT.
- Le dépôt présente désormais clairement la plateforme autonome.
- L’anglais devient la langue principale du README, avec une version française
  complète.
- Les démarrages rapides montrent le résultat pour MVC, AML View et les API.
- La relation entre CLI, Framework, View, Engine et Data devient explicite.
- Une capture actuelle et des démonstrations publiques apportent une preuve
  immédiate.
- Les règles de contribution, de conduite et de sécurité sont documentées.
- Les rapports d’installation mesurent les premiers projets réussis et échoués
  sans télémétrie cachée.

PHPAML reste en bêta. Toute application en production doit suivre la liste de
vérification documentée.

**Essayer :** https://phpaml.com/fr/download

**Documentation :** https://phpaml.com/fr/docs

**Démo publique :** https://phpaml-book-reader-demo.onrender.com

**Partager le résultat du premier essai :**
https://github.com/MR-C0DE/phpaml-cli/issues/new?template=installation-report.yml

## Short announcement — English

PHPAML is now MIT licensed.

Build structured PHP applications, declarative interfaces, and APIs without
manually installing PHP or Composer. One autonomous workflow for Windows,
macOS, and Linux.

Try the beta: https://phpaml.com/download

## Annonce courte — Français

PHPAML est désormais sous licence MIT.

Créez des applications PHP structurées, des interfaces déclaratives et des API
sans installer manuellement PHP ou Composer. Un environnement autonome pour
Windows, macOS et Linux.

Essayez la bêta : https://phpaml.com/fr/download

## Five-minute demonstration script

1. Show a clean machine without a global `php` or `composer` command.
2. Install PHPAML using the official platform installer.
3. Run `aml create-view-app lighthouse-demo`.
4. Run `aml doctor` and point out the successful diagnostics.
5. Run `aml serve` and open the generated application.
6. Change one visible heading and demonstrate live reload.
7. Open the project structure and identify View, Engine, and Data roles.
8. End on the installation-report link, including when the run succeeded.

The final video should be under five minutes, show the actual result, and avoid
long explanations of internal architecture before the application runs.

## Publication sequence

1. Publish compatible Framework and Template releases under MIT.
2. Update the CLI release manifest to those exact versions.
3. Run all platform builds and clean-install checks.
4. Publish PHPAML CLI 1.7.0-beta.15 and verify every checksum.
5. Update and publish the official website release metadata.
6. Publish the English announcement and French translation together.
7. Share the five-minute demonstration.
8. Review installation reports after 24 hours, 7 days, and 30 days.

## Success measures

Record a baseline immediately before publication, then measure:

- unique release downloads by platform;
- installation reports submitted;
- first projects that succeed without assistance;
- failures during installation, creation, diagnostics, and serving;
- documentation-to-download conversion when analytics are available;
- GitHub stars, forks, contributors, and resolved first-run issues;
- returning reporters or contributors after 7 and 30 days.

Do not interpret GitHub stars alone as adoption. A successful first project is
the primary activation event.

The pre-launch values and interpretation limits are recorded in
[the 2026-08-23 adoption baseline](adoption-baseline-2026-08-23.md).
