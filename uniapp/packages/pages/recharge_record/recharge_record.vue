<template>
  <view class="page">
    <view v-if="logs.length === 0" class="empty">暂无充值记录</view>
    <view v-for="item in logs" :key="item.id" class="record-item">
      <view class="record-header">
        <view class="record-type">充值</view>
        <view class="record-amount">+{{ item.change_amount }}</view>
      </view>
      <view class="record-footer">
        <text class="record-time">{{ item.create_time }}</text>
        <text class="record-balance">余额: {{ item.left_amount }}</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getAccountLogs } from '@/api/shop'
import type { AccountLog } from '@/api/shop'

const logs = ref<AccountLog[]>([])

onMounted(async () => {
  try {
    const data = await getAccountLogs({ page_size: 50 })
    // Filter to recharge records only (change_type for recharge would be specific value)
    logs.value = data.lists.filter((log) => Number(log.change_amount) > 0)
  } catch (error) {
    console.error('Failed to load recharge records:', error)
  }
})
</script>

<style scoped>
.page { background: #f5f5f5; min-height: 100vh; padding: 24rpx; }
.empty { text-align: center; color: #999; padding: 120rpx 0; font-size: 28rpx; }
.record-item { background: #fff; border-radius: 12rpx; margin-bottom: 20rpx; padding: 30rpx; }
.record-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16rpx; }
.record-type { font-size: 28rpx; color: #666; }
.record-amount { font-size: 36rpx; font-weight: 600; color: #52c41a; }
.record-footer { display: flex; justify-content: space-between; font-size: 24rpx; color: #999; }
</style>
