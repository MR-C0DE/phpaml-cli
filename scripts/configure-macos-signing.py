#!/usr/bin/env python3
"""One-time interactive setup of AML CLI's GitHub Actions signing secrets."""
import argparse
import base64
import getpass
import os
from pathlib import Path
import secrets
import subprocess
import sys
import tempfile

REPOSITORY = "MR-C0DE/phpaml-cli"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--confirm-upload", action="store_true",
                        help="Authorize storing both private Developer ID identities and notarization credentials in this repository's Actions secrets.")
    args = parser.parse_args()
    if not args.confirm_upload or sys.platform != "darwin" or not sys.stdin.isatty():
        raise ValueError("Run on your Mac in an interactive terminal with --confirm-upload after reviewing docs/macos-signing.md.")
    subprocess.run(["gh", "auth", "status"], check=True, stdout=subprocess.DEVNULL)
    apple_id = input("Apple ID used for PHPAML notarization: ").strip()
    password = getpass.getpass("Apple app-specific password (hidden): ").strip()
    if not apple_id or not password:
        raise ValueError("Both notarization credentials are required.")
    p12_password = secrets.token_urlsafe(40)
    with tempfile.TemporaryDirectory(prefix="phpaml-signing-setup-") as temporary:
        temporary = Path(temporary)
        helper = temporary / "export-identities"
        subprocess.run(["xcrun", "swiftc", "-module-cache-path", str(temporary / "modules"),
                        str(Path(__file__).with_name("export-macos-signing-identities.swift")),
                        "-o", str(helper)], check=True)
        print("Approve macOS Keychain access if requested. Only PHPAML's two identities are exported.")
        subprocess.run([str(helper), str(Path.home() / "Library/Keychains/login.keychain-db"), str(temporary)],
                       input=p12_password + "\n", text=True, check=True)
        # Validate the app-specific password against Apple before changing GitHub secrets.
        validation = subprocess.run(["xcrun", "notarytool", "history", "--apple-id", apple_id,
                                     "--team-id", "D5798DRJ9K", "--password", password,
                                     "--output-format", "json"], capture_output=True)
        if validation.returncode:
            raise ValueError("Apple did not accept these notarization credentials. No GitHub secrets were changed.")
        values = {"MACOS_APPLICATION_P12_BASE64": base64.b64encode((temporary / "APPLICATION.p12").read_bytes()).decode(),
                  "MACOS_INSTALLER_P12_BASE64": base64.b64encode((temporary / "INSTALLER.p12").read_bytes()).decode(),
                  "MACOS_P12_PASSWORD": p12_password, "APPLE_ID": apple_id,
                  "APPLE_APP_SPECIFIC_PASSWORD": password}
        for name, value in values.items():
            # gh encrypts the input for the repository; secrets never enter argv or logs.
            result = subprocess.run(["gh", "secret", "set", name, "--repo", REPOSITORY],
                                    input=value, text=True, capture_output=True)
            if result.returncode:
                raise ValueError(f"Could not configure {name}. Rerun setup to replace all five secrets consistently.")
            print(name + " configured.")
    print("GitHub signing configured; temporary encrypted exports removed.")
    print("Validate with: gh workflow run build-macos-arm64.yml --repo " + REPOSITORY + " --ref main")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        sys.exit(str(error))
