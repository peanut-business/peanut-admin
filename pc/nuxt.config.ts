import defaultBrand from './generated/brand.json'

export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: false },
  ssr: false,

  app: {
    baseURL: '/pc/',
    head: {
      title: defaultBrand.website.pc_title,
      meta: [
        { name: 'description', content: defaultBrand.website.pc_desc },
        { name: 'keywords', content: defaultBrand.website.pc_keywords },
      ],
      link: [{ rel: 'icon', href: '/brand/favicon.svg' }],
    },
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
        target: process.env.NUXT_DEV_PROXY_TARGET || 'http://127.0.0.1:8000/api',
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
