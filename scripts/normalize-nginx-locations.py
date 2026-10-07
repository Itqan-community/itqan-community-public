#!/usr/bin/env python3
"""Normalize the Flarum nginx site conf so /robots.txt and /og/ are handled
exactly once.

Earlier deploys appended these locations, which duplicated an existing
`location = /robots.txt` on production and made `nginx -t` fail. This script
is idempotent: it removes any existing /robots.txt and /og/ location blocks
(brace-aware, so nested `if {}` blocks are handled) and inserts exactly one
managed block before the main `location / {`.

Usage:
    normalize-nginx-locations.py <conf> [--beta-guard]

`--beta-guard` adds the production `beta.community.itqan.dev -> 403` rule to
the robots location (staging does not need it).
"""
import argparse
import re
import sys

parser = argparse.ArgumentParser()
parser.add_argument("conf")
parser.add_argument("--beta-guard", action="store_true")
args = parser.parse_args()

s = open(args.conf, encoding="utf-8").read()

# 1) Remove a previously managed block (marker-delimited).
s = re.sub(
    r"(?ms)^[ \t]*# BEGIN itqan managed locations.*?[ \t]*# END itqan managed locations[ \t]*\n?",
    "",
    s,
)

# 2) Remove orphan comments that referenced the old robots handling.
s = re.sub(
    r"(?m)^[ \t]*#[^\n]*robots\.txt[^\n]*\n(?:[ \t]*#[^\n]*\n)*",
    "",
    s,
)

# 3) Brace-aware removal of any `location = /robots.txt` or `location ^~ /og/`.
HEADER_RE = re.compile(r"(?m)^[ \t]*location\s*(?:=\s*/robots\.txt|\^~\s*/og/)")
i = 0
out = []
while True:
    m = HEADER_RE.search(s, i)
    if not m:
        out.append(s[i:])
        break
    out.append(s[i : m.start()])
    brace = s.find("{", m.start())
    if brace == -1:
        i = m.end()
        continue
    depth = 0
    j = brace
    while j < len(s):
        ch = s[j]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                j += 1
                break
        j += 1
    while j < len(s) and s[j] in " \t":
        j += 1
    if j < len(s) and s[j] == "\n":
        j += 1
    i = j
s = "".join(out)

# 4) Build and insert exactly one managed block before `location / {`.
robots = "    location = /robots.txt {\n"
if args.beta_guard:
    robots += "        if ($host = beta.community.itqan.dev) {\n            return 403;\n        }\n"
robots += "        try_files $uri /index.php?$query_string;\n    }\n"

managed = (
    "    # BEGIN itqan managed locations\n"
    + robots
    + "\n"
    + "    location ^~ /og/ {\n"
    + "        try_files $uri /index.php?$query_string;\n"
    + "    }\n"
    + "    # END itqan managed locations\n"
)

if "    location / {" not in s:
    sys.exit("normalize-nginx-locations: 'location / {' anchor not found")

s = s.replace("    location / {", managed + "    location / {", 1)
open(args.conf, "w", encoding="utf-8").write(s)
print("normalize-nginx-locations: ok")
