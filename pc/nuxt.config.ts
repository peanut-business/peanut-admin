export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: false },

  modules: ['@element-plus/nuxt', '@pinia/nuxt', '@nuxtjs/tailwindcss'],

  elementPlus: {
    importStyle: 'css',
  },

  runtimeConfig: {
    // SSR cannot consume the browser-only same-origin proxy. Deployments may
    // override this private origin without exposing it to clients.
    apiServerBase: 'http://127.0.0.1:8000',
    public: {
      // Production normally serves the API on the same origin. Override only
      // when the deployed frontend intentionally uses a separate API origin.
      apiBase: '',
    },
  },

  nitro: {
    devProxy: {
      '/api': {
        target: 'http://127.0.0.1:8000/api',
        changeOrigin: true,
      },
    },
  },

  tailwindcss: {
    exposeConfig: true,
  },

  typescript: {
    strict: true,
  },
})
