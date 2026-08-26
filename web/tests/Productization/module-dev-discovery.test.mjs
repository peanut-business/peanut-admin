import {
  mkdtempSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  realpathSync,
  rmSync,
  writeFileSync,
} from 'fs';
import { tmpdir } from 'os';
import { resolve } from 'path';
import { fileURLToPath } from 'url';
import { loadConfigFromFile } from 'vite';

function expect(condition, message) {
  if (!condition) throw new Error(message);
}

function manifestPaths(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = resolve(directory, entry.name);
    if (entry.isDirectory()) return manifestPaths(path);
    return entry.isFile() && entry.name === 'module.json' ? [path] : [];
  });
}

const webRoot = realpathSync(
  resolve(fileURLToPath(new URL('../..', import.meta.url)))
);
const projectRoot = resolve(webRoot, '..');
const virtualId = 'virtual:peanut-plugin-contributions';

async function virtualSource() {
  const loaded = await loadConfigFromFile(
    { command: 'serve', mode: 'development' },
    resolve(webRoot, 'config/vite.config.dev.ts'),
    webRoot
  );
  expect(loaded !== null, 'development Vite config could not be loaded');
  const plugin = loaded.config.plugins?.find(
    (candidate) => candidate.name === 'peanut-plugin-contribution-manifest'
  );
  expect(plugin, 'plugin contribution virtual module is missing');
  const resolvedId = await plugin.resolveId(virtualId);
  return plugin.load(resolvedId);
}

const first = await virtualSource();
const second = await virtualSource();
expect(
  first === second,
  'development contribution discovery is not deterministic'
);
const expectedEntries = manifestPaths(
  resolve(projectRoot, 'server/app/Modules')
)
  .map((path) => JSON.parse(readFileSync(path, 'utf8')))
  .map((manifest) => manifest.frontend?.entry)
  .filter(Boolean)
  .sort();
expectedEntries.forEach((entry) => {
  const importPath = `/${entry.slice('web/'.length)}`;
  expect(
    first.includes(JSON.stringify(importPath)),
    `development Vite omitted ${entry}`
  );
});
expect(
  (first.match(/^import contribution/gm) || []).length ===
    expectedEntries.length,
  'development Vite contribution count differs from module.json'
);

const devConfigSource = readFileSync(
  resolve(webRoot, 'config/vite.config.dev.ts'),
  'utf8'
);
expect(
  !devConfigSource.includes('plugins.lock') &&
    devConfigSource.includes(
      'createBaseConfig(configEnv, discoverAdminContributions)'
    ),
  'development Vite config still depends on plugins.lock'
);

const temporary = mkdtempSync(resolve(tmpdir(), 'pa-module-dev-discovery-'));
try {
  const invalidRoot = resolve(
    temporary,
    'server/app/Modules/Fixture/InvalidEntry'
  );
  mkdirSync(invalidRoot, { recursive: true });
  mkdirSync(resolve(temporary, 'web/src/modules/fixture-invalid-entry'), {
    recursive: true,
  });
  writeFileSync(
    resolve(invalidRoot, 'module.json'),
    JSON.stringify({
      key: 'fixture.invalid-entry',
      frontend: { entry: 'web/src/modules/wrong/contribution.ts' },
    })
  );
  const probe = resolve(temporary, 'vite.config.ts');
  writeFileSync(
    probe,
    `import { discoverAdminContributions } from ${JSON.stringify(
      resolve(webRoot, 'config/vite.config.dev.ts')
    )};\nexport default { entries: discoverAdminContributions(${JSON.stringify(
      temporary
    )}) };\n`
  );
  let rejected = false;
  try {
    await loadConfigFromFile(
      { command: 'serve', mode: 'development' },
      probe,
      temporary
    );
  } catch (error) {
    rejected = String(error).includes('frontend.entry differs from key');
  }
  expect(rejected, 'mismatched development frontend.entry was accepted');
} finally {
  rmSync(temporary, { recursive: true, force: true });
}

// eslint-disable-next-line no-console
console.log(
  `MODULE-DEV-DISCOVERY-C-001 passed modules=${expectedEntries.length}`
);
