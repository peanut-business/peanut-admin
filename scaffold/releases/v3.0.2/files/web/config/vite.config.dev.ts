import { mergeConfig } from 'vite';
import eslint from 'vite-plugin-eslint';
import baseConfig from './vite.config.base';

const apiProxyTarget = process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1';
const allowedHosts = (process.env.VITE_ALLOWED_HOSTS || '')
  .split(',')
  .map((host) => host.trim())
  .filter(Boolean);

export default mergeConfig(
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
        },
        '/brand': {
          target: apiProxyTarget,
          changeOrigin: false,
        },
        '/storage': {
          target: apiProxyTarget,
          changeOrigin: false,
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
  baseConfig
);
