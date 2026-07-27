<template>
  <div class="max-w-6xl mx-auto px-6 py-10">
    <!-- Hero -->
    <div class="rounded-2xl bg-gradient-to-r from-blue-600 to-blue-400 text-white p-12 mb-12">
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

const { data: indexData } = await useFetch<{ code: number; data: { article: Article[] } }>(
  () => `${useRuntimeConfig().public.apiBase}/api/index/index`
)
const articles = computed(() => indexData.value?.data?.article || [])
</script>
