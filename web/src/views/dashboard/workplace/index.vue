<template>
  <div class="container">
    <a-card class="general-card welcome-card">
      <a-space direction="vertical" :size="16" fill>
        <a-space :size="16" align="center">
          <a-avatar :size="56" :image-url="userStore.avatar">
            <icon-user />
          </a-avatar>
          <div>
            <div class="welcome-title">
              {{ $t('workplace.welcome') }}{{ displayName }}
            </div>
            <div class="welcome-subtitle">
              {{ $t('workplace.subtitle') }}
            </div>
          </div>
        </a-space>
        <a-alert type="normal">{{ $t('workplace.tip') }}</a-alert>
      </a-space>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed } from 'vue';
  import { useUserStore } from '@/store';

  const userStore = useUserStore();
  const displayName = computed(() => userStore.name || '');
</script>

<script lang="ts">
  export default {
    name: 'Workplace',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 20px;
  }

  .welcome-title {
    color: var(--color-text-1);
    font-size: 20px;
    font-weight: 500;
  }

  .welcome-subtitle {
    margin-top: 4px;
    color: var(--color-text-3);
    font-size: 13px;
  }
</style>
