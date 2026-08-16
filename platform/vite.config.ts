import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  base: '/platform/',
  plugins: [vue()],
  server: {
    proxy: {
      '/api': { target: process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:20180', changeOrigin: false },
    },
  },
});
