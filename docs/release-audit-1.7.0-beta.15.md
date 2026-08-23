# Release audit — PHPAML CLI 1.7.0-beta.15

Audit date: 2026-08-23

## Verdict

**Ready for commits and platform release candidates. Not ready to publish yet.**

The local source, documentation, package metadata, and website pass their
available checks. Publication remains blocked on tagged Framework and Template
releases plus installer builds on the three supported platforms.

## Prepared version set

| Component | Previous | Candidate |
| --- | --- | --- |
| PHPAML CLI | 1.7.0-beta.14 | 1.7.0-beta.15 |
| Framework | 0.3.0-beta.1 | 0.3.0-beta.2 |
| Template | 0.5.0-beta.1 | 0.5.0-beta.2 |

The Template manifest requires Framework `0.3.0-beta.2`. The CLI manifest
selects Template `0.5.0-beta.2`. The website must remain on beta.14 until the
beta.15 installer names, sizes, and SHA-256 values exist.

## Checks passed

### PHPAML CLI

- Private PHP runtime selection
- English/French language behavior
- `aml doctor`
- SEO configuration and generation
- AI debugger configuration
- Production build, checksum, and deployment profiles
- Data and MongoDB module installation routing
- AML View generators
- Classic and modern API generators and field migrations
- Environment and database commands
- Legacy structure migration
- 17 documentation pages
- Shell syntax for Linux and macOS build scripts
- JSON parsing for all changed manifests

### Framework

- Strict Composer validation
- Complete framework suite: 29 successful scenarios
- Configuration, routes, HTTP safety, CSRF, CSP, API, tokens, auth,
  validation, uploads, data queries, logging, migrations, rate limits,
  observability, idempotency, and OpenAPI

### Template

- Strict Composer validation
- Complete template suite: 11 successful scenarios
- Request, response, container, routing, validation, rendering, 404, and CSRF

### Official website

- TypeScript route generation and type checking
- ESLint
- Sites/Vinext production build
- Complete rendered bilingual route test
- Open Graph and Twitter metadata use the evergreen `og-v3.png`

## Release blockers

1. Review and commit the Framework MIT changes.
2. Tag and publish Framework `v0.3.0-beta.2`.
3. Review and commit the Template MIT and version changes.
4. Tag and publish Template `v0.5.0-beta.2`.
5. Review and commit PHPAML CLI `1.7.0-beta.15`.
6. Run the GitHub Actions matrix, including Go tests and Linux, macOS, and
   Windows installer construction.
7. Clean-install every produced installer on its supported platform.
8. Verify every installer and portable archive against its published SHA-256.
9. Update `website/app/release.ts` with the real beta.15 asset sizes and hashes.
10. Rebuild and publish the website, then publish the CLI release and launch
    announcements.

## Environment limitation

Go is not installed in the local audit environment. The Go launcher test and
binary compilation therefore remain assigned to GitHub Actions, whose build
workflows explicitly install Go before running the builds.

## Non-blocking warning

The macOS shell emitted locale warnings for `C.UTF-8` during one deployment
smoke test. The test and archive verification succeeded. This warning concerns
the audit shell locale, not the generated application.
