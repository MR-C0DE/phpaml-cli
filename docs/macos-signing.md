# Automated macOS signing and notarization

The macOS build workflow signs AML and its bundled PHP, signs the installer,
submits it to Apple and requires an `Accepted` result. It attaches the ticket,
verifies Gatekeeper acceptance, compares the installer with the portable archive
and checks the PHP sources against the exact build commit. SHA-256 manifests
are generated only after signing and stapling. Native installation and console
tests then run on the signed distribution before artifacts are uploaded.

`Publish CLI release` runs on macOS and repeats the Apple signature, entitlement,
ticket, Gatekeeper and payload checks on the downloaded artifacts. It also
requires `macos-signing.json` to match their digests, version and source commit.
Missing secrets, expired certificates, a pending/rejected Apple submission,
unsigned artifacts or a verification failure stop publication. There is no
unsigned fallback, including for beta releases.

## One-time configuration

The repository `MR-C0DE/phpaml-cli` requires these Actions secrets:

| Secret | Content |
| --- | --- |
| `MACOS_APPLICATION_P12_BASE64` | Encrypted Developer ID Application identity, including its private key, encoded as Base64 |
| `MACOS_INSTALLER_P12_BASE64` | Encrypted Developer ID Installer identity, including its private key, encoded as Base64 |
| `MACOS_P12_PASSWORD` | Password protecting both exports |
| `APPLE_ID` | Apple account used for notarization |
| `APPLE_APP_SPECIFIC_PASSWORD` | App-specific password for notarization |

The expected Apple team is `D5798DRJ9K`. Certificate names are pinned in
`scripts/macos-signing.py`. The only extra entitlement is
`com.apple.security.cs.allow-jit`, exclusively on bundled PHP.

After authorizing storage of these credentials in GitHub Actions, run the
following on the Mac containing the two private identities and an authenticated
GitHub CLI. Enter the app-specific password only in the hidden terminal prompt.

```bash
python3 scripts/configure-macos-signing.py --confirm-upload
```

The helper exports only PHPAML's two identities from the login Keychain, using
a random encryption password. macOS may require permission to export each key.
It validates the notarization credentials with Apple, sends secrets to GitHub
through stdin, and removes its temporary exports. No secret is committed, added
to release artifacts or printed. Anyone able to modify a trusted repository
workflow could use these signing secrets: keep repository write access restricted.
Renew the certificates and replace the secrets before they expire. Rerunning the
helper replaces all five values; do not start a signing build during replacement.

The hosted runner imports the identities into a temporary keychain only during
signing. Cleanup restores its prior keychain search list and deletes the temporary
keychain, including after a command fails. GitHub also destroys hosted runners.
Secrets are passed only to configuration/signing steps, not package compilation.

## Validation and publication

```bash
gh workflow run build-macos-arm64.yml --repo MR-C0DE/phpaml-cli --ref main
```

This signs and notarizes a test build without creating a GitHub release. For a
release, update the version and dated changelog, then push its exact `v*` tag.
All three platform builds must succeed on that tag and commit. Dispatch
`publish-release.yml` from `main` with that tag after reviewing the results.

Beta/prerelease versions explicitly use `--prerelease --latest=false`; they do
not change the stable `/releases/latest` endpoint. Stable versions retain the
existing normal GitHub release behavior. `aml update` continues to select the
stable endpoint; beta users specify a version. Existing releases are never
overwritten by the publication workflow. Previously built unsigned artifacts
cannot pass the new publication checks.

Local signing can reuse the established Keychain profile without exporting keys:

```bash
python3 scripts/macos-signing.py sign --directory dist --version <version> \
  --commit <full-source-commit> --keychain "$HOME/Library/Keychains/login.keychain-db" \
  --notary-profile PHPAML-notary
```

The directory must already contain `aml-macos-arm64/` built from that commit.
Local checks: `python3 tests/release-workflow-test.py`. The same publication
verifier can be run with `verify` and without either Keychain option.

References: [GitHub certificate setup](https://docs.github.com/en/actions/how-tos/deploy/deploy-to-third-party-platforms/sign-xcode-applications),
[Apple notarization workflow](https://developer.apple.com/documentation/security/customizing-the-notarization-workflow).
