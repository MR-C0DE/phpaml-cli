#!/usr/bin/env python3
"""Resolve and verify one explicit CLI release; never select a latest tag."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys

signing_spec = importlib.util.spec_from_file_location("macos_signing", Path(__file__).with_name("macos-signing.py"))
signing = importlib.util.module_from_spec(signing_spec)
signing_spec.loader.exec_module(signing)

PLATFORMS = {
    "linux-x64": ("build-linux-x64.yml", "tar.gz", "deb"),
    "macos-arm64": ("build-macos-arm64.yml", "tar.gz", "pkg"),
    "windows-x64": ("build-windows-x64.yml", "zip", "exe"),
}

def command(*args):
    return subprocess.check_output(args, text=True).strip()

def validate_tag(tag, version):
    if not re.fullmatch(r"v[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9]+(?:[.-][A-Za-z0-9]+)*)?", tag):
        raise ValueError("An explicit version tag is required.")
    if tag != "v" + version:
        raise ValueError("The tag does not match phpaml.json at its commit.")

def select_runs(runs, tag, sha):
    selected = {}
    for platform, (workflow, _, _) in PLATFORMS.items():
        matches = [run for run in runs if (
            run.get("event") == "push" and run.get("head_branch") == tag
            and run.get("head_sha") == sha
            and run.get("path") == ".github/workflows/" + workflow
        )]
        if not matches:
            raise ValueError(f"No tagged build for {platform} at {sha}.")
        latest = max(matches, key=lambda run: run["id"])
        if latest.get("status") != "completed" or latest.get("conclusion") != "success":
            raise ValueError(f"The latest tagged build for {platform} has not succeeded.")
        selected[platform] = latest["id"]
    return selected

def expected_assets(version):
    archives = set()
    for platform, (_, portable, installer) in PLATFORMS.items():
        archives.update((f"aml-{platform}.{portable}", f"phpaml-{version}-{platform}.{installer}"))
    return archives | {name + ".sha256" for name in archives} | {signing.REPORT}

def verify_assets(directory, version, commit):
    expected = expected_assets(version)
    actual = {path.name for path in directory.iterdir()}
    if actual != expected or any(not path.is_file() or path.is_symlink() for path in directory.iterdir()):
        raise ValueError(f"Unexpected release assets: missing={expected - actual}, extra={actual - expected}")
    for name in sorted(expected):
        if not name.endswith(".sha256"):
            continue
        archive = name.removesuffix(".sha256")
        match = re.fullmatch(r"([a-fA-F0-9]{64})\s+\*?([^\r\n]+)\s*", (directory / name).read_text().strip())
        if not match or match[2] != archive:
            raise ValueError(f"Invalid checksum manifest: {name}")
        digest = hashlib.sha256((directory / archive).read_bytes()).hexdigest()
        if digest != match[1].lower():
            raise ValueError(f"Checksum mismatch: {archive}")
    signing.validate_report(directory, version, commit)

def main():
    if len(sys.argv) != 2:
        raise ValueError("Usage: prepare-release.py <explicit-version-tag>")
    tag = sys.argv[1]
    validate_tag(tag, tag.removeprefix("v"))
    sha = command("git", "rev-parse", "--verify", "refs/tags/" + tag + "^{commit}")
    metadata = json.loads(command("git", "show", sha + ":phpaml.json"))
    version = metadata["version"]
    validate_tag(tag, version)
    repository = os.environ["GITHUB_REPOSITORY"]
    pages = json.loads(command("gh", "api", "--paginate", "--slurp",
        f"repos/{repository}/actions/runs?event=push&branch={tag}&per_page=100"))
    runs = select_runs([run for page in pages for run in page["workflow_runs"]], tag, sha)
    directory = Path("release-assets")
    directory.mkdir()  # Refuse stale files from an earlier preparation.
    for platform, run_id in runs.items():
        command("gh", "run", "download", str(run_id), "--repo", repository,
                "--name", "aml-" + platform, "--dir", str(directory))
    verify_assets(directory, version, sha)
    changelog = command("git", "show", sha + ":CHANGELOG.md")
    section = re.search(r"^## " + re.escape(version) + r" — ([^\n]+)\n(.*?)(?=^## |\Z)",
                        changelog, re.MULTILINE | re.DOTALL)
    if not section or section[1] == "Unreleased":
        raise ValueError("Dated release notes are required at the tagged commit.")
    Path("RELEASE-NOTES.md").write_text(section[2].strip() + "\n")
    Path("release-provenance.json").write_text(json.dumps({
        "tag": tag, "commit": sha, "builds": runs,
        "macosSigning": signing.validate_report(directory, version, sha),
        "assets": {name: hashlib.sha256((directory / name).read_bytes()).hexdigest()
                   for name in sorted(expected_assets(version))},
    }, indent=2) + "\n")
    with open(os.environ["GITHUB_OUTPUT"], "a") as output:
        output.write(f"tag={tag}\nversion={version}\nsha={sha}\nprerelease={'true' if '-' in version else 'false'}\n")
    print(f"Verified {tag} at {sha}: three successful tagged builds and six archives.")

if __name__ == "__main__":
    try:
        main()
    except (ValueError, KeyError, OSError, subprocess.CalledProcessError) as error:
        sys.exit(str(error))
