import { resolve } from 'node:path';
import { defineConfig } from "vite";
import uni from "@dcloudio/vite-plugin-uni";
import { readClientEnvironment } from '../scripts/client-environment';

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const environment = readClientEnvironment(resolve(__dirname, `.env.${mode}`));
  const apiProxyTarget = environment.VITE_API_PROXY_TARGET ||
    (environment.PHP_PORT ? `http://127.0.0.1:${environment.PHP_PORT}` : 'http://127.0.0.1');
  return {
  base: '/mobile/',
  plugins: [uni()],
  define: {
    'import.meta.env.VITE_APP_BASE_URL': JSON.stringify(environment.VITE_APP_BASE_URL || ''),
  },
  server: {
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
  };
});
