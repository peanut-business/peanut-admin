import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ConfigData } from '@/api/index'
import { getConfig } from '@/api/index'

export const useAppStore = defineStore('app', () => {
  const config = ref<ConfigData | null>(null)

  async function loadConfig() {
    try {
      config.value = await getConfig()
    } catch (error) {
      console.error('Failed to load config:', error)
    }
  }

  return {
    config,
    loadConfig,
  }
})
