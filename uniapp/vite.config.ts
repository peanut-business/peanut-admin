import { defineConfig } from "vite";
import uni from "@dcloudio/vite-plugin-uni";

const apiProxyTarget = process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1';

// https://vitejs.dev/config/
export default defineConfig({
  base: '/mobile/',
  plugins: [uni()],
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
});
