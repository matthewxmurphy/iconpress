#!/usr/bin/env python3
"""Build the reviewed IconPress development payload; never collect a whole workspace.

Copyright 2026 Matthew C. Murphy, https://MatthewxMurphy.com.
Product/support: https://builtnotbegged.com. SPDX-License-Identifier: GPL-2.0-or-later.
"""
import argparse
import datetime
import json
import os
from pathlib import Path
import re
import stat
import zipfile


# The original 33 plugin files plus current documentation/license and VERSION.
# Builder/tests, Git metadata, backups, uploads and siblings are never collected.
PAYLOAD = (
    "iconpress.php", "uninstall.php", "readme.txt",
    "admin/class-iconpress-admin.php",
    "includes/class-iconpress-click.php", "includes/class-iconpress-db.php",
    "includes/class-iconpress-render.php", "includes/class-iconpress-settings.php",
    "includes/class-iconpress-store.php", "includes/class-iconpress-tier.php",
    "includes/class-iconpress-widget.php", "includes/iconpress-seed-data.php",
    "blocks/icon-wall/block.json", "assets/css/admin.css", "assets/css/iconpress.css",
    "assets/js/admin.js", "assets/js/block.js", "assets/images/clear.gif",
    "assets/images/defaults/advertise-128.png", "assets/images/defaults/advertise-32.png",
    "assets/images/defaults/blank-16.png", "assets/images/defaults/blank-32.png",
    "assets/images/defaults/blank-48.png", "assets/images/defaults/check-32.png",
    "assets/images/defaults/house-1.png", "assets/images/defaults/house-2.png",
    "assets/images/defaults/house-3.png", "assets/images/defaults/house-4.png",
    "assets/images/defaults/house-5.png", "assets/images/defaults/house-6.png",
    "assets/images/defaults/house-7.png", "assets/images/defaults/house-8.png",
    "assets/images/defaults/house-9.png", "README.md", "CHANGELOG.md", "COPYING", "VERSION",
)


class PackageError(RuntimeError):
    pass


def inputs(root):
    """Admit only regular, unlinked fixed files and coherent development metadata."""
    root = Path(root).absolute()
    if root.is_symlink() or not root.is_dir():
        raise PackageError("Use the regular source checkout.")
    files = {}
    for relative in PAYLOAD:
        path = root
        for part in Path(relative).parts:
            path = path / part
            if path.is_symlink():
                raise PackageError("Linked package input refused: " + relative)
        try:
            info = path.stat()
        except FileNotFoundError as error:
            raise PackageError("Required package input missing: " + relative) from error
        if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
            raise PackageError("Package inputs must be regular, unlinked files: " + relative)
        files[relative] = path.read_bytes()

    version = files["VERSION"].decode("ascii").strip()
    match = re.fullmatch(r"(\d{4})\.([1-9]|1[0-2])\.(\d{2})\.(\d{3})-dev", version)
    if not match or not 1 <= int(match[4]) <= 999:
        raise PackageError("Use a YYYY.M.DD.XXX-dev development identity.")
    try:
        date = datetime.date(*map(int, match.group(1, 2, 3)))
    except ValueError as error:
        raise PackageError("The development identity must contain a real date.") from error
    plugin = files["iconpress.php"].decode("utf-8")
    readme = files["readme.txt"].decode("utf-8")
    block = json.loads(files["blocks/icon-wall/block.json"])
    header = re.search(r"(?m)^ \* Version:\s*(\S+)\s*$", plugin)
    constant = re.search(r"define\( 'ICONPRESS_VERSION', '([^']+)' \);", plugin)
    development = re.search(r"(?m)^Development version:\s*(\S+)\s*$", readme)
    if not header or not constant or not development or any(
        value != version for value in (header[1], constant[1], development[1], block.get("version"))
    ) or not re.search(r"(?m)^Stable tag: trunk\s*$", readme):
        raise PackageError("VERSION, plugin, block and development-readme metadata must agree; Stable tag stays trunk.")
    for name, content in files.items():
        if name != "iconpress.php" and Path(name).suffix in (".php", ".js", ".css"):
            declared = re.search(r"(?m)^ \* Development version:\s*(\S+)\s*$", content.decode("utf-8"))
            if not declared or declared[1] != version:
                raise PackageError("First-party payload header version must agree: " + name)
    return version, date, files


def build(root, output_directory):
    """Write one new ZIP; never replace an existing artifact or modify source."""
    version, date, files = inputs(root)
    directory = Path(output_directory)
    directory.mkdir(parents=True, exist_ok=True)
    destination = directory / ("iconpress-" + version + ".zip")
    descriptor = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    try:
        with os.fdopen(descriptor, "wb") as target:
            with zipfile.ZipFile(target, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
                for name in sorted(files):
                    entry = zipfile.ZipInfo("iconpress/" + name, (date.year, date.month, date.day, 0, 0, 0))
                    entry.create_system = 3
                    entry.external_attr = (stat.S_IFREG | 0o644) << 16
                    entry.compress_type = zipfile.ZIP_DEFLATED
                    archive.writestr(entry, files[name], compresslevel=9)
    except BaseException:
        destination.unlink()
        raise
    return destination


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", type=Path, default=Path(__file__).resolve().parents[1] / "dist")
    arguments = parser.parse_args()
    try:
        package = build(Path(__file__).resolve().parents[1], arguments.output_dir)
    except (PackageError, OSError, ValueError) as error:
        parser.exit(1, str(error) + "\n")
    print("Built development candidate: " + str(package))
