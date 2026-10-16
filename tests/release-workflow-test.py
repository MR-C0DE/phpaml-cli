import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("release", Path(__file__).parents[1] / "scripts/prepare-release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)

class ReleaseIntegrity(unittest.TestCase):
    version = "1.7.0-beta.45"
    tag = "v" + version
    sha = "a" * 40

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
            for name in release.expected_assets(self.version):
                if not name.endswith(".sha256"):
                    (directory / name).write_bytes(b"archive")
                    (directory / (name + ".sha256")).write_text(hashlib.sha256(b"archive").hexdigest() + "  " + name + "\n")
            release.verify_assets(directory, self.version)
            with self.assertRaises(ValueError):
                release.verify_assets(directory, "1.7.0-beta.44")
            archive = directory / "aml-linux-x64.tar.gz"
            archive.write_bytes(b"corrupted")
            with self.assertRaises(ValueError):
                release.verify_assets(directory, self.version)
            archive.write_bytes(b"archive")
            checksum = directory / (archive.name + ".sha256")
            checksum.write_text(hashlib.sha256(b"archive").hexdigest() + "  ../" + archive.name)
            with self.assertRaises(ValueError):
                release.verify_assets(directory, self.version)

if __name__ == "__main__":
    unittest.main()
