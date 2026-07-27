<template>
  <view class="user-page">
    <view class="user-header">
      <view v-if="isLoggedIn" class="user-info" @click="goUserData">
        <image :src="userInfo.avatar || '/static/avatar.png'" class="avatar" />
        <view class="info">
          <view class="nickname">{{ userInfo.nickname || '未设置昵称' }}</view>
          <view class="mobile">{{ userInfo.mobile || '未绑定手机' }}</view>
        </view>
      </view>
      <view v-else class="login-prompt" @click="goLogin">
        <text>点击登录</text>
      </view>
    </view>

    <view class="wallet-card" v-if="isLoggedIn" @click="goWallet">
      <view class="wallet-item">
        <view class="amount">{{ userInfo.balance || '0.00' }}</view>
        <view class="label">余额</view>
      </view>
      <view class="wallet-item">
        <view class="amount">{{ userInfo.points || 0 }}</view>
        <view class="label">积分</view>
      </view>
    </view>

    <view class="menu-list">
      <view class="menu-item" @click="goCollection">
        <text class="menu-icon">⭐</text>
        <text class="menu-title">我的收藏</text>
      </view>
      <view class="menu-item" @click="goSettings">
        <text class="menu-icon">⚙️</text>
        <text class="menu-title">个人设置</text>
      </view>
      <view class="menu-item" @click="goCustomerService">
        <text class="menu-icon">💬</text>
        <text class="menu-title">联系客服</text>
      </view>
      <view class="menu-item" @click="goAbout">
        <text class="menu-icon">ℹ️</text>
        <text class="menu-title">关于我们</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useUserStore } from '@/store/user'
import { getUserCenter } from '@/api/user'

const userStore = useUserStore()
const isLoggedIn = computed(() => userStore.isLoggedIn)
const userInfo = computed(() => userStore.userInfo)

onMounted(async () => {
  if (isLoggedIn.value) {
    try {
      const data = await getUserCenter()
      userStore.setUserInfo(data)
    } catch (error) {
      console.error('Failed to load user center:', error)
    }
  }
})

function goLogin() { uni.navigateTo({ url: '/pages/login/login' }) }
function goUserData() { uni.navigateTo({ url: '/pages/user_data/user_data' }) }
function goWallet() { uni.navigateTo({ url: '/packages/pages/user_wallet/user_wallet' }) }
function goCollection() { uni.navigateTo({ url: '/pages/collection/collection' }) }
function goSettings() { uni.navigateTo({ url: '/pages/user_set/user_set' }) }
function goCustomerService() { uni.navigateTo({ url: '/pages/customer_service/customer_service' }) }
function goAbout() { uni.navigateTo({ url: '/pages/as_us/as_us' }) }
</script>

<style scoped>
.user-page { background: #f5f5f5; min-height: 100vh; }
.user-header { background: linear-gradient(135deg, #2979ff, #1d54c4); padding: 60rpx 40rpx 40rpx; }
.user-info { display: flex; align-items: center; }
.avatar { width: 120rpx; height: 120rpx; border-radius: 50%; border: 4rpx solid rgba(255,255,255,0.5); }
.info { margin-left: 24rpx; }
.nickname { font-size: 34rpx; font-weight: 600; color: #fff; }
.mobile { font-size: 26rpx; color: rgba(255,255,255,0.8); margin-top: 8rpx; }
.login-prompt { color: #fff; font-size: 32rpx; font-weight: 600; }
.wallet-card { display: flex; background: #fff; margin: 24rpx; border-radius: 16rpx; padding: 30rpx; }
.wallet-item { flex: 1; text-align: center; }
.amount { font-size: 36rpx; font-weight: 600; color: #333; }
.label { font-size: 24rpx; color: #999; margin-top: 8rpx; }
.menu-list { background: #fff; margin: 0 24rpx; border-radius: 16rpx; overflow: hidden; }
.menu-item { display: flex; align-items: center; padding: 30rpx 24rpx; border-bottom: 1rpx solid #f5f5f5; }
.menu-icon { font-size: 36rpx; margin-right: 20rpx; }
.menu-title { font-size: 28rpx; color: #333; }
</style>
