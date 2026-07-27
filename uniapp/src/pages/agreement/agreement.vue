<template>
  <view class="page">
    <view class="policy-content" v-if="policy">
      <view class="policy-title">{{ policy.title }}</view>
      <rich-text :nodes="policy.content" class="policy-body" />
    </view>
    <view v-else class="loading">加载中...</view>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPolicy } from '@/api/index'
import type { PolicyData } from '@/api/index'

const policy = ref<PolicyData | null>(null)

onMounted(() => {
  const pages = getCurrentPages()
  const page = pages[pages.length - 1] as any
  const type = page?.options?.type || 'service'
  loadPolicy(type)
})

async function loadPolicy(type: 'privacy' | 'service') {
  try {
    policy.value = await getPolicy(type)
  } catch (error) {
    console.error('Failed to load policy:', error)
  }
}
</script>

<style scoped>
.page { background: #fff; min-height: 100vh; }
.loading { text-align: center; padding: 80rpx; color: #999; }
.policy-content { padding: 40rpx; }
.policy-title { font-size: 36rpx; font-weight: 700; color: #333; margin-bottom: 30rpx; }
.policy-body { font-size: 28rpx; color: #555; line-height: 1.8; }
</style>
