<template>
  <view class="page">
    <view class="amount-select">
      <view class="label">选择充值金额</view>
      <view class="preset-amounts">
        <view
          v-for="preset in presets"
          :key="preset"
          class="preset-item"
          :class="{ active: amount === preset }"
          @click="amount = preset"
        >
          {{ preset }}元
        </view>
      </view>
      <view class="custom-amount">
        <input
          v-model.number="amount"
          type="digit"
          placeholder="或输入自定义金额"
          class="custom-input"
        />
      </view>
    </view>

    <view class="btn-area">
      <button class="btn-primary" :disabled="loading || !amount" @click="handleRecharge">
        {{ loading ? '处理中...' : `立即充值 ${amount || 0} 元` }}
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const loading = ref(false)
const amount = ref<number | null>(null)
const presets = [10, 50, 100, 200, 500, 1000]

async function handleRecharge() {
  if (!amount.value || amount.value <= 0)
    return uni.showToast({ title: '请输入有效金额', icon: 'none' })

  // Note: actual recharge would integrate payment SDK — placeholder for now
  uni.showToast({ title: '充值功能开发中', icon: 'none' })
}
</script>

<style scoped>
.page { background: #f5f5f5; min-height: 100vh; }
.amount-select { background: #fff; margin: 24rpx; border-radius: 16rpx; padding: 40rpx; }
.label { font-size: 30rpx; font-weight: 600; color: #333; margin-bottom: 30rpx; }
.preset-amounts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20rpx; }
.preset-item { background: #f5f5f5; border: 2rpx solid transparent; border-radius: 12rpx; padding: 24rpx; text-align: center; font-size: 28rpx; color: #333; }
.preset-item.active { background: #e6f4ff; border-color: #2979ff; color: #2979ff; font-weight: 600; }
.custom-amount { margin-top: 30rpx; }
.custom-input { width: 100%; height: 80rpx; background: #f5f5f5; border-radius: 12rpx; padding: 0 24rpx; font-size: 30rpx; color: #333; }
.btn-area { padding: 40rpx; }
.btn-primary { width: 100%; height: 90rpx; background: #2979ff; color: #fff; font-size: 32rpx; border-radius: 45rpx; border: none; }
.btn-primary[disabled] { opacity: 0.6; }
</style>
