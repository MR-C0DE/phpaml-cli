# PHPAML CLI 1.7.0-beta.16 community launch pack

Publication status: ready. PHPAML CLI `1.7.0-beta.16` is public with verified
Windows, macOS, and Linux packages. The real 36-second first-run demonstration
is available in the repository and on the official bilingual website.

## Canonical links

- Repository: https://github.com/MR-C0DE/phpaml-cli
- Release: https://github.com/MR-C0DE/phpaml-cli/releases/tag/v1.7.0-beta.16
- Install: https://phpaml.com/download
- Installation en français: https://phpaml.com/fr/download
- Documentation: https://phpaml.com/docs
- 36-second demo: https://github.com/MR-C0DE/phpaml-cli/blob/main/docs/assets/demo/phpaml-demo-no-voice.mp4
- First-run report: https://github.com/MR-C0DE/phpaml-cli/issues/new?template=installation-report.yml

## Core message

PHPAML is an MIT-licensed, self-contained PHP platform for building structured
web applications, declarative interfaces, and APIs without manually setting up
PHP, Composer, or a development environment.

## GitHub Discussion

### Title

```text
PHPAML beta.16 is ready: try the autonomous first-project workflow
```

### Body

PHPAML CLI `1.7.0-beta.16` is now available for Windows, macOS, and Linux.

PHPAML packages its own PHP and Composer environment. From a fresh folder, one
workflow creates a structured MVC application, a declarative AML View
interface, or a JSON API:

```bash
aml create-view-app my-interface
cd my-interface
aml doctor
aml serve
```

The 36-second demonstration shows the real published CLI creating and running
an AML View application. No voice-over and no hidden setup steps:

https://github.com/MR-C0DE/phpaml-cli/blob/main/docs/assets/demo/phpaml-demo-no-voice.mp4

The project is MIT licensed and remains in beta. I would especially value
feedback on two questions:

1. Did your first project run without assistance?
2. Is the relationship between CLI, Framework, View, Engine, and Data clear?

Install: https://phpaml.com/download

Report the first run, including successful ones:
https://github.com/MR-C0DE/phpaml-cli/issues/new?template=installation-report.yml

## X / Bluesky

```text
PHPAML beta.16 is out.

Build structured PHP apps, declarative interfaces and APIs without manually setting up PHP or Composer.

✓ Autonomous CLI
✓ Windows, macOS, Linux
✓ MIT licensed
✓ Real app in 36 seconds

https://github.com/MR-C0DE/phpaml-cli
```

## LinkedIn

```text
PHPAML CLI 1.7.0-beta.16 is now available.

The goal is simple: evaluating a PHP platform should begin with a running application, not with environment configuration.

PHPAML includes its own PHP and Composer runtimes on Windows, macOS and Linux. The same `aml` workflow can create a structured MVC application, a declarative AML View interface or a JSON API.

The new 36-second demonstration uses the real published CLI from a fresh folder. PHPAML is open source under the MIT License and still in beta, so candid first-run feedback is especially valuable.

Repository: https://github.com/MR-C0DE/phpaml-cli
Install: https://phpaml.com/download
```

## Reddit / r/PHP

### Title

```text
PHPAML beta.16 — a self-contained PHP platform with declarative UI and APIs
```

### Body

```text
I have published PHPAML CLI 1.7.0-beta.16, an MIT-licensed PHP platform that bundles its own PHP and Composer environment for Windows, macOS and Linux.

The main experiment is whether a complete PHP application workflow can remain explicit while removing the initial environment setup. The same CLI creates classic MVC projects, declarative AML View interfaces and JSON APIs.

    aml create-view-app my-interface
    cd my-interface
    aml doctor
    aml serve

Here is the real first-run workflow in 36 seconds:
https://github.com/MR-C0DE/phpaml-cli/blob/main/docs/assets/demo/phpaml-demo-no-voice.mp4

Repository: https://github.com/MR-C0DE/phpaml-cli
Installers: https://phpaml.com/download

It is still beta. I am looking for technical criticism, particularly around the packaged-runtime trade-off, the first-project experience and whether the component boundaries are understandable.
```

## Show HN

### Title

```text
Show HN: PHPAML – self-contained PHP with declarative UI and APIs
```

### Introduction

```text
PHPAML is an MIT-licensed PHP platform that includes its own PHP and Composer runtimes on Windows, macOS and Linux. The goal is to make the first evaluation start with a running application instead of environment configuration.

The CLI creates classic MVC applications, declarative AML View interfaces and JSON APIs. View describes UI in PHP, Engine handles browser-side state and navigation, and Data provides typed persistence. Each package can also be used independently.

The project is still beta. The current release includes verified native packages, bilingual documentation, cross-browser tests and a 36-second recording of the real first-project workflow.

Repository: https://github.com/MR-C0DE/phpaml-cli
Demo: https://github.com/MR-C0DE/phpaml-cli/blob/main/docs/assets/demo/phpaml-demo-no-voice.mp4

I would value feedback on the packaged-runtime trade-off and whether the platform boundaries are clear to a new visitor.
```

## Annonce française

```text
PHPAML CLI 1.7.0-beta.16 est disponible.

Créez des applications PHP structurées, des interfaces déclaratives et des API sans configurer manuellement PHP ou Composer.

✓ CLI autonome
✓ Windows, macOS et Linux
✓ Licence MIT
✓ Une vraie application en 36 secondes

Code : https://github.com/MR-C0DE/phpaml-cli
Installation : https://phpaml.com/fr/download
```

## Publication order

1. Publish the GitHub Discussion and pin it.
2. Publish LinkedIn and X/Bluesky with the 36-second video.
3. Publish to r/PHP only after answering the first GitHub feedback.
4. Publish Show HN when the maintainer can remain available for replies.
5. Record the adoption report after 24 hours, 7 days, and 30 days.

## Response rules

- State clearly that PHPAML is beta.
- Answer technical criticism with concrete trade-offs, not marketing claims.
- Never count downloads as unique users or successful installations.
- Ask reporters whether `create-view-app`, `doctor`, and `serve` succeeded.
- Turn recurring first-run failures into public issues with reproduction steps.

