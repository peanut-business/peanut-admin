<template>
  <view class="page">
    <view class="logo-area">
      <image :src="logo || '/static/logo.png'" class="logo" mode="aspectFit" />
      <view class="app-name">{{ appName }}</view>
    </view>

    <view class="section">
      <view class="section-title">关于我们</view>
      <view class="section-body">{{ intro }}</view>
    </view>

    <view class="links">
      <view class="link-item" @click="goPrivacy">隐私政策</view>
      <view class="link-item" @click="goService">用户协议</view>
    </view>

    <view class="version">版本 {{ version }}</view>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getConfig } from '@/api/index'

const logo = ref('')
const appName = ref('peanut')
const version = ref('1.0.0')
const intro = ref('感谢使用本产品，我们致力于为您提供最优质的服务体验。')

onMounted(async () => {
  try {
    const config = await getConfig()
    appName.value = config.website.shop_name
    logo.value = config.website.shop_logo
    version.value = config.version
  } catch (error) {
    console.error('Failed to load config:', error)
  }
})

function goPrivacy() { uni.navigateTo({ url: '/pages/agreement/agreement?type=privacy' }) }
function goService() { uni.navigateTo({ url: '/pages/agreement/agreement?type=service' }) }
</script>

<style scoped>
.page { background: #f5f5f5; min-height: 100vh; }
.logo-area { display: flex; flex-direction: column; align-items: center; padding: 80rpx 40rpx 40rpx; background: #fff; }
.logo { width: 160rpx; height: 160rpx; border-radius: 32rpx; }
.app-name { font-size: 36rpx; font-weight: 700; color: #333; margin-top: 20rpx; }
.section { background: #fff; margin-top: 20rpx; padding: 30rpx 32rpx; }
.section-title { font-size: 30rpx; font-weight: 600; color: #333; margin-bottom: 16rpx; }
.section-body { font-size: 28rpx; color: #666; line-height: 1.8; }
.links { background: #fff; margin-top: 20rpx; }
.link-item { padding: 28rpx 32rpx; border-bottom: 1rpx solid #f5f5f5; font-size: 28rpx; color: #333; }
.version { text-align: center; padding: 60rpx; font-size: 24rpx; color: #ccc; }
</style>
