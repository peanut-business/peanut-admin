<template>
  <view class="detail-page">
    <view v-if="article" class="content">
      <image :src="article.image" class="cover" mode="widthFix" />

      <view class="article-body">
        <view class="title">{{ article.title }}</view>
        <view class="meta">
          <text>{{ article.author }}</text>
          <text>{{ article.click_num }} 次浏览</text>
          <text>{{ article.create_time }}</text>
        </view>
        <!-- rich text content -->
        <rich-text :nodes="article.content" class="rich-content" />
      </view>
    </view>

    <view v-if="article" class="action-bar">
      <view class="action-item" @click="toggleCollect">
        <text>{{ article.collect ? '❤️' : '🤍' }}</text>
        <text>{{ article.collect ? '已收藏' : '收藏' }}</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getArticleDetail, addCollect, cancelCollect } from '@/api/news'
import type { ArticleDetail } from '@/api/news'
import { useUserStore } from '@/store/user'

const userStore = useUserStore()
const props = defineProps<{ id?: string }>()
const article = ref<ArticleDetail | null>(null)

// pages in UniApp receive params via onLoad options
onMounted(() => {
  const pages = getCurrentPages()
  const page = pages[pages.length - 1] as any
  const id = page?.options?.id
  if (id) loadDetail(Number(id))
})

async function loadDetail(id: number) {
  try {
    article.value = await getArticleDetail(id)
  } catch (error) {
    console.error('Failed to load article detail:', error)
  }
}

async function toggleCollect() {
  if (!userStore.isLoggedIn) {
    uni.navigateTo({ url: '/pages/login/login' })
    return
  }
  if (!article.value) return
  try {
    if (article.value.collect) {
      await cancelCollect(article.value.id)
    } else {
      await addCollect(article.value.id)
    }
    article.value.collect = !article.value.collect
  } catch (error) {
    console.error('Failed to toggle collect:', error)
  }
}
</script>

<style scoped>
.detail-page { background: #fff; min-height: 100vh; padding-bottom: 100rpx; }
.cover { width: 100%; }
.article-body { padding: 30rpx; }
.title { font-size: 36rpx; font-weight: 700; color: #333; line-height: 1.4; }
.meta { display: flex; gap: 20rpx; font-size: 24rpx; color: #999; margin: 20rpx 0 30rpx; }
.rich-content { font-size: 30rpx; line-height: 1.8; color: #444; }
.action-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1rpx solid #eee; padding: 20rpx 40rpx; display: flex; }
.action-item { display: flex; flex-direction: column; align-items: center; font-size: 24rpx; color: #666; }
</style>
