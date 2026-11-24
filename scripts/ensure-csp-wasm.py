#!/usr/bin/env python3
"""Ensure Nginx Content-Security-Policy headers allow WebAssembly compilation.

Chromium 155+ removed native XSLTProcessor, so Flarum loads its vendored
WebAssembly XSLT polyfill (`xslt-polyfill.min.js`). Without `'wasm-unsafe-eval'`
in `script-src`, browsers reject `WebAssembly.Module()` with a CSP
CompileError and every `s9e.TextFormatter.preview()` call fails.

Usage:
    ensure-csp-wasm.py [--backup-dir <dir>] <conf-path> [<conf-path> ...]
    ensure-csp-wasm.py --restore <backup-dir>
"""
import argparse
import glob
import json
import os
import re
import shutil
import sys

INCLUDE_RE = re.compile(r'^[ \t]*include[ \t]+["\']?([^"\';\s]+)["\']?[ \t]*;', re.MULTILINE)
CSP_HEADER_RE = re.compile(
    r'(?im)^([ \t]*add_header[ \t]+Content-Security-Policy(?:-Report-Only)?[ \t]+")([^"]*)(")'
)
SCRIPT_SRC_RE = re.compile(r'(?i)(?<![\w-])(script-src)(?=[ \t;]|$)([^;]*)')
LEADING_SELF_RE = re.compile(r"(?i)^([ \t]*'self')")


def discover_configs(roots):
    """Return deduplicated real paths of existing config files and their includes."""
    discovered = []
    seen = set()
    queue = list(roots)

    while queue:
        raw_path = queue.pop(0)
        if not os.path.isfile(raw_path):
            continue
        real = os.path.realpath(raw_path)
        if real in seen:
            continue
        seen.add(real)
        discovered.append(real)

        try:
            with open(real, "r", encoding="utf-8") as fh:
                content = fh.read()
        except OSError:
            continue

        base_dir = os.path.dirname(real)
        for line in content.splitlines():
            if line.lstrip().startswith("#"):
                continue
            m = INCLUDE_RE.match(line)
            if not m:
                continue
            pattern = m.group(1)
            candidates = (
                glob.glob(pattern)
                if os.path.isabs(pattern)
                else glob.glob(os.path.join(base_dir, pattern))
            )
            for match in sorted(candidates):
                queue.append(match)

    return discovered


def patch_script_src(match):
    directive = match.group(1)
    sources = match.group(2)
    tokens = [token.lower() for token in sources.split()]
    if "'wasm-unsafe-eval'" in tokens:
        return match.group(0)

    self_match = LEADING_SELF_RE.match(sources)
    if self_match:
        prefix = self_match.group(1)
        rest = sources[len(prefix) :]
        return f"{directive}{prefix} 'wasm-unsafe-eval'{rest}"

    return f"{directive} 'wasm-unsafe-eval'{sources}"


def patch_csp_content(content):
    """Add `'wasm-unsafe-eval'` to active CSP `script-src` directives if missing."""
    def replace_header(match):
        prefix, policy, suffix = match.group(1), match.group(2), match.group(3)
        updated_policy = SCRIPT_SRC_RE.sub(patch_script_src, policy)
        return f"{prefix}{updated_policy}{suffix}"

    return CSP_HEADER_RE.sub(replace_header, content)


def backup_file(backup_dir, index, path, manifest):
    os.makedirs(backup_dir, exist_ok=True)
    backup_name = f"{index}.bak"
    shutil.copy2(path, os.path.join(backup_dir, backup_name))
    manifest.append({"backup": backup_name, "original": path})


def restore_backups(backup_dir):
    manifest_path = os.path.join(backup_dir, "manifest.json")
    if not os.path.isfile(manifest_path):
        print("ensure-csp-wasm: nothing to restore")
        return 0

    with open(manifest_path, "r", encoding="utf-8") as fh:
        manifest = json.load(fh)

    for entry in manifest:
        src = os.path.join(backup_dir, entry["backup"])
        dst = entry["original"]
        if os.path.isfile(src):
            shutil.copy2(src, dst)
            print(f"ensure-csp-wasm: restored {dst}")

    return 0


def main():
    parser = argparse.ArgumentParser(
        description="Ensure nginx Content-Security-Policy includes 'wasm-unsafe-eval' in script-src."
    )
    parser.add_argument("paths", nargs="*", help="Nginx config files to inspect")
    parser.add_argument("--backup-dir", help="Directory to store pre-edit backups for rollback")
    parser.add_argument("--restore", metavar="DIR", help="Restore files from a backup directory")
    args = parser.parse_args()

    if args.restore:
        return restore_backups(args.restore)

    if not args.paths:
        parser.error("provide at least one nginx config path or --restore <dir>")

    configs = discover_configs(args.paths)
    manifest = []
    updated_count = 0

    for path in configs:
        with open(path, "r", encoding="utf-8", newline="") as fh:
            original = fh.read()

        updated = patch_csp_content(original)
        if updated == original:
            continue

        if args.backup_dir:
            backup_file(args.backup_dir, updated_count, path, manifest)

        with open(path, "w", encoding="utf-8", newline="") as fh:
            fh.write(updated)

        updated_count += 1
        print(f"ensure-csp-wasm: updated {path}")

    if args.backup_dir and manifest:
        os.makedirs(args.backup_dir, exist_ok=True)
        with open(os.path.join(args.backup_dir, "manifest.json"), "w", encoding="utf-8") as fh:
            json.dump(manifest, fh, indent=2)

    if updated_count == 0:
        print("ensure-csp-wasm: already ok")

    return 0


if __name__ == "__main__":
    sys.exit(main())
