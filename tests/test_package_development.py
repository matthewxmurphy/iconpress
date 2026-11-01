"""Focused package privacy, integrity and historical-artifact preservation checks.

Copyright 2026 Matthew C. Murphy, https://MatthewxMurphy.com.
Product/support: https://builtnotbegged.com. SPDX-License-Identifier: GPL-2.0-or-later.
"""
import importlib.util
from pathlib import Path
import shutil
import sys
import tempfile
import unittest
import zipfile

sys.dont_write_bytecode = True
SOURCE = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("iconpress_package", SOURCE / "scripts/package-development.py")
packager = importlib.util.module_from_spec(spec)
spec.loader.exec_module(packager)


class DevelopmentPackageTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="iconpress-package-test-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name) / "source"
        for name in packager.PAYLOAD:
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(SOURCE / name, target)
        self.output = Path(self.temporary.name) / "packages"

    def test_extra_private_files_are_excluded_and_block_relative_asset_is_preserved(self):
        for name in (".env", "backup.zip", "uploads/leads.csv", "admin/private-notes.txt"):
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text("SYNTHETIC PRIVATE DO NOT PACKAGE")
        package = packager.build(self.root, self.output)
        with zipfile.ZipFile(package) as archive:
            self.assertIsNone(archive.testzip())
            self.assertTrue(all(name.startswith("iconpress/") for name in archive.namelist()))
            self.assertEqual(archive.read("iconpress/assets/js/block.js"), (SOURCE / "assets/js/block.js").read_bytes())
            self.assertEqual(archive.read("iconpress/COPYING"), (SOURCE / "COPYING").read_bytes())
            self.assertFalse(any(b"SYNTHETIC PRIVATE" in archive.read(name) for name in archive.namelist()))
            self.assertNotIn("iconpress/scripts/package-development.py", archive.namelist())

    def test_linked_input_is_refused_without_reading_or_changing_outside_file(self):
        outside = Path(self.temporary.name) / "private.txt"
        outside.write_text("SYNTHETIC OUTSIDE")
        target = self.root / "includes/class-iconpress-settings.php"
        target.unlink()
        target.symlink_to(outside)
        with self.assertRaises(packager.PackageError):
            packager.build(self.root, self.output)
        self.assertEqual(outside.read_text(), "SYNTHETIC OUTSIDE")
        self.assertFalse(self.output.exists())

    def test_existing_artifact_is_never_overwritten(self):
        package = packager.build(self.root, self.output)
        before = package.read_bytes()
        with self.assertRaises(FileExistsError):
            packager.build(self.root, self.output)
        self.assertEqual(package.read_bytes(), before)

    def test_mixed_block_version_is_refused_before_writing(self):
        target = self.root / "blocks/icon-wall/block.json"
        target.write_text(target.read_text().replace("2026.10.31.001-dev", "2026.10.2"))
        with self.assertRaises(packager.PackageError):
            packager.build(self.root, self.output)
        self.assertFalse(self.output.exists())

    def test_missing_full_license_is_refused_before_writing(self):
        (self.root / "COPYING").unlink()
        with self.assertRaises(packager.PackageError):
            packager.build(self.root, self.output)
        self.assertFalse(self.output.exists())


if __name__ == "__main__":
    unittest.main()
