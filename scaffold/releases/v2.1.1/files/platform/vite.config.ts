import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

const allowedHosts = (process.env.VITE_ALLOWED_HOSTS || '')
  .split(',')
  .map((host) => host.trim())
  .filter(Boolean);

export default defineConfig({
  base: '/platform/',
  plugins: [vue()],
  server: {
    allowedHosts,
    proxy: {
      '/api': { target: process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:20180', changeOrigin: false },
    },
  },
});
