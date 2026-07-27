<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.config']" />
    <a-card class="general-card" :title="$t('menu.system.config')">
      <a-spin :loading="loading" style="width: 100%">
        <a-form
          ref="formRef"
          :model="form"
          :rules="rules"
          layout="vertical"
          style="max-width: 560px"
        >
          <a-form-item field="name" :label="$t('systemConfig.field.name')">
            <a-input
              v-model="form.name"
              :placeholder="$t('systemConfig.field.name.placeholder')"
            />
          </a-form-item>
          <a-form-item field="logo" :label="$t('systemConfig.field.logo')">
            <a-input
              v-model="form.logo"
              :placeholder="$t('systemConfig.field.logo.placeholder')"
            />
          </a-form-item>
          <a-form-item
            field="favicon"
            :label="$t('systemConfig.field.favicon')"
          >
            <a-input
              v-model="form.favicon"
              :placeholder="$t('systemConfig.field.favicon.placeholder')"
            />
          </a-form-item>
          <a-form-item
            field="copyright"
            :label="$t('systemConfig.field.copyright')"
          >
            <a-input
              v-model="form.copyright"
              :placeholder="$t('systemConfig.field.copyright.placeholder')"
            />
          </a-form-item>
          <a-form-item field="icp" :label="$t('systemConfig.field.icp')">
            <a-input
              v-model="form.icp"
              :placeholder="$t('systemConfig.field.icp.placeholder')"
            />
          </a-form-item>
          <a-form-item>
            <a-button
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
            >
              {{ $t('systemConfig.operation.save') }}
            </a-button>
          </a-form-item>
        </a-form>
      </a-spin>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getWebsiteConfig,
    saveWebsiteConfig,
    type WebsiteConfig,
  } from '@/api/system/config';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const form = reactive<WebsiteConfig>({
    name: '',
    logo: '',
    favicon: '',
    copyright: '',
    icp: '',
  });

  const rules = {
    name: [{ required: true, message: t('systemConfig.field.name.required') }],
  };

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getWebsiteConfig();
      Object.assign(form, data);
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      await saveWebsiteConfig({ ...form });
      Message.success(t('systemConfig.tip.success'));
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemConfig',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
