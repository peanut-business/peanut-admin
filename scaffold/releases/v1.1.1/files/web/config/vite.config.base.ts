import { readFileSync } from 'fs';
import { resolve } from 'path';
import { defineConfig, type Plugin } from 'vite';
import vue from '@vitejs/plugin-vue';
import vueJsx from '@vitejs/plugin-vue-jsx';
import svgLoader from 'vite-svg-loader';

interface PluginLockDocument {
  schema_version: number;
  plugins: Array<{
    frontend: Array<{ client_key: string; entry: string }>;
  }>;
}

function lockedAdminContributions(): string[] {
  const lock = JSON.parse(
    readFileSync(resolve(__dirname, '../../plugins.lock'), 'utf8')
  ) as PluginLockDocument;
  if (lock.schema_version !== 1 || !Array.isArray(lock.plugins)) {
    throw new Error('plugins.lock is invalid');
  }
  return lock.plugins
    .flatMap((plugin) => plugin.frontend || [])
    .filter((identity) => identity.client_key === 'admin-web')
    .map((identity) => identity.entry)
    .sort();
}

function pluginContributionManifest(): Plugin {
  const virtualId = 'virtual:peanut-plugin-contributions';
  const resolvedId = `\0${virtualId}`;
  return {
    name: 'peanut-plugin-contribution-manifest',
    resolveId(id) {
      return id === virtualId ? resolvedId : null;
    },
    load(id) {
      if (id !== resolvedId) return null;
      const entries = lockedAdminContributions();
      const imports = entries.map((entry, index) => {
        if (!entry.startsWith('web/src/')) {
          throw new Error(`Plugin contribution is outside web/src: ${entry}`);
        }
        return `import contribution${index} from ${JSON.stringify(`/${entry.slice('web/'.length)}`)};`;
      });
      return `${imports.join('\n')}\nexport default [${entries.map((_, index) => `contribution${index}`).join(',')}];`;
    },
  };
}

export default defineConfig({
  // The admin SPA is published below server/public/admin in every environment.
  base: '/admin/',
  plugins: [
    pluginContributionManifest(),
    vue(),
    vueJsx(),
    svgLoader({ svgoConfig: {} }),
  ],
  resolve: {
    alias: [
      {
        find: '@',
        replacement: resolve(__dirname, '../src'),
      },
      {
        find: 'assets',
        replacement: resolve(__dirname, '../src/assets'),
      },
      {
        find: 'vue-i18n',
        replacement: 'vue-i18n/dist/vue-i18n.cjs.js', // Resolve the i18n warning issue
      },
      {
        find: 'vue',
        replacement: 'vue/dist/vue.esm-bundler.js', // compile template
      },
    ],
    extensions: ['.ts', '.js'],
  },
  define: {
    'process.env': {},
  },
  css: {
    preprocessorOptions: {
      less: {
        modifyVars: {
          hack: `true; @import (reference) "${resolve(
            'src/assets/style/breakpoint.less'
          )}";`,
        },
        javascriptEnabled: true,
      },
    },
  },
});
