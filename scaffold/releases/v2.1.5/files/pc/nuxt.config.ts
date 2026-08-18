import defaultBrand from './generated/brand.json'

const runtimeEnv =
  (globalThis as { process?: { env?: Record<string, string | undefined> } }).process?.env ?? {}
const devProxyOrigin = runtimeEnv.NUXT_DEV_PROXY_ORIGIN || 'http://127.0.0.1'
const devProxyTarget = runtimeEnv.NUXT_DEV_PROXY_TARGET || `${devProxyOrigin}/api`

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
        target: devProxyTarget,
        changeOrigin: false,
      },
      '/brand': {
        target: `${devProxyOrigin}/brand`,
        changeOrigin: false,
      },
      '/storage': {
        target: `${devProxyOrigin}/storage`,
        changeOrigin: false,
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
