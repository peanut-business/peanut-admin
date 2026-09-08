import defaultBrand from '~/generated/brand.json'

export interface WebsiteConfig {
  name: string
  web_favicon: string
  web_logo: string
  login_image: string
  shop_name: string
  shop_logo: string
  pc_logo: string
  pc_title: string
  pc_ico: string
  pc_desc: string
  pc_keywords: string
  h5_favicon: string
  slogan: string
  copyright: string
  official_url: string
  github_url: string
}

const imageFields: (keyof WebsiteConfig)[] = [
  'web_favicon', 'web_logo', 'login_image', 'shop_logo',
  'pc_logo', 'pc_ico', 'h5_favicon',
]
const fallbackWebsite = { ...defaultBrand.website } as WebsiteConfig
imageFields.forEach((field) => {
  const value = fallbackWebsite[field]
  if (value && !value.startsWith('/') && !value.startsWith('http')) {
    fallbackWebsite[field] = `/${value}`
  }
})

export const useAppStore = defineStore('app', () => {
  const config = ref<{
    domain: string
    website: WebsiteConfig
    login: { login_way: number[] }
    version: string
  } | null>(null)
  const website = computed(() => config.value?.website || fallbackWebsite)

  async function loadConfig() {
    if (config.value) return
    const { get } = useRequest()
    try {
      config.value = await get('api/index/config', undefined, false)
    } catch (error) {
      console.error('Failed to load config:', error)
    }
  }

  return { config, website, loadConfig }
})
