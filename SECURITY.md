# Security policy

PHPAML is pre-stable. Report vulnerabilities privately with a GitHub Security Advisory in the affected PHPAML repository. Do not disclose exploit details, credentials, or personal data in a public issue.

The latest CLI release and the current `main` branches are supported. Older pre-releases are unsupported. We aim to acknowledge critical reports within 72 hours, provide an initial assessment within 7 days, and release a fix as soon as coordinated disclosure permits.

Include the affected version and platform, impact, reproduction steps, and a minimal proof of concept. Only test systems and data you own or are authorized to test.

Generators and project creation reject ambiguous relative paths and symbolic
links below the selected project root. Generators create new leaf files
exclusively and do not overwrite existing files. These checks do not make it
safe to run an untrusted project's PHP, Composer scripts or installation code;
they also do not provide isolation from another process concurrently changing
the project's parent directories.
