<template>
  <a-form-item :label="$t('channel.menu.type')">
    <a-select v-model="type">
      <a-option value="click">{{ $t('channel.menu.typeClick') }}</a-option>
      <a-option value="view">{{ $t('channel.menu.typeView') }}</a-option>
      <a-option value="miniprogram">
        {{ $t('channel.menu.typeMiniprogram') }}
      </a-option>
    </a-select>
  </a-form-item>
  <a-form-item v-if="type === 'click'" :label="$t('channel.menu.key')">
    <a-input v-model="keyValue" :max-length="128" />
  </a-form-item>
  <a-form-item v-if="type === 'view'" :label="$t('channel.menu.url')">
    <a-input v-model="url" :max-length="1024" />
  </a-form-item>
  <template v-if="type === 'miniprogram'">
    <a-form-item :label="$t('channel.menu.fallbackUrl')">
      <a-input v-model="url" :max-length="1024" />
    </a-form-item>
    <a-form-item :label="$t('channel.menu.appId')">
      <a-input v-model="appid" :max-length="128" />
    </a-form-item>
    <a-form-item :label="$t('channel.menu.pagePath')">
      <a-input v-model="pagepath" :max-length="512" />
    </a-form-item>
  </template>
</template>

<script lang="ts" setup>
  import { computed } from 'vue';
  import type {
    OfficialAccountMenuItem,
    OfficialAccountMenuType,
  } from '@/api/official-account';

  const props = defineProps<{ modelValue: OfficialAccountMenuItem }>();
  const emit = defineEmits<{
    (event: 'update:modelValue', value: OfficialAccountMenuItem): void;
  }>();

  const update = <K extends keyof OfficialAccountMenuItem>(
    key: K,
    value: OfficialAccountMenuItem[K]
  ) => {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
  };

  const type = computed<OfficialAccountMenuType>({
    get: () => props.modelValue.type || 'click',
    set: (value) => update('type', value),
  });
  const keyValue = computed({
    get: () => props.modelValue.key || '',
    set: (value: string) => update('key', value),
  });
  const url = computed({
    get: () => props.modelValue.url || '',
    set: (value: string) => update('url', value),
  });
  const appid = computed({
    get: () => props.modelValue.appid || '',
    set: (value: string) => update('appid', value),
  });
  const pagepath = computed({
    get: () => props.modelValue.pagepath || '',
    set: (value: string) => update('pagepath', value),
  });
</script>

<script lang="ts">
  export default { name: 'MenuLeafFields' };
</script>
