import hashlib
from contextlib import redirect_stdout
import importlib.util
import io
import json
from pathlib import Path
import tarfile
from types import SimpleNamespace
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location("release", Path(__file__).parents[1] / "scripts/prepare-release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)

class ReleaseIntegrity(unittest.TestCase):
    version = "1.7.0-beta.45"
    tag = "v" + version
    sha = "a" * 40

    def assets(self, directory):
        for name in release.expected_assets(self.version):
            if not name.endswith(".sha256") and name != release.signing.REPORT:
                (directory / name).write_bytes(b"archive")
                (directory / (name + ".sha256")).write_text(hashlib.sha256(b"archive").hexdigest() + "  " + name + "\n")
        report = {"schemaVersion": 1, "version": self.version, "commit": self.sha,
                  "teamId": release.signing.TEAM,
                  "notarization": {"id": "4c9ee527-a95b-4947-86b7-7176ddf1556f", "status": "Accepted"},
                  "checks": dict.fromkeys(release.signing.CHECKS, True),
                  "assets": {name: release.signing.sha256(directory / name) for name in release.signing.names(self.version)}}
        (directory / release.signing.REPORT).write_text(json.dumps(report))
        return report

    def runs(self):
        return [{"id": index, "event": "push", "head_branch": self.tag,
                 "head_sha": self.sha, "path": ".github/workflows/" + values[0],
                 "status": "completed", "conclusion": "success"}
                for index, values in enumerate(release.PLATFORMS.values(), 1)]

    def test_requires_explicit_matching_version(self):
        release.validate_tag(self.tag, self.version)
        for tag in ["main", "latest", "--help", "v1.7.0-beta.44", "v1.7.0\n"]:
            with self.subTest(tag=tag), self.assertRaises(ValueError):
                release.validate_tag(tag, self.version)

    def test_requires_three_tagged_builds_at_exact_commit(self):
        self.assertEqual(len(release.select_runs(self.runs(), self.tag, self.sha)), 3)
        for key, value in [("event", "workflow_dispatch"), ("head_branch", "main"),
                           ("head_sha", "b" * 40), ("path", ".github/workflows/other.yml")]:
            runs = self.runs()
            runs[0][key] = value
            with self.subTest(key=key), self.assertRaises(ValueError):
                release.select_runs(runs, self.tag, self.sha)

    def test_does_not_fall_back_to_older_success(self):
        for status, conclusion in [("completed", "failure"), ("in_progress", None)]:
            runs = self.runs()
            runs.append({**runs[0], "id": 100, "status": status, "conclusion": conclusion})
            with self.assertRaises(ValueError):
                release.select_runs(runs, self.tag, self.sha)

    def test_checks_every_archive_and_rejects_wrong_version_or_manifest_path(self):
        with tempfile.TemporaryDirectory() as temporary:
            directory = Path(temporary)
            self.assets(directory)
            release.verify_assets(directory, self.version, self.sha)
            with self.assertRaises(ValueError):
                release.verify_assets(directory, "1.7.0-beta.44", self.sha)
            archive = directory / "aml-linux-x64.tar.gz"
            archive.write_bytes(b"corrupted")
            with self.assertRaises(ValueError):
                release.verify_assets(directory, self.version, self.sha)
            archive.write_bytes(b"archive")
            checksum = directory / (archive.name + ".sha256")
            checksum.write_text(hashlib.sha256(b"archive").hexdigest() + "  ../" + archive.name)
            with self.assertRaises(ValueError):
                release.verify_assets(directory, self.version, self.sha)

    def test_refuses_unsigned_or_stale_signing_evidence(self):
        with tempfile.TemporaryDirectory() as temporary:
            directory = Path(temporary)
            report = self.assets(directory)
            evidence = directory / release.signing.REPORT
            evidence.unlink()
            with self.assertRaises(ValueError):
                release.verify_assets(directory, self.version, self.sha)
            for key, value in [("version", "1.7.0"), ("commit", "b" * 40), ("teamId", "OTHERTEAM1"),
                               ("checks", {}), ("assets", {}),
                               ("notarization", {"id": report["notarization"]["id"], "status": "In Progress"}),
                               ("notarization", {"id": "not-an-id", "status": "Accepted"})]:
                evidence.write_text(json.dumps({**report, key: value}))
                with self.subTest(key=key, value=value), self.assertRaises(ValueError):
                    release.verify_assets(directory, self.version, self.sha)
            evidence.write_text(json.dumps(report))
            package = directory / release.signing.names(self.version)[1]
            package.write_bytes(b"changed after notarization")
            (directory / (package.name + ".sha256")).write_text(release.signing.sha256(package) + "  " + package.name)
            with self.assertRaises(ValueError):
                release.verify_assets(directory, self.version, self.sha)

    def test_rejects_unsafe_archive_members_before_extraction(self):
        for name, kind in [("../escape", tarfile.REGTYPE), ("/absolute", tarfile.REGTYPE),
                           ("aml-macos-arm64/link", tarfile.SYMTYPE),
                           ("aml-macos-arm64/link", tarfile.LNKTYPE)]:
            with self.subTest(name=name, kind=kind), tempfile.TemporaryDirectory() as temporary:
                temporary = Path(temporary)
                archive = temporary / "input.tar.gz"
                with tarfile.open(archive, "w:gz") as output:
                    member = tarfile.TarInfo(name)
                    member.type = kind
                    member.linkname = "../../escape"
                    output.addfile(member, io.BytesIO(b""))
                with self.assertRaises(ValueError):
                    release.signing.extract_portable(archive, temporary / "output")
                self.assertFalse((temporary / "output").exists())

    def test_beta_publication_explicitly_preserves_latest_stable(self):
        workflow = (Path(__file__).parents[1] / ".github/workflows/publish-release.yml").read_text()
        self.assertIn('if [[ "$PRERELEASE" == true ]]; then args+=(--prerelease --latest=false); fi', workflow)
        self.assertLess(workflow.index("macos-signing.py verify"), workflow.index('gh release create'))
        release.validate_tag("v1.7.0", "1.7.0")
        release.validate_tag("v1.7.0-beta.46", "1.7.0-beta.46")

    def test_rejects_incorrect_native_signatures_and_entitlements(self):
        details = (f"Identifier=com.phpaml.cli\nTeamIdentifier={release.signing.TEAM}\n"
                   f"Authority={release.signing.APPLICATION}\nTimestamp=Oct 17 2026\n"
                   "CodeDirectory flags=0x10000(runtime)\n")
        for invalid in [details.replace(release.signing.TEAM, "OTHERTEAM1"),
                        details.replace("Developer ID Application", "Apple Development"),
                        details.replace("Timestamp=", "Signed Time="),
                        details.replace("(runtime)", "(adhoc)")]:
            with self.subTest(details=invalid), patch.object(release.signing, "run", side_effect=[
                SimpleNamespace(stdout=b"", stderr=b""),
                SimpleNamespace(stdout=b"", stderr=invalid.encode())
            ]), self.assertRaises(ValueError):
                release.signing.verify_binary(Path("aml"), "com.phpaml.cli", False)
        dangerous = release.signing.plistlib.dumps({"com.apple.security.cs.disable-library-validation": True})
        with patch.object(release.signing, "run", side_effect=[
            SimpleNamespace(stdout=b"", stderr=b""),
            SimpleNamespace(stdout=b"", stderr=details.encode()),
            SimpleNamespace(stdout=dangerous, stderr=b"")
        ]), self.assertRaises(ValueError):
            release.signing.verify_binary(Path("aml"), "com.phpaml.cli", False)

    def test_missing_credentials_never_fall_back_to_unsigned_signing(self):
        args = SimpleNamespace(keychain=None, notary_profile=None)
        with patch.dict(release.signing.os.environ, {}, clear=True), patch.object(release.signing, "run") as command:
            with self.assertRaisesRegex(ValueError, "Missing GitHub signing secrets"):
                with release.signing.signing_credentials(args):
                    self.fail("Signing must not continue without credentials")
            command.assert_not_called()

    def test_accepted_notarization_finishes_distribution_and_rejected_results_stop(self):
        accepted = {"id": "d32d75f2-5521-4710-ab44-bf04e4a54e0e", "status": "Accepted"}
        for response in [accepted, {"status": "Accepted"},
                         {**accepted, "status": "Invalid"},
                         {**accepted, "status": "In Progress"}]:
            with self.subTest(response=response), tempfile.TemporaryDirectory() as temporary:
                directory = Path(temporary)
                root = directory / "aml-macos-arm64"
                root.mkdir()
                (root / "fixture").write_bytes(b"payload")
                evidence = directory / release.signing.REPORT
                evidence.write_text("old successful report")
                args = SimpleNamespace(directory=directory, version=self.version, commit=self.sha,
                                       keychain="test.keychain", notary_profile="test-profile")

                def native_command(*arguments):
                    if arguments[0] == "pkgbuild":
                        Path(arguments[-1]).write_bytes(b"signed installer")
                    output = json.dumps(response).encode() if arguments[:3] == ("xcrun", "notarytool", "submit") else b""
                    return SimpleNamespace(stdout=output, stderr=b"")

                with patch.object(release.signing, "run", side_effect=native_command) as command, \
                     patch.object(release.signing, "verify", return_value=dict.fromkeys(release.signing.CHECKS, True)) as verify, \
                     redirect_stdout(io.StringIO()):
                    if response == accepted:
                        release.signing.sign(args)
                        report = release.signing.validate_report(directory, self.version, self.sha)
                        self.assertEqual(report["notarization"], accepted)
                        verify.assert_called_once()
                        command.assert_any_call("xcrun", "stapler", "staple", directory / release.signing.names(self.version)[1])
                        for name in release.signing.names(self.version):
                            self.assertTrue((directory / (name + ".sha256")).is_file())
                    else:
                        with self.assertRaisesRegex(ValueError, "Apple did not accept"):
                            release.signing.sign(args)
                        verify.assert_not_called()
                        self.assertFalse(evidence.exists())
                        self.assertFalse(any(call.args[:2] == ("xcrun", "stapler") for call in command.call_args_list))

if __name__ == "__main__":
    unittest.main()
