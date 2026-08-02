<template>
  <div class="max-w-6xl mx-auto px-6 py-10">
    <section v-if="bannerItems.length" class="pc-banner mb-12" :style="bannerStyle">
      <button
        v-for="item in bannerItems"
        :key="`${item.name}-${item.image}`"
        type="button"
        class="pc-banner-item"
        @click="executeDecorationLink(item.link)"
      >
        <img v-if="item.image" :src="item.image" :alt="item.name" />
        <span v-if="item.name" class="pc-banner-title">{{ item.name }}</span>
      </button>
    </section>
    <div v-else class="rounded-2xl bg-gradient-to-r from-blue-600 to-blue-400 text-white p-12 mb-12">
      <h1 class="text-4xl font-bold mb-4">{{ config?.website?.shop_name }}</h1>
      <p class="text-blue-100 text-lg">探索最新资讯，了解更多精彩内容</p>
      <NuxtLink to="/information">
        <el-button class="mt-6" size="large">浏览全部资讯</el-button>
      </NuxtLink>
    </div>

    <!-- Latest articles -->
    <section>
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">最新资讯</h2>
        <NuxtLink to="/information" class="text-primary text-sm hover:underline">查看全部 →</NuxtLink>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <ArticleCard v-for="item in articles" :key="item.id" :article="item" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

const appStore = useAppStore()
const config = computed(() => appStore.config)

interface Article {
  id: number; cate_id: number; cate_name: string; title: string
  image: string; desc: string; author: string; click_num: number
  collect_num: number; create_time: string
}

interface DecorationLink {
  target_type: 'shop' | 'article' | 'custom' | 'mini_program'
  target: string | number
  query?: Record<string, unknown>
}

interface DecorationItem {
  image: string
  name: string
  link: DecorationLink
  is_show?: 0 | 1
}

interface DecorationComponent {
  name: string
  content: { enabled?: 0 | 1; data?: DecorationItem[]; [key: string]: unknown }
  styles?: Record<string, string | number>
}

const runtimeConfig = useRuntimeConfig()
const apiBase = import.meta.server
  ? runtimeConfig.apiServerBase
  : runtimeConfig.public.apiBase
const { data: indexData } = await useFetch<{ code: number; data: { all?: Article[]; article?: Article[]; decorate?: { data: DecorationComponent[] } } }>(
  () => `${apiBase}/api/pc/index`
)
const articles = computed(() => indexData.value?.data?.all || indexData.value?.data?.article || [])
const decorate = computed(() => indexData.value?.data?.decorate)
const bannerComponent = computed(() => {
  const list = decorate.value?.data
  return Array.isArray(list) ? list.find((item) => item.name === 'pc-banner') : undefined
})
const bannerItems = computed(() => {
  const component = bannerComponent.value
  if (!component || component.content.enabled === 0 || !Array.isArray(component.content.data)) return []
  return component.content.data.filter((item) => item.is_show === undefined || item.is_show === 1)
})
const bannerStyle = computed(() => bannerComponent.value?.styles || {})

function executeDecorationLink(link: DecorationLink) {
  if (!link || typeof link.target !== 'string' && typeof link.target !== 'number') return
  const target = String(link.target)
  const shopRoutes: Record<string, string> = {
    home: '/',
    news: '/information',
    profile: '/user/info',
    settings: '/account/security',
    favorites: '/user/collection',
    customer_service: '/account/security',
    wallet: '/account/security',
    privacy: '/policy/privacy',
    service: '/policy/service',
  }
  if (link.target_type === 'shop') {
    const route = shopRoutes[target]
    if (route) navigateTo(route)
    return
  }
  if (link.target_type === 'article') {
    navigateTo(`/information/detail/${encodeURIComponent(target)}`)
    return
  }
  if (link.target_type === 'custom' && /^https?:\/\//i.test(target)) {
    window.location.assign(target)
    return
  }
  // A mini-program target is intentionally not interpreted as a PC route.
  const webUrl = typeof link.query?.web_url === 'string' ? link.query.web_url : ''
  if (/^https?:\/\//i.test(webUrl)) window.location.assign(webUrl)
}
</script>

<style scoped>
.pc-banner { min-height: 260px; overflow: hidden; border-radius: 1rem; background: #f4f6f8; }
.pc-banner-item { position: relative; display: block; width: 100%; border: 0; padding: 0; background: transparent; cursor: pointer; }
.pc-banner-item img { display: block; width: 100%; max-height: 420px; object-fit: cover; }
.pc-banner-title { position: absolute; left: 1.25rem; bottom: 1rem; color: white; text-shadow: 0 1px 4px rgb(0 0 0 / 45%); }
</style>
