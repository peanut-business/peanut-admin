<template>
  <view class="page">
    <view class="contact-section">
      <image src="/static/customer-service.png" class="cs-img" mode="aspectFit" />
      <view class="cs-title">联系客服</view>
      <view class="cs-desc">如有问题请通过以下方式联系我们</view>
    </view>

    <view v-if="csInfo" class="info-list">
      <view v-if="csInfo.phone" class="info-item" @click="callPhone(csInfo.phone)">
        <text class="info-icon">📞</text>
        <text class="info-text">{{ csInfo.phone }}</text>
        <text class="action">拨打</text>
      </view>
      <view v-if="csInfo.wechat" class="info-item">
        <text class="info-icon">💬</text>
        <text class="info-text">微信：{{ csInfo.wechat }}</text>
      </view>
      <view v-if="csInfo.qq" class="info-item">
        <text class="info-icon">🐧</text>
        <text class="info-text">QQ：{{ csInfo.qq }}</text>
      </view>
      <view v-if="csInfo.email" class="info-item">
        <text class="info-icon">✉️</text>
        <text class="info-text">{{ csInfo.email }}</text>
      </view>
      <view v-if="csInfo.work_time" class="info-item">
        <text class="info-icon">🕐</text>
        <text class="info-text">{{ csInfo.work_time }}</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { http } from '@/utils/request'

interface CsInfo {
  phone?: string
  wechat?: string
  qq?: string
  email?: string
  work_time?: string
}

const csInfo = ref<CsInfo | null>(null)

onMounted(async () => {
  try {
    const data = await http.get<CsInfo>('api/index/config', undefined, false)
    // customer_service config is nested under config response in some implementations
    csInfo.value = (data as any).customer_service || {}
  } catch (error) {
    console.error('Failed to load customer service info:', error)
  }
})

function callPhone(phone: string) {
  uni.makePhoneCall({ phoneNumber: phone })
}
</script>

<style scoped>
.page { background: #f5f5f5; min-height: 100vh; }
.contact-section { display: flex; flex-direction: column; align-items: center; padding: 80rpx 40rpx 40rpx; background: #fff; }
.cs-img { width: 200rpx; height: 200rpx; }
.cs-title { font-size: 36rpx; font-weight: 600; color: #333; margin-top: 24rpx; }
.cs-desc { font-size: 26rpx; color: #999; margin-top: 12rpx; }
.info-list { background: #fff; margin-top: 20rpx; }
.info-item { display: flex; align-items: center; padding: 28rpx 32rpx; border-bottom: 1rpx solid #f5f5f5; }
.info-icon { font-size: 36rpx; margin-right: 20rpx; }
.info-text { flex: 1; font-size: 28rpx; color: #333; }
.action { font-size: 26rpx; color: #2979ff; }
</style>
