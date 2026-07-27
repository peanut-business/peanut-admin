<template>
  <div class="container">
    <Breadcrumb
      :items="['menu.appSetting', 'menu.appSetting.customerService']"
    />
    <a-card class="general-card" :title="$t('menu.appSetting.customerService')">
      <a-spin :loading="loading" style="width: 100%">
        <a-form :model="form" layout="vertical" style="max-width: 560px">
          <a-form-item :label="$t('customerService.field.qrCode')">
            <a-upload
              :action="uploadAction"
              :headers="uploadHeaders"
              :show-file-list="false"
              list-type="picture-card"
              accept="image/*"
              @success="onQrSuccess"
              @error="onQrError"
            >
              <template #upload-button>
                <div class="qr-uploader">
                  <img v-if="form.qr_code" :src="form.qr_code" alt="qr" />
                  <icon-plus v-else />
                </div>
              </template>
            </a-upload>
          </a-form-item>
          <a-form-item :label="$t('customerService.field.wechat')">
            <a-input
              v-model="form.wechat"
              :placeholder="$t('customerService.field.wechat.placeholder')"
            />
          </a-form-item>
          <a-form-item :label="$t('customerService.field.phone')">
            <a-input
              v-model="form.phone"
              :placeholder="$t('customerService.field.phone.placeholder')"
            />
          </a-form-item>
          <a-form-item :label="$t('customerService.field.serviceTime')">
            <a-input
              v-model="form.service_time"
              :placeholder="$t('customerService.field.serviceTime.placeholder')"
            />
          </a-form-item>
          <a-form-item>
            <a-button
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
            >
              {{ $t('customerService.operation.save') }}
            </a-button>
          </a-form-item>
        </a-form>
      </a-spin>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FileItem } from '@arco-design/web-vue/es/upload/interfaces';
  import useLoading from '@/hooks/loading';
  import { getToken } from '@/utils/auth';
  import {
    getCustomerServiceConfig,
    saveCustomerServiceConfig,
    type CustomerServiceConfig,
  } from '@/api/app';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const submitLoading = ref(false);

  const uploadAction = '/api/admin/upload/image';
  const uploadHeaders = computed(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });

  const form = reactive<CustomerServiceConfig>({
    qr_code: '',
    wechat: '',
    phone: '',
    service_time: '',
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getCustomerServiceConfig();
      Object.assign(form, data);
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const onQrSuccess = (fileItem: FileItem) => {
    const res = fileItem.response as
      | { code: number; msg: string; data: { url: string } }
      | undefined;
    if (!res || res.code !== 20000) {
      Message.error(res?.msg || t('customerService.tip.uploadFail'));
      return;
    }
    form.qr_code = res.data.url;
    Message.success(t('customerService.tip.uploadSuccess'));
  };
  const onQrError = () => {
    Message.error(t('customerService.tip.uploadFail'));
  };

  const handleSubmit = async () => {
    submitLoading.value = true;
    try {
      await saveCustomerServiceConfig({ ...form });
      Message.success(t('customerService.tip.success'));
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'AppSettingCustomerService',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .qr-uploader {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;

    img {
      max-width: 100%;
      max-height: 100%;
    }
  }
</style>
