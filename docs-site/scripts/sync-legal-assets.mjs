#!/usr/bin/env node

import { mkdir, readFile, writeFile } from 'node:fs/promises'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const docsRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repositoryRoot = resolve(docsRoot, '..')
const targetRoot = join(docsRoot, 'public', 'legal')
const checkOnly = process.argv.includes('--check')
const assets = [
  ['LICENSE', 'LICENSE.txt'],
  ['NOTICE', 'NOTICE.txt'],
  ['THIRD_PARTY_NOTICES.md', 'THIRD_PARTY_NOTICES.txt'],
  ['RELEASE_SBOM.spdx.json', 'RELEASE_SBOM.spdx.json'],
  ['CHANGELOG.md', 'CHANGELOG.txt'],
  ['RELEASE_METADATA.json', 'RELEASE_METADATA.json'],
]

for (const [sourceName, targetName] of assets) {
  const source = await readFile(join(repositoryRoot, sourceName))
  const target = join(targetRoot, targetName)
  if (checkOnly) {
    const current = await readFile(target).catch(() => null)
    if (!current || !current.equals(source)) {
      throw new Error(`generated legal asset is stale: docs-site/public/legal/${targetName}`)
    }
    continue
  }
  await mkdir(dirname(target), { recursive: true })
  await writeFile(target, source)
}

if (!checkOnly) process.stdout.write('Release legal assets synchronized.\n')
