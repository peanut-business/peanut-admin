import { resolve } from 'path';
import { defineConfig, mergeConfig, type OutputChunk, type Plugin } from 'vite';
import { createBaseConfig } from './vite.config.base';
import configCompressPlugin from './plugin/compress';
import configVisualizerPlugin from './plugin/visualizer';

function assertNoInstanceTools(): Plugin {
  const route = resolve(__dirname, '../src/router/routes/modules/dev-tools.ts');
  const prefixes = [
    `${resolve(__dirname, '../src/views/dev-tools')}/`,
    `${resolve(__dirname, '../src/api/dev-tools')}/`,
  ];
  const normalize = (value: string) => value.replace(/\\/g, '/');
  const forbiddenRoute = normalize(route);
  const forbiddenPrefixes = prefixes.map(normalize);
  return {
    name: 'peanut-no-instance-tools-in-production',
    generateBundle(_options, bundle) {
      Object.values(bundle).forEach((output) => {
        if (output.type !== 'chunk') return;
        Object.keys((output as OutputChunk).modules).forEach((moduleId) => {
          const normalized = normalize(moduleId);
          if (
            normalized === forbiddenRoute ||
            forbiddenPrefixes.some((prefix) => normalized.startsWith(prefix))
          ) {
            throw new Error(
              `Production bundle contains instance-tool module: ${normalized}`
            );
          }
        });
      });
    },
  };
}

export default defineConfig((configEnv) =>
  mergeConfig(
    {
      mode: 'production',
      plugins: [
        assertNoInstanceTools(),
        configCompressPlugin('gzip'),
        configVisualizerPlugin(),
      ],
      build: {
        manifest: true,
        rollupOptions: {
          output: {
            manualChunks: {
              element: ['element-plus', '@element-plus/icons-vue'],
              chart: ['echarts', 'vue-echarts'],
              vue: ['vue', 'vue-router', 'pinia', '@vueuse/core', 'vue-i18n'],
            },
          },
        },
        chunkSizeWarningLimit: 2000,
      },
    },
    createBaseConfig(configEnv)
  )
);
