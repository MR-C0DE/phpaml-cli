# Privacy-first adoption measurement

PHPAML measures public interest and voluntary first-project outcomes without
adding hidden telemetry to the CLI.

## What is collected

The weekly `Public adoption metrics` workflow reads only:

- public GitHub repository counts;
- public download-request counts for the selected release;
- aggregate fields from issues labeled `installation` that users chose to submit.

The generated report does not retain issue authors, issue bodies, commands,
diagnostic output, IP addresses, device identifiers, or installation IDs. The
workflow uploads JSON and Markdown aggregates as a private workflow artifact
with 90-day retention; it does not commit changing counters to the repository.

## Run a report

```bash
node --test tests/adoption-report.test.mjs
node scripts/adoption-report.mjs \
  --release=v1.7.0-beta.15 \
  --json=phpaml-adoption.json \
  --markdown=phpaml-adoption.md
```

`GITHUB_TOKEN` is optional for public data and recommended in automation to
avoid anonymous API rate limits.

## Activation definition

A confirmed successful first project is a voluntary report where a developer:

1. installs PHPAML;
2. creates a project;
3. passes diagnostics;
4. starts the application;
5. sees the generated result.

Failures are separated into installation, project creation, diagnostics, and
application-start stages so repeated friction can become a specific fix.

## Interpretation rules

- A GitHub download is a request, not a unique person or installation.
- Checksum downloads are reported separately from binaries.
- Automated verification and repeated requests may inflate counts.
- Voluntary reports have strong selection bias and are not a representative
  success rate until the sample is sufficiently large.
- Publish aggregates only. Never reproduce issue authors, raw commands,
  diagnostics, secrets, personal data, or private system information.

Compare snapshots after 24 hours, 7 days, and 30 days using the original
[pre-launch baseline](adoption-baseline-2026-08-23.md).
