<template>
  <div class="max-w-6xl mx-auto px-6 py-10">
    <!-- Category tabs -->
    <div class="flex gap-3 mb-8 flex-wrap">
      <el-tag
        :type="!currentCate ? 'primary' : 'info'"
        size="large"
        class="cursor-pointer"
        @click="switchCate(null)"
      >全部</el-tag>
      <el-tag
        v-for="cate in categories"
        :key="cate.id"
        :type="currentCate === cate.id ? 'primary' : 'info'"
        size="large"
        class="cursor-pointer"
        @click="switchCate(cate.id)"
      >{{ cate.name }}</el-tag>
    </div>

    <!-- Article grid -->
    <div v-if="articles.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <ArticleCard v-for="item in articles" :key="item.id" :article="item" />
    </div>
    <el-empty v-else description="暂无资讯" />

    <!-- Pagination -->
    <div v-if="total > pageSize" class="flex justify-center mt-10">
      <el-pagination
        v-model:current-page="pageNo"
        :page-size="pageSize"
        :total="total"
        layout="prev, pager, next"
        @current-change="loadArticles"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

interface Article { id: number; cate_id: number; cate_name: string; title: string; image: string; desc: string; author: string; click_num: number; collect_num: number; create_time: string }
interface ArticleCate { id: number; name: string; image: string; sort: number }

const apiBase = useRuntimeConfig().public.apiBase
const currentCate = ref<number | null>(null)
const pageNo = ref(1)
const pageSize = 12
const articles = ref<Article[]>([])
const total = ref(0)

const { data: cateData } = await useFetch<{ code: number; data: ArticleCate[] }>(() => `${apiBase}/api/article/cate`)
const categories = computed(() => cateData.value?.data || [])

await loadArticles()

async function loadArticles() {
  const params = new URLSearchParams({ page_no: String(pageNo.value), page_size: String(pageSize) })
  if (currentCate.value) params.set('cate_id', String(currentCate.value))
  const res = await $fetch<{ code: number; data: { lists: Article[]; count: number } }>(
    `${apiBase}/api/article/lists?${params}`
  )
  articles.value = res.data?.lists || []
  total.value = res.data?.count || 0
}

function switchCate(id: number | null) {
  currentCate.value = id
  pageNo.value = 1
  loadArticles()
}
</script>
