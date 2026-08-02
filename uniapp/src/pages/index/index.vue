<template>
  <view class="index-page" :style="pageStyle">
    <swiper v-if="bannerItems.length" class="banner" :autoplay="true" :interval="4000" circular>
      <swiper-item v-for="item in bannerItems" :key="`${item.name}-${item.image}`">
        <view class="banner-item" @click="executeDecorationLink(item.link)">
          <image v-if="item.image" :src="item.image" mode="aspectFill" class="banner-img" />
          <view v-if="item.name" class="banner-title">{{ item.name }}</view>
        </view>
      </swiper-item>
    </swiper>

    <view v-if="navItems.length" class="nav-grid" :style="navGridStyle">
      <view v-for="item in navItems" :key="`${item.name}-${item.image}`" class="nav-item" @click="executeDecorationLink(item.link)">
        <image v-if="item.image" :src="item.image" mode="aspectFill" class="nav-image" />
        <view class="nav-name">{{ item.name }}</view>
      </view>
    </view>

    <swiper v-if="middleBannerItems.length" class="middle-banner" :autoplay="true" :interval="5000" circular>
      <swiper-item v-for="item in middleBannerItems" :key="`${item.name}-${item.image}`">
        <view class="banner-item" @click="executeDecorationLink(item.link)">
          <image v-if="item.image" :src="item.image" mode="aspectFill" class="middle-banner-img" />
          <view v-if="item.name" class="banner-title">{{ item.name }}</view>
        </view>
      </swiper-item>
    </swiper>

    <view class="article-section">
      <view class="section-title">最新资讯</view>
      <view class="article-list">
        <view v-for="item in articles" :key="item.id" class="article-item" @click="goDetail(item.id)">
          <image :src="item.image" class="article-img" mode="aspectFill" />
          <view class="article-info">
            <view class="article-title">{{ item.title }}</view>
            <view class="article-meta">
              <text class="author">{{ item.author }}</text>
              <text class="views">{{ item.click_num ?? item.click ?? 0 }} 次浏览</text>
            </view>
          </view>
        </view>
      </view>
    </view>
    <DecorationTabbar />
  </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getIndexData, type Article } from '@/api/index'
import { useAppStore } from '@/store/app'
import DecorationTabbar from '@/components/DecorationTabbar.vue'
import {
  executeDecorationLink,
  getDecorationComponent,
  getDecorationItems,
  getDecorationTheme,
  type DecorationPage,
  type DecorationItem,
} from '@/utils/decoration'

const appStore = useAppStore()
const articles = ref<Article[]>([])
const decorate = ref<DecorationPage | null>(null)
const theme = computed(() => getDecorationTheme(appStore.config?.theme))

const component = (name: string) => getDecorationComponent(decorate.value, name)
const items = (name: string) => getDecorationItems(component(name)).filter((item) => item.is_show === undefined || item.is_show === 1)
const enabled = (name: string) => Number(component(name)?.content?.enabled ?? 0) === 1
const bannerItems = computed<DecorationItem[]>(() => enabled('banner') ? items('banner') : [])
const navItems = computed<DecorationItem[]>(() => enabled('nav') ? items('nav') : [])
const middleBannerItems = computed<DecorationItem[]>(() => enabled('middle-banner') ? items('middle-banner') : [])

const meta = computed(() => {
  const pageMeta = Array.isArray(decorate.value?.meta)
    ? decorate.value?.meta.find((item) => item.name === 'page-meta')
    : undefined
  return pageMeta?.content || {}
})
const pageStyle = computed(() => {
  const style: Record<string, string> = {
    '--theme-primary': theme.value?.themeColor1 || '#2979ff',
    '--theme-secondary': theme.value?.themeColor2 || '#1d54c4',
  }
  if (meta.value.bg_type === 1 && typeof meta.value.bg_color === 'string') style.backgroundColor = meta.value.bg_color
  if (meta.value.bg_type === 2 && typeof meta.value.bg_image === 'string' && meta.value.bg_image) style.backgroundImage = `url(${meta.value.bg_image})`
  return style
})
const navGridStyle = computed(() => {
  const perLine = Number(component('nav')?.content?.per_line || 5)
  return { gridTemplateColumns: `repeat(${Math.min(5, Math.max(1, perLine))}, 1fr)` }
})

async function loadHome() {
  try {
    await appStore.loadConfig()
    const data = await getIndexData()
    articles.value = data.article
    decorate.value = data.decorate
  } catch (error) {
    console.error('Failed to load index data:', error)
  }
}

onShow(loadHome)

function goDetail(id: number) {
  uni.navigateTo({ url: `/pages/news_detail/news_detail?id=${id}` })
}
</script>

<style scoped>
.index-page { min-height: 100vh; padding-bottom: calc(120rpx + env(safe-area-inset-bottom)); background: #f5f5f5; background-size: cover; background-position: center; box-sizing: border-box; }
.banner, .middle-banner { width: 100%; height: 360rpx; }
.middle-banner { margin: 20rpx 24rpx 0; width: calc(100% - 48rpx); height: 220rpx; border-radius: 12rpx; overflow: hidden; }
.banner-item { position: relative; width: 100%; height: 100%; background: #eee; }
.banner-img, .middle-banner-img { width: 100%; height: 100%; }
.banner-title { position: absolute; left: 24rpx; bottom: 20rpx; color: #fff; font-size: 28rpx; text-shadow: 0 1rpx 6rpx rgb(0 0 0 / 50%); }
.nav-grid { display: grid; gap: 12rpx; margin: 20rpx 24rpx; padding: 20rpx; background: #fff; border-radius: 12rpx; }
.nav-item { display: flex; flex-direction: column; align-items: center; min-width: 0; }
.nav-image { width: 72rpx; height: 72rpx; border-radius: 10rpx; }
.nav-name { margin-top: 8rpx; color: #333; font-size: 24rpx; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.article-section { padding: 24rpx; }
.section-title { font-size: 32rpx; font-weight: 600; margin-bottom: 20rpx; color: var(--theme-primary); }
.article-item { display: flex; background: #fff; border-radius: 12rpx; margin-bottom: 20rpx; overflow: hidden; }
.article-img { width: 200rpx; height: 160rpx; flex-shrink: 0; }
.article-info { flex: 1; padding: 16rpx 20rpx; display: flex; flex-direction: column; justify-content: space-between; }
.article-title { font-size: 28rpx; color: #333; font-weight: 500; line-height: 1.4; }
.article-meta { display: flex; justify-content: space-between; font-size: 22rpx; color: #999; }
</style>
