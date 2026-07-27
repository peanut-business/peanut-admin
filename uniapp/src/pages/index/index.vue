<template>
  <view class="index-page">
    <view class="banner">
      <image src="/static/banner.png" mode="widthFix" class="banner-img" />
    </view>

    <view class="article-section">
      <view class="section-title">最新资讯</view>
      <view class="article-list">
        <view
          v-for="item in articles"
          :key="item.id"
          class="article-item"
          @click="goDetail(item.id)"
        >
          <image :src="item.image" class="article-img" mode="aspectFill" />
          <view class="article-info">
            <view class="article-title">{{ item.title }}</view>
            <view class="article-meta">
              <text class="author">{{ item.author }}</text>
              <text class="views">{{ item.click_num }} 次浏览</text>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getIndexData } from '@/api/index'
import type { Article } from '@/api/index'

const articles = ref<Article[]>([])

onMounted(async () => {
  try {
    const data = await getIndexData()
    articles.value = data.article
  } catch (error) {
    console.error('Failed to load index data:', error)
  }
})

function goDetail(id: number) {
  uni.navigateTo({ url: `/pages/news_detail/news_detail?id=${id}` })
}
</script>

<style scoped>
.index-page { background: #f5f5f5; min-height: 100vh; }
.banner-img { width: 100%; }
.article-section { padding: 24rpx; }
.section-title { font-size: 32rpx; font-weight: 600; margin-bottom: 20rpx; color: #333; }
.article-item { display: flex; background: #fff; border-radius: 12rpx; margin-bottom: 20rpx; overflow: hidden; }
.article-img { width: 200rpx; height: 160rpx; flex-shrink: 0; }
.article-info { flex: 1; padding: 16rpx 20rpx; display: flex; flex-direction: column; justify-content: space-between; }
.article-title { font-size: 28rpx; color: #333; font-weight: 500; line-height: 1.4; }
.article-meta { display: flex; justify-content: space-between; font-size: 22rpx; color: #999; }
</style>
