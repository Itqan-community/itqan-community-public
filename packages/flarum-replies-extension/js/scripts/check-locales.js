#!/usr/bin/env node
// Three-way locale coverage (spec §7): en.yml ↔ ar.yml parity, plus every
// *statically referenced* key existing in en. Scope notes:
//   - Only keys passed literally to `trans(...)` are scanned. Dynamic template
//     keys (`forum.${key}` in VoteRail/CollapseToggle) and helper-bare keys
//     (`trans('tombstone')` — the index.js `trans` helper prefixes for us)
//     can't be resolved statically; those enumerations live in their
//     components and are covered by the QA matrix.
//   - Storage keys share the extension prefix (`setting: 'mtareq-nested-replies.enabled'`,
//     `SORT_PREFERENCE_KEY`) but are settings-table rows, not translation
//     keys — hence the trans()-scoped scan (plan-draft defect #26: a bare
//     quoted-prefix scan produced 13 false FAILs).
//   - The leaf-key character class must include `-`: the root key
//     `mtareq-nested-replies:` contains hyphens, and without them line 1 was
//     skipped, dropping the namespace from every path — en/ar parity then
//     passed vacuously on two identically-wrong sets (defect #25). The
//     size guard below blocks that class of silent pass for good.
const fs = require('fs');
const path = require('path');

const jsRoot = path.join(__dirname, '..');           // js/
const extRoot = path.join(jsRoot, '..');             // extension root
const localeDir = path.join(extRoot, 'locale');

function leafKeys(file) {
  const keys = [];
  const stack = []; // {indent, path}
  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    if (!line.trim() || line.trim().startsWith('#')) continue;
    const m = line.match(/^(\s*)([A-Za-z0-9_.-]+):\s*(.*)$/);
    if (!m) continue;
    const indent = m[1].length;
    const key = m[2];
    const rest = m[3].trim();
    while (stack.length && stack[stack.length - 1].indent >= indent) stack.pop();
    const full = stack.length ? `${stack[stack.length - 1].path}.${key}` : key;
    if (rest === '') stack.push({ indent, path: full });
    else keys.push(full);
  }
  return new Set(keys);
}

function staticCalls(dir, out = new Set()) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) staticCalls(p, out);
    else if (entry.name.endsWith('.js')) {
      const text = fs.readFileSync(p, 'utf8');
      // Literal key immediately inside a trans() call (same-line; verified no
      // trans( calls leave their key on the following line). The closing
      // quote requirement keeps template-literal keys (`forum.${key}`) out.
      for (const m of text.matchAll(/trans\(\s*['"`]mtareq-nested-replies\.([A-Za-z0-9_.]+)['"`]/g)) {
        out.add(`mtareq-nested-replies.${m[1]}`);
      }
    }
  }
  return out;
}

const en = leafKeys(path.join(localeDir, 'en.yml'));
const ar = leafKeys(path.join(localeDir, 'ar.yml'));
const calls = staticCalls(path.join(jsRoot, 'src'));

let failed = false;
const fail = (msg) => { console.error(`FAIL ${msg}`); failed = true; };

// A parse that yields nothing must never pass — parity over two empty sets
// is vacuously true (defect #25's signature).
if (!en.size || !ar.size) fail(`leafKeys parsed en=${en.size} ar=${ar.size} — yml parse broken`);

for (const k of en) if (!ar.has(k)) fail(`ar.yml missing: ${k}`);
for (const k of ar) if (!en.has(k)) fail(`en.yml missing: ${k}`);
for (const c of calls) if (!en.has(c)) fail(`js references missing en.yml key: ${c}`);

if (failed) process.exit(1);
console.log(`locale coverage OK: ${en.size} en keys, ${ar.size} ar keys, ${calls.size} static js calls`);
