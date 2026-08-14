#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const [root, manifestPath] = process.argv.slice(2);
if (!root || !manifestPath) {
  console.error('usage: web-public-entry-gate.mjs <application-root> <package-manifest>');
  process.exit(64);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const exportsSet = new Set(
  Object.keys(manifest.exports || {}).map((key) =>
    key === '.' ? '@peanut-admin/admin' : `@peanut-admin/admin${key.slice(1)}`,
  ),
);
const roots = ['web/src', 'web/tests', 'pc', 'uniapp/src'];
const ignored = new Set(['node_modules', 'dist', '.nuxt', '.output']);
const importPattern = /(?:from\s+|import\s*\(\s*|require\s*\(\s*|import\s+)['"]([^'"]+)['"]/g;

function visit(current) {
  if (!fs.existsSync(current)) return;
  const stat = fs.lstatSync(current);
  if (stat.isSymbolicLink()) throw new Error(`symlink not allowed in source scan: ${current}`);
  if (stat.isDirectory()) {
    for (const entry of fs.readdirSync(current)) {
      if (!ignored.has(entry)) visit(path.join(current, entry));
    }
    return;
  }
  if (!/\.(?:ts|tsx|js|jsx|vue|mjs|cjs)$/.test(current)) return;

  const content = fs.readFileSync(current, 'utf8');
  for (const match of content.matchAll(importPattern)) {
    const specifier = match[1];
    if (/(?:^|\/)node_modules\/@peanut-admin(?:\/|$)/.test(specifier)) {
      throw new Error(`npm internal path: ${current}`);
    }
    if (/(?:^|\/)vendor\/peanut-admin(?:\/|$)/.test(specifier)) {
      throw new Error(`Composer vendor path in Web source: ${current}`);
    }
    if (/^@peanut-admin\/admin\/.*\/src(?:\/|$)/.test(specifier)) {
      throw new Error(`npm package src deep import: ${current}`);
    }
    if (/^(?:\.\.\/)+.*packages\/web(?:\/|$)/.test(specifier)) {
      throw new Error(`relative cross-package source import: ${current}`);
    }
    if (
      (specifier === '@peanut-admin/admin' || specifier.startsWith('@peanut-admin/admin/'))
      && !exportsSet.has(specifier)
    ) {
      throw new Error(`undeclared npm export ${specifier} in ${current}`);
    }
  }
}

for (const relative of roots) visit(path.join(root, relative));
