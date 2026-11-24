#!/usr/bin/env python3
"""Unit tests for scripts/ensure-csp-wasm.py."""
import importlib.util
import os
import subprocess
import sys
import tempfile
import unittest

SCRIPT_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "ensure-csp-wasm.py")


def load_module():
    spec = importlib.util.spec_from_file_location("ensure_csp_wasm", SCRIPT_PATH)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


PROD_CSP = (
    'add_header Content-Security-Policy "default-src \'self\'; '
    'script-src \'self\' \'unsafe-inline\' https://www.googletagmanager.com https://*.mailerlite.com; '
    'style-src \'self\' \'unsafe-inline\' https://*.mlcdn.com https://*.mailerlite.com https://fonts.googleapis.com; '
    'img-src \'self\' data: https:; '
    'font-src \'self\' data: https://*.mlcdn.com https://*.mailerlite.com https://fonts.gstatic.com; '
    'frame-src \'self\' https://www.youtube-nocookie.com https://drive.google.com; '
    'frame-ancestors \'self\'; object-src \'none\'; '
    'connect-src \'self\' https://www.google-analytics.com https://analytics.google.com https://www.googletagmanager.com https://*.mailerlite.com;" always;\n'
)


class EnsureCspWasmTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.mod = load_module()

    def write(self, rel_path, content):
        full = os.path.join(self.tmp.name, rel_path)
        os.makedirs(os.path.dirname(full), exist_ok=True)
        with open(full, "w", encoding="utf-8", newline="") as fh:
            fh.write(content)
        return full

    def read(self, full_path):
        with open(full_path, "r", encoding="utf-8", newline="") as fh:
            return fh.read()

    def run_cli(self, *args):
        return subprocess.run(
            [sys.executable, SCRIPT_PATH, *args],
            capture_output=True,
            text=True,
            check=False,
        )

    def test_updates_included_security_headers_and_preserves_other_directives(self):
        snippet = self.write("snippets/security-headers.conf", f"add_header X-Frame-Options SAMEORIGIN always;\n{PROD_CSP}")
        flarum_conf = self.write(
            "sites-available/flarum.conf",
            f"server {{\n    include {snippet};\n    location / {{ try_files $uri /index.php?$query_string; }}\n}}\n",
        )

        res = self.run_cli(flarum_conf)
        self.assertEqual(res.returncode, 0, res.stderr)
        self.assertIn("updated", res.stdout)

        expected_csp = PROD_CSP.replace(
            "script-src 'self' 'unsafe-inline'",
            "script-src 'self' 'wasm-unsafe-eval' 'unsafe-inline'",
        )
        self.assertEqual(self.read(snippet), f"add_header X-Frame-Options SAMEORIGIN always;\n{expected_csp}")
        self.assertNotIn("#", self.read(snippet))

    def test_idempotent_second_run_makes_no_changes(self):
        snippet = self.write("snippets/security-headers.conf", PROD_CSP)
        res1 = self.run_cli(snippet)
        self.assertEqual(res1.returncode, 0)
        first_text = self.read(snippet)

        res2 = self.run_cli(snippet)
        self.assertEqual(res2.returncode, 0)
        self.assertIn("already ok", res2.stdout)
        self.assertEqual(self.read(snippet), first_text)

    def test_leaves_existing_wasm_unsafe_eval_untouched_regardless_of_position(self):
        existing = (
            'add_header Content-Security-Policy "default-src \'self\'; '
            'script-src \'self\' \'unsafe-inline\' https://example.com \'wasm-unsafe-eval\';" always;\n'
        )
        snippet = self.write("security-headers.conf", existing)
        res = self.run_cli(snippet)
        self.assertEqual(res.returncode, 0)
        self.assertIn("already ok", res.stdout)
        self.assertEqual(self.read(snippet), existing)

    def test_ignores_commented_out_csp_lines(self):
        commented = f"# {PROD_CSP}"
        snippet = self.write("security-headers.conf", commented)
        res = self.run_cli(snippet)
        self.assertEqual(res.returncode, 0)
        self.assertIn("already ok", res.stdout)
        self.assertEqual(self.read(snippet), commented)

    def test_updates_inline_csp_in_flarum_conf_and_does_not_touch_script_src_elem(self):
        conf = self.write(
            "flarum.conf",
            'server {\n    add_header Content-Security-Policy "default-src \'self\'; script-src-elem \'self\'; script-src \'unsafe-inline\';" always;\n}\n',
        )
        res = self.run_cli(conf)
        self.assertEqual(res.returncode, 0)
        self.assertEqual(
            self.read(conf),
            'server {\n    add_header Content-Security-Policy "default-src \'self\'; script-src-elem \'self\'; script-src \'wasm-unsafe-eval\' \'unsafe-inline\';" always;\n}\n',
        )

    def test_updates_script_src_when_last_directive_without_trailing_semicolon(self):
        conf = self.write(
            "flarum.conf",
            'add_header Content-Security-Policy "default-src \'self\'; script-src \'self\'" always;\n',
        )
        res = self.run_cli(conf)
        self.assertEqual(res.returncode, 0)
        self.assertEqual(
            self.read(conf),
            'add_header Content-Security-Policy "default-src \'self\'; script-src \'self\' \'wasm-unsafe-eval\'" always;\n',
        )

    def test_skips_missing_file_paths_gracefully(self):
        missing = os.path.join(self.tmp.name, "nonexistent.conf")
        existing = self.write("security-headers.conf", PROD_CSP)
        res = self.run_cli(missing, existing)
        self.assertEqual(res.returncode, 0)
        self.assertIn("'wasm-unsafe-eval'", self.read(existing))

    def test_backup_and_restore_restores_exact_original_contents(self):
        snippet = self.write("snippets/security-headers.conf", PROD_CSP)
        bak_dir = os.path.join(self.tmp.name, "bak")

        res = self.run_cli("--backup-dir", bak_dir, snippet)
        self.assertEqual(res.returncode, 0)
        self.assertIn("'wasm-unsafe-eval'", self.read(snippet))

        restore_res = self.run_cli("--restore", bak_dir)
        self.assertEqual(restore_res.returncode, 0)
        self.assertEqual(self.read(snippet), PROD_CSP)

    def test_restore_with_empty_or_missing_backup_dir_exits_zero(self):
        missing_bak = os.path.join(self.tmp.name, "no-bak")
        res = self.run_cli("--restore", missing_bak)
        self.assertEqual(res.returncode, 0)


if __name__ == "__main__":
    unittest.main()
