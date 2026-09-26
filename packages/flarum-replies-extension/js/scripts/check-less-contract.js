#!/usr/bin/env node
// Token contract lint (knowledge/design-system/tokens.md rules 1–2):
// no literal hex colours and no scheme-override blocks in extension page styles.
// Palette files (itqan-theme/color-schemes.less, tokens.less) are out of scope.
const fs = require('fs');
const path = require('path');

const lessDir = path.join(__dirname, '..', '..', 'less');
let failed = false;

for (const file of fs.readdirSync(lessDir).filter((n) => n.endsWith('.less'))) {
  const lines = fs.readFileSync(path.join(lessDir, file), 'utf8').split(/\r?\n/);
  lines.forEach((line, i) => {
    const code = line.replace(/\/\/.*$/, ''); // strip line comments
    if (/#{/.test(code)) return;              // ignore LESS interpolation
    if (/#[0-9a-fA-F]{3,8}\b/.test(code)) {
      console.error(`FAIL ${file}:${i + 1} hex literal: ${line.trim()}`);
      failed = true;
    }
    if (/data-itqan-theme|dark-mode/.test(code)) {
      console.error(`FAIL ${file}:${i + 1} scheme-override block: ${line.trim()}`);
      failed = true;
    }
  });
}

if (failed) process.exit(1);
console.log('less token contract OK: no hex literals, no scheme overrides');
