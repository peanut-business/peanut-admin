<template>
  <a-spin :loading="loading" style="width: 100%">
    <a-alert v-if="!canView" type="warning">
      {{ $t('channel.officialAccount.permissionDenied') }}
    </a-alert>
    <a-form
      v-else
      ref="formRef"
      :model="form"
      :rules="rules"
      layout="vertical"
      style="max-width: 560px; margin-top: 16px"
    >
      <a-alert type="info" :show-icon="true" style="margin-bottom: 16px">
        {{ $t('channel.openPlatform.notice') }}
      </a-alert>
      <a-form-item field="app_id" label="AppID">
        <a-input
          v-model="form.app_id"
          :max-length="128"
          :placeholder="$t('channel.field.appid.placeholder')"
        />
      </a-form-item>
      <a-form-item field="app_secret" label="AppSecret">
        <a-input-password
          v-model="form.app_secret"
          :max-length="255"
          :placeholder="
            form.app_secret_configured
              ? $t('channel.officialAccount.secretMaskedPlaceholder')
              : $t('channel.field.secret.placeholder')
          "
        />
      </a-form-item>
      <a-form-item>
        <a-button
          v-permission="['setting/open-platform/save']"
          type="primary"
          :loading="submitLoading"
          @click="handleSubmit"
        >
          {{ $t('channel.operation.save') }}
        </a-button>
      </a-form-item>
    </a-form>
  </a-spin>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import { hasPermission } from '@/hooks/permission';
  import {
    getOpenPlatformConfig,
    saveOpenPlatformConfig,
    type OpenPlatformConfig,
    type OpenPlatformConfigForm,
  } from '@/api/official-account';

  const { t } = useI18n();
  const canView = computed(() => hasPermission('setting/open-platform/config'));
  const loading = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();
  const form = reactive<OpenPlatformConfig>({
    app_id: '',
    app_secret: '',
    app_secret_configured: false,
  });
  const rules = {
    app_id: [
      { required: true, message: t('channel.field.appid.required') },
    ],
    app_secret: [
      { required: true, message: t('channel.field.secret.required') },
    ],
  };

  const fetchData = async () => {
    if (!canView.value) return;
    loading.value = true;
    try {
      const { data } = await getOpenPlatformConfig();
      Object.assign(form, data);
    } finally {
      loading.value = false;
    }
  };
  onMounted(fetchData);

  const handleSubmit = async () => {
    const errors = await formRef.value?.validate();
    if (errors) return;
    submitLoading.value = true;
    try {
      const data: OpenPlatformConfigForm = {
        app_id: form.app_id.trim(),
        app_secret: form.app_secret.trim(),
      };
      await saveOpenPlatformConfig(data);
      await fetchData();
      Message.success(t('channel.tip.success'));
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default { name: 'OpenPlatform' };
</script>
