import { defineConfig, mergeConfig } from 'vite';
import eslint from 'vite-plugin-eslint';
import { createBaseConfig } from './vite.config.base';

const apiProxyTarget = process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1';
const allowedHosts = (process.env.VITE_ALLOWED_HOSTS || '')
  .split(',')
  .map((host) => host.trim())
  .filter(Boolean);
const tenantEntryHost = process.env.VITE_TENANT_ENTRY_HOST || '';

const proxyHeaders = tenantEntryHost ? { host: tenantEntryHost } : undefined;

export default defineConfig((configEnv) =>
  mergeConfig(
    {
      mode: 'development',
      server: {
        open: process.env.VITE_OPEN_BROWSER !== 'false',
        allowedHosts,
        fs: {
          strict: true,
        },
        proxy: {
          '/api': {
            target: apiProxyTarget,
            changeOrigin: false,
            headers: proxyHeaders,
          },
          '/brand': {
            target: apiProxyTarget,
            changeOrigin: false,
            headers: proxyHeaders,
          },
          '/storage': {
            target: apiProxyTarget,
            changeOrigin: false,
            headers: proxyHeaders,
          },
        },
      },
      plugins: [
        eslint({
          cache: false,
          include: ['src/**/*.ts', 'src/**/*.tsx', 'src/**/*.vue'],
          exclude: ['node_modules'],
        }),
      ],
    },
    createBaseConfig(configEnv)
  )
);
