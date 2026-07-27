export const useAppStore = defineStore('app', () => {
  const config = ref<{
    domain: string
    website: { shop_name: string; shop_logo: string }
    login: { login_way: number[] }
    version: string
  } | null>(null)

  async function loadConfig() {
    if (config.value) return
    const { get } = useRequest()
    try {
      config.value = await get('api/index/config', undefined, false)
    } catch (error) {
      console.error('Failed to load config:', error)
    }
  }

  return { config, loadConfig }
})
