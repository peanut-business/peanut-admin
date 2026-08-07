export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: false },
  ssr: false,

  app: {
    baseURL: '/pc/',
  },

  modules: ['@element-plus/nuxt', '@pinia/nuxt', '@nuxtjs/tailwindcss'],

  elementPlus: {
    importStyle: 'css',
  },

  runtimeConfig: {
    public: {
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
