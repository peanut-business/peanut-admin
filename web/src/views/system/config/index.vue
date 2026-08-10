<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.config']" />
    <el-card class="general-card">
      <template #header>{{ $t('menu.system.config') }}</template>
      <div v-loading="loading" style="width: 100%">
        <el-form
          ref="formRef"
          :model="form"
          :rules="rules"
          label-position="top"
          style="max-width: 560px"
        >
          <el-form-item prop="name" :label="$t('systemConfig.field.name')">
            <el-input
              v-model="form.name"
              :placeholder="$t('systemConfig.field.name.placeholder')"
            />
          </el-form-item>
          <el-form-item prop="logo" :label="$t('systemConfig.field.logo')">
            <el-input
              v-model="form.logo"
              :placeholder="$t('systemConfig.field.logo.placeholder')"
            />
          </el-form-item>
          <el-form-item
            prop="favicon"
            :label="$t('systemConfig.field.favicon')"
          >
            <el-input
              v-model="form.favicon"
              :placeholder="$t('systemConfig.field.favicon.placeholder')"
            />
          </el-form-item>
          <el-form-item
            prop="copyright"
            :label="$t('systemConfig.field.copyright')"
          >
            <el-input
              v-model="form.copyright"
              :placeholder="$t('systemConfig.field.copyright.placeholder')"
            />
          </el-form-item>
          <el-form-item prop="icp" :label="$t('systemConfig.field.icp')">
            <el-input
              v-model="form.icp"
              :placeholder="$t('systemConfig.field.icp.placeholder')"
            />
          </el-form-item>
          <el-form-item>
            <el-button
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
            >
              {{ $t('systemConfig.operation.save') }}
            </el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { ElMessage } from 'element-plus';
  import type { FormInstance } from 'element-plus';
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
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;
    submitLoading.value = true;
    try {
      await saveWebsiteConfig({ ...form });
      ElMessage.success(t('systemConfig.tip.success'));
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
