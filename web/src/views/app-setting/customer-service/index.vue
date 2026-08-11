<template>
  <div class="container">
    <Breadcrumb
      :items="['menu.appSetting', 'menu.appSetting.customerService']"
    />
    <el-card
      v-loading="loading"
      class="general-card"
      :header="$t('menu.appSetting.customerService')"
    >
      <el-form :model="form" label-position="top" style="max-width: 560px">
        <el-form-item :label="$t('customerService.field.qrCode')">
          <el-upload
            class="qr-upload"
            :action="uploadAction"
            :headers="uploadHeaders"
            :show-file-list="false"
            accept="image/*"
            :on-success="onQrSuccess"
            :on-error="onQrError"
          >
            <div class="qr-uploader">
              <img v-if="form.qr_code" :src="form.qr_code" alt="qr" />
              <el-icon v-else><Plus /></el-icon>
            </div>
          </el-upload>
        </el-form-item>
        <el-form-item :label="$t('customerService.field.wechat')">
          <el-input
            v-model="form.wechat"
            :placeholder="$t('customerService.field.wechat.placeholder')"
          />
        </el-form-item>
        <el-form-item :label="$t('customerService.field.phone')">
          <el-input
            v-model="form.phone"
            :placeholder="$t('customerService.field.phone.placeholder')"
          />
        </el-form-item>
        <el-form-item :label="$t('customerService.field.serviceTime')">
          <el-input
            v-model="form.service_time"
            :placeholder="$t('customerService.field.serviceTime.placeholder')"
          />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            :loading="submitLoading"
            @click="handleSubmit"
          >
            {{ $t('customerService.operation.save') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { ElMessage, type UploadProps } from 'element-plus';
  import { Plus } from '@element-plus/icons-vue';
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

  const onQrSuccess: UploadProps['onSuccess'] = (response) => {
    const result = response as
      | { code: number; msg: string; data: { url: string } }
      | undefined;
    if (!result || result.code !== 20000) {
      ElMessage.error(
        result?.msg || t('customerService.tip.uploadFail')
      );
      return;
    }
    form.qr_code = result.data.url;
    ElMessage.success(t('customerService.tip.uploadSuccess'));
  };

  const onQrError: UploadProps['onError'] = () => {
    ElMessage.error(t('customerService.tip.uploadFail'));
  };

  const handleSubmit = async () => {
    submitLoading.value = true;
    try {
      await saveCustomerServiceConfig({ ...form });
      ElMessage.success(t('customerService.tip.success'));
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
    width: 148px;
    height: 148px;
    overflow: hidden;
    border: 1px dashed var(--el-border-color);
    border-radius: 6px;

    img {
      max-width: 100%;
      max-height: 100%;
    }
  }
</style>
