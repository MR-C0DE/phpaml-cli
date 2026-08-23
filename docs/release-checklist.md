# Public release checklist

Use this checklist for the first PHPAML CLI release that includes the new
open-source positioning and for later public releases.

## Identity and repository

- [ ] `README.md` leads with the self-contained platform promise.
- [ ] `README.fr.md` communicates the same product scope in French.
- [ ] The current product screenshot renders correctly on GitHub.
- [ ] Live demo and source links open the intended projects.
- [ ] CLI, Framework, View, Engine, Data, i18n, and Template roles are accurate.
- [ ] GitHub repository description and topics match the README positioning.
- [ ] The GitHub social preview does not show an obsolete version number.

## License and community

- [ ] CLI, Framework, View, Engine, Data, i18n, and Template declare MIT.
- [ ] Every distributed repository contains the complete MIT license text.
- [ ] Existing copyright notices are preserved.
- [ ] Contribution and Code of Conduct links render on GitHub.
- [ ] Installation, bug, and proposal issue forms can be submitted.
- [ ] Security reports point to private GitHub Security Advisories.

## Release integrity

- [ ] `VERSIONING.md`, `CHANGELOG.md`, installer metadata, and website release
  data agree on the version.
- [ ] Framework and Template versions selected by the CLI are compatible.
- [ ] Linux x64, macOS ARM64, and Windows x64 workflows pass.
- [ ] Installer artifacts and SHA-256 files are present for every platform.
- [ ] A clean installation succeeds without global PHP or Composer.
- [ ] Upgrade from the current stable CLI succeeds.
- [ ] `aml doctor`, project creation, `aml serve`, tests, and production build
  succeed for MVC, AML View, and API projects.

## First-run measurement

- [ ] Publish the installation-report link in the release notes.
- [ ] Record successful and failed reports by platform and project type.
- [ ] Separate installation failures from creation, diagnostics, and serve
  failures.
- [ ] Review results after 24 hours, 7 days, and 30 days.
- [ ] Turn repeated failures into documented fixes or tracked issues.
- [ ] Publish only aggregated counts; never copy secrets or personal data.

## Communication

- [ ] Release notes state the user outcome before listing implementation work.
- [ ] English announcement is primary and a French version is available.
- [ ] Demonstrate one complete five-minute journey with its final result.
- [ ] Link the website, documentation, live demo, source, and release assets.
- [ ] Clearly label beta limitations and the production checklist.
