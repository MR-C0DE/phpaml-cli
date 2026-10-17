#!/usr/bin/env python3
"""Sign on an isolated macOS runner; independently verify before publication."""
import argparse
import base64
from contextlib import contextmanager
import hashlib
import json
import os
from pathlib import Path
import plistlib
import re
import secrets
import shlex
import shutil
import subprocess
import sys
import tarfile
import tempfile
import uuid
import urllib.request

TEAM = "D5798DRJ9K"
APPLICATION = f"Developer ID Application: Andre Mulaja ({TEAM})"
INSTALLER = f"Developer ID Installer: Andre Mulaja ({TEAM})"
REPORT = "macos-signing.json"
CHECKS = ("codeSignatures", "hardenedRuntime", "entitlements", "installerSignature",
          "stapledTicket", "gatekeeper", "matchingPayloads", "taggedSources")
SECRET_NAMES = ("MACOS_APPLICATION_P12_BASE64", "MACOS_INSTALLER_P12_BASE64",
                "MACOS_P12_PASSWORD", "APPLE_ID", "APPLE_APP_SPECIFIC_PASSWORD")
REDACT = []


def run(*args):
    result = subprocess.run([str(arg) for arg in args], capture_output=True)
    if result.returncode:
        message = result.stderr.decode(errors="replace") + result.stdout.decode(errors="replace")
        for secret in REDACT:
            if secret:
                message = message.replace(secret, "[redacted]")
        raise ValueError(f"{Path(args[0]).name} failed ({result.returncode}): {message}")
    return result


def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def names(version):
    if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9]+(?:[.-][A-Za-z0-9]+)*)?", version):
        raise ValueError("Invalid version.")
    return ("aml-macos-arm64.tar.gz", f"phpaml-{version}-macos-arm64.pkg")


def validate_report(directory, version, commit):
    report = json.loads((directory / REPORT).read_text())
    if (report.get("schemaVersion"), report.get("version"), report.get("commit"), report.get("teamId")) != (1, version, commit, TEAM):
        raise ValueError("Signing evidence does not match this version, commit or Apple team.")
    notary = report.get("notarization", {})
    if notary.get("status") != "Accepted":
        raise ValueError("Apple has not accepted this distribution.")
    uuid.UUID(notary.get("id", ""))
    if report.get("checks") != dict.fromkeys(CHECKS, True):
        raise ValueError("Incomplete macOS verification evidence.")
    if report.get("assets") != {name: sha256(directory / name) for name in names(version)}:
        raise ValueError("Signing evidence refers to different macOS artifacts.")
    return report


def extract_portable(archive, target):
    # Release archives contain ordinary files/directories only. Never extract links.
    with tarfile.open(archive, "r:gz") as source:
        members = source.getmembers()
        seen = set()
        for member in members:
            path = Path(member.name)
            if (path.is_absolute() or ".." in path.parts or not path.parts
                    or path.parts[0] != "aml-macos-arm64" or member.name in seen
                    or not (member.isfile() or member.isdir())):
                raise ValueError("Unsafe or duplicate path in the macOS archive.")
            seen.add(member.name)
        for member in members:
            destination = target / member.name
            if member.isdir():
                destination.mkdir(parents=True, exist_ok=True)
            else:
                destination.parent.mkdir(parents=True, exist_ok=True)
                with source.extractfile(member) as stream, destination.open("wb") as output:
                    shutil.copyfileobj(stream, output)
                destination.chmod(member.mode & 0o755)


def tree_files(root):
    result = {}
    for path in root.rglob("*"):
        if path.is_symlink() or not (path.is_file() or path.is_dir()):
            raise ValueError("Unexpected link or special file in the package payload.")
        if path.is_file():
            result[path.relative_to(root).as_posix()] = sha256(path)
    return result


def verify_binary(path, identifier, jit):
    run("codesign", "--verify", "--strict", path)
    result = run("codesign", "-d", "--verbose=4", path)
    details = result.stderr.decode()
    for required in (f"Identifier={identifier}\n", f"TeamIdentifier={TEAM}\n",
                     f"Authority={APPLICATION}\n", "Timestamp="):
        if required not in details:
            raise ValueError(f"Unexpected Developer ID signature: {path.name}")
    if not re.search(r"flags=0x[0-9a-f]+\([^\n)]*runtime[^\n)]*\)", details):
        raise ValueError(f"Hardened runtime is missing: {path.name}")
    raw = run("codesign", "-d", "--entitlements", ":-", path).stdout
    entitlements = plistlib.loads(raw) if raw.strip() else {}
    expected = {"com.apple.security.cs.allow-jit": True} if jit else {}
    if entitlements != expected:
        raise ValueError(f"Unexpected entitlements: {path.name}")


def verify(directory, version, commit):
    portable, installer = names(version)
    if not re.fullmatch(r"[a-f0-9]{40}", commit):
        raise ValueError("An exact source commit is required.")
    package = directory / installer
    signature = run("pkgutil", "--check-signature", package).stdout.decode()
    if INSTALLER not in signature or "signed by a developer certificate issued by Apple" not in signature:
        raise ValueError("Installer is not signed by the expected Developer ID.")
    run("xcrun", "stapler", "validate", package)
    assessment = run("spctl", "--assess", "--type", "install", "--verbose=4", package)
    if b"source=Notarized Developer ID" not in assessment.stderr:
        raise ValueError("Gatekeeper did not recognize notarization.")
    with tempfile.TemporaryDirectory(prefix="aml-verify-") as temporary:
        temporary = Path(temporary)
        extract_portable(directory / portable, temporary)
        archive_root = temporary / "aml-macos-arm64"
        expanded = temporary / "installer"
        run("pkgutil", "--expand-full", package, expanded)
        installed_root = expanded / "Payload/usr/local/lib/aml"
        link = expanded / "Payload/usr/local/bin/aml"
        if not link.is_symlink() or os.readlink(link) != "../lib/aml/bin/aml":
            raise ValueError("The installed AML command has an unexpected target.")
        # The installer must not run scripts or install files outside its declared payload.
        import xml.etree.ElementTree as ET
        info = ET.parse(expanded / "PackageInfo").getroot()
        if (info.get("identifier"), info.get("version"), info.get("install-location")) != ("com.phpaml.cli", version, "/") or info.find("scripts") is not None:
            raise ValueError("Unexpected installer metadata or scripts.")
        for path in (expanded / "Payload").rglob("*"):
            if (path.is_symlink() or not path.is_dir()) and path != link and installed_root not in path.parents:
                raise ValueError("Unexpected file outside the AML installation.")
        if tree_files(archive_root) != tree_files(installed_root):
            raise ValueError("Installer and portable archive contain different files.")
        for root in (archive_root, installed_root):
            verify_binary(root / "bin/aml", "com.phpaml.cli", False)
            verify_binary(root / "runtime/php/bin/php", "com.phpaml.php", True)
        sources = run("git", "ls-tree", "-r", "--name-only", commit, "cli/").stdout.decode().splitlines()
        expected_files = {"bin/aml", "runtime/php/bin/php", "runtime/composer/composer.phar"}
        for source in ["phpaml.json"] + [p for p in sources if p.endswith(".php")]:
            target = source if source == "phpaml.json" else "runtime/bin/" + Path(source).name
            expected_files.add(target)
            if (archive_root / target).read_bytes() != run("git", "show", f"{commit}:{source}").stdout:
                raise ValueError(f"Installed source differs from {commit}: {source}")
        if set(tree_files(archive_root)) != expected_files:
            raise ValueError("Unexpected files in the AML distribution.")
        if json.loads((archive_root / "phpaml.json").read_text())["version"] != version:
            raise ValueError("Installed version mismatch.")
    return dict.fromkeys(CHECKS, True)


@contextmanager
def signing_credentials(args):
    if args.keychain and args.notary_profile:
        yield args.keychain, ["--keychain-profile", args.notary_profile, "--keychain", args.keychain]
        return
    if args.keychain or args.notary_profile:
        raise ValueError("Local signing requires both --keychain and --notary-profile.")
    values = {name: os.environ.pop(name, "") for name in SECRET_NAMES}
    missing = [name for name, value in values.items() if not value]
    if missing:
        raise ValueError("Missing GitHub signing secrets: " + ", ".join(missing))
    REDACT.extend(values.values())
    with tempfile.TemporaryDirectory(prefix="aml-signing-", dir=os.environ.get("RUNNER_TEMP")) as temporary:
        temporary = Path(temporary)
        keychain = temporary / "signing.keychain-db"
        password = secrets.token_urlsafe(32)
        REDACT.append(password)
        original = shlex.split(run("security", "list-keychains", "-d", "user").stdout.decode())
        try:
            run("security", "create-keychain", "-p", password, keychain)
            run("security", "set-keychain-settings", "-lut", "3600", keychain)
            run("security", "unlock-keychain", "-p", password, keychain)
            # Fresh runners may not have Apple's Developer ID G2 intermediate cached.
            intermediate = temporary / "DeveloperIDG2CA.cer"
            with urllib.request.urlopen("https://www.apple.com/certificateauthority/DeveloperIDG2CA.cer", timeout=30) as response:
                intermediate.write_bytes(response.read())
            if sha256(intermediate) != "f16cd3c54c7f83cea4bf1a3e6a0819c8aaa8e4a1528fd144715f350643d2df3a":
                raise ValueError("Apple intermediate certificate checksum mismatch.")
            run("security", "import", intermediate, "-k", keychain)
            for kind in ("APPLICATION", "INSTALLER"):
                certificate = temporary / (kind + ".p12")
                certificate.write_bytes(base64.b64decode(values[f"MACOS_{kind}_P12_BASE64"], validate=True))
                certificate.chmod(0o600)
                run("security", "import", certificate, "-P", values["MACOS_P12_PASSWORD"],
                    "-k", keychain, "-T", "/usr/bin/codesign", "-T", "/usr/bin/pkgbuild")
                certificate.unlink()
            run("security", "list-keychains", "-d", "user", "-s", keychain, *original)
            run("security", "set-key-partition-list", "-S", "apple-tool:,apple:", "-k", password, keychain)
            yield str(keychain), ["--apple-id", values["APPLE_ID"], "--team-id", TEAM,
                                 "--password", values["APPLE_APP_SPECIFIC_PASSWORD"]]
        finally:
            try:
                run("security", "list-keychains", "-d", "user", "-s", *original)
            finally:
                if keychain.exists():
                    run("security", "delete-keychain", keychain)


def sign(args):
    directory = args.directory
    report_path = directory / REPORT
    report_path.unlink(missing_ok=True)  # Never leave prior successful evidence after a failed attempt.
    root = directory / "aml-macos-arm64"
    portable, installer = names(args.version)
    if not re.fullmatch(r"[a-f0-9]{40}", args.commit):
        raise ValueError("An exact source commit is required.")
    print("Signing AML and PHP with Developer ID…", flush=True)
    with signing_credentials(args) as (keychain, authentication):
        for relative, identifier, jit in (("bin/aml", "com.phpaml.cli", False),
                                          ("runtime/php/bin/php", "com.phpaml.php", True)):
            extra = ["--entitlements", str(Path(__file__).with_name("macos-php-entitlements.plist"))] if jit else []
            run("codesign", "--force", "--sign", APPLICATION, "--keychain", keychain,
                "--timestamp", "--options", "runtime", "--identifier", identifier, *extra, root / relative)
        with tempfile.TemporaryDirectory(prefix="aml-pkg-") as staging:
            staging = Path(staging)
            shutil.copytree(root, staging / "usr/local/lib/aml")
            (staging / "usr/local/bin").mkdir(parents=True)
            (staging / "usr/local/bin/aml").symlink_to("../lib/aml/bin/aml")
            run("pkgbuild", "--root", staging, "--identifier", "com.phpaml.cli", "--version", args.version,
                "--install-location", "/", "--sign", INSTALLER, "--keychain", keychain,
                "--timestamp", directory / installer)
        print("Submitting signed installer to Apple…", flush=True)
        response = run("xcrun", "notarytool", "submit", directory / installer, *authentication,
                       "--wait", "--timeout", "20m", "--output-format", "json")
        notary = json.loads(response.stdout)
        if notary.get("id") or notary.get("status") != "Accepted":
            raise ValueError("Apple did not accept the installer: " + json.dumps(notary))
        run("xcrun", "stapler", "staple", directory / installer)
    with tarfile.open(directory / portable, "w:gz") as archive:
        archive.add(root, arcname="aml-macos-arm64")
    checks = verify(directory, args.version, args.commit)
    for name in (portable, installer):
        (directory / (name + ".sha256")).write_text(sha256(directory / name) + "  " + name + "\n")
    report_path.write_text(json.dumps({
        "schemaVersion": 1, "version": args.version, "commit": args.commit, "teamId": TEAM,
        "notarization": {"id": notary["id"], "status": "Accepted"}, "checks": checks,
        "assets": {name: sha256(directory / name) for name in (portable, installer)},
    }, indent=2) + "\n")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=("sign", "verify"))
    parser.add_argument("--directory", type=Path, required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--commit", required=True)
    parser.add_argument("--keychain")
    parser.add_argument("--notary-profile")
    args = parser.parse_args()
    if sys.platform != "darwin":
        raise ValueError("macOS is required to verify Apple distribution signatures.")
    args.directory = args.directory.resolve()
    if args.action == "sign":
        sign(args)
    else:
        validate_report(args.directory, args.version, args.commit)
        verify(args.directory, args.version, args.commit)
    print(f"macOS {args.version}: signed, notarized, stapled and verified.")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, KeyError) as error:
        sys.exit(str(error))
