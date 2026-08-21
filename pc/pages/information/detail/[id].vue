<template>
  <div class="max-w-4xl mx-auto px-6 py-10">
    <div v-if="article" class="bg-white rounded-2xl shadow-sm overflow-hidden">
      <img :src="article.image" :alt="article.title" class="w-full h-72 object-cover" />

      <div class="p-8">
        <div class="flex items-center gap-3 mb-4">
          <el-tag size="small">{{ article.cate_name }}</el-tag>
          <span class="text-gray-400 text-sm">{{ article.create_time }}</span>
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-4">{{ article.title }}</h1>

        <div class="flex items-center justify-between text-sm text-gray-400 mb-8 pb-6 border-b">
          <span>{{ article.author }}</span>
          <div class="flex items-center gap-4">
            <span>{{ article.click_num }} 次浏览</span>
            <el-button
              :type="article.collect ? 'primary' : 'default'"
              size="small"
              :icon="article.collect ? 'StarFilled' : 'Star'"
              @click="toggleCollect"
            >{{ article.collect ? '已收藏' : '收藏' }}</el-button>
          </div>
        </div>

        <!-- Article body -->
        <div class="prose max-w-none text-gray-700 leading-relaxed" v-html="safeArticleContent" />
      </div>
    </div>
    <el-empty v-else description="文章不存在" />
  </div>
</template>

<script setup lang="ts">
import sanitizeRichText from '~/utils/sanitize-rich-text'

definePageMeta({ layout: 'default' })

const route = useRoute()
const id = Number(route.params.id)
const apiBase = useRuntimeConfig().public.apiBase
const userStore = useUserStore()

interface ArticleDetail {
  id: number; cate_id: number; cate_name: string; title: string
  image: string; desc: string; content: string; author: string
  click_num: number; collect_num: number; create_time: string; collect: boolean
}

const { data } = await useFetch<{ code: number; data: ArticleDetail }>(
  `${apiBase}/api/article/detail?id=${id}`,
  { headers: userStore.token ? { Authorization: `Bearer ${userStore.token}` } : {} }
)
const article = ref(data.value?.data || null)
const safeArticleContent = computed(() => sanitizeRichText(article.value?.content))

async function toggleCollect() {
  if (!userStore.isLoggedIn) return navigateTo('/login')
  if (!article.value) return
  const endpoint = article.value.collect ? 'cancelCollect' : 'addCollect'
  await $fetch(`${apiBase}/api/article/${endpoint}`, {
    method: 'POST',
    body: { article_id: article.value.id },
    headers: { Authorization: `Bearer ${userStore.token}` },
  })
  article.value.collect = !article.value.collect
}
</script>
