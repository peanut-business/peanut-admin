export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: false },

  modules: ['@element-plus/nuxt', '@pinia/nuxt', '@nuxtjs/tailwindcss'],

  elementPlus: {
    importStyle: 'css',
  },

  runtimeConfig: {
    public: {
      // override at runtime via NUXT_PUBLIC_API_BASE env var
      apiBase: 'http://192.168.192.2:8080',
    },
  },

  tailwindcss: {
    exposeConfig: true,
  },

  typescript: {
    strict: true,
  },
})
