<template>
  <div class="bg-white rounded-xl shadow-sm p-8">
    <h2 class="text-xl font-bold text-gray-800 mb-6">我的收藏</h2>

    <div v-if="articles.length" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div
        v-for="item in articles"
        :key="item.id"
        class="flex gap-4 p-4 border border-gray-100 rounded-xl hover:border-primary/30 transition-colors cursor-pointer"
        @click="$router.push(`/information/detail/${item.article_id}`)"
      >
        <img :src="item.image" class="w-24 h-20 rounded-lg object-cover shrink-0" />
        <div class="flex-1 min-w-0">
          <div class="font-medium text-gray-800 text-sm leading-snug mb-1 line-clamp-2">{{ item.title }}</div>
          <div class="text-gray-400 text-xs line-clamp-2">{{ item.desc }}</div>
        </div>
      </div>
    </div>
    <el-empty v-else description="暂无收藏" />

    <div v-if="total > pageSize" class="flex justify-center mt-8">
      <el-pagination
        v-model:current-page="pageNo"
        :page-size="pageSize"
        :total="total"
        layout="prev, pager, next"
        @current-change="loadCollections"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'user', middleware: 'auth' })

const userStore = useUserStore()
const apiBase = useRuntimeConfig().public.apiBase
const pageNo = ref(1)
const pageSize = 12

interface CollectItem { id: number; article_id: number; title: string; image: string; desc: string }

const articles = ref<CollectItem[]>([])
const total = ref(0)

await loadCollections()

async function loadCollections() {
  const res = await $fetch<{ code: number; data: { lists: CollectItem[]; count: number } }>(
    `${apiBase}/api/article/collect?page_no=${pageNo.value}&page_size=${pageSize}`,
    { headers: { Authorization: `Bearer ${userStore.token}` } }
  )
  articles.value = res.data?.lists || []
  total.value = res.data?.count || 0
}
</script>
