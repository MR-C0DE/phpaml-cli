# PHPAML adoption baseline — 2026-08-23

This baseline was captured before publishing the open-source repository and
positioning changes. It uses public GitHub metadata only and contains no hidden
product telemetry or personal data.

## Repository

| Metric | Baseline |
| --- | ---: |
| GitHub stars | 1 |
| Forks | 0 |
| Open issues and pull requests | 0 |
| Repository subscribers | 1 |
| Repository created | 2026-08-07 |
| Last public push | 2026-08-21 |
| Publicly detected license | None |

The public repository does not yet expose a detected license because the new
MIT file remains local until publication.

## Stable release baseline

Latest stable release: `v1.6.0`, published 2026-08-12.

| Asset category | Linux | macOS | Windows | Total |
| --- | ---: | ---: | ---: | ---: |
| Installer | 24 | 26 | 25 | 75 |
| Installer checksum | 23 | 24 | 25 | 72 |
| Portable archive | 0 | 0 | 0 | 0 |
| Portable checksum | 0 | 0 | 0 | 0 |

## Preview release baseline

Current preview: `v1.7.0-beta.14`, published 2026-08-21.

| Asset category | Linux | macOS | Windows | Total |
| --- | ---: | ---: | ---: | ---: |
| Installer | 50 | 51 | 50 | 151 |
| Installer checksum | 50 | 51 | 50 | 151 |
| Portable archive | 0 | 0 | 0 | 0 |
| Portable checksum | 0 | 0 | 0 | 0 |

## Interpretation limits

GitHub exposes asset download requests, not unique installations or active
developers. Installer and checksum totals are almost identical, and the beta
counts are nearly uniform across platforms. Automated release verification,
bots, repeated downloads, and coupled installer/checksum requests may therefore
represent a material part of these totals.

Do not claim that PHPAML has 151 beta users. Use these counts only as a
release-to-release traffic baseline.

## Activation metrics for beta.15

The primary activation event is:

> A developer creates a PHPAML project, passes `aml doctor`, starts it with
> `aml serve`, and sees the application without assistance.

Record the following after 24 hours, 7 days, and 30 days:

| Metric | Before launch | 24 hours | 7 days | 30 days |
| --- | ---: | ---: | ---: | ---: |
| Stars | 1 | — | — | — |
| Forks | 0 | — | — | — |
| Installer download requests | 0 for beta.15 | — | — | — |
| Installation reports | 0 | — | — | — |
| Confirmed successful first projects | 0 | — | — | — |
| Installation failures | 0 | — | — | — |
| Project creation failures | 0 | — | — | — |
| Diagnostic failures | 0 | — | — | — |
| Serve failures | 0 | — | — | — |
| First-time contributors | 0 | — | — | — |

## Public sources

- Repository metadata: https://api.github.com/repos/MR-C0DE/phpaml-cli
- Stable release: https://github.com/MR-C0DE/phpaml-cli/releases/tag/v1.6.0
- Preview release: https://github.com/MR-C0DE/phpaml-cli/releases/tag/v1.7.0-beta.14

Capture the comparison values at approximately the same time of day and retain
the raw asset counts. Never infer unique users from GitHub download totals.
