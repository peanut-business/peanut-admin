<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.storage']" />
    <el-card class="general-card">
      <template #header>{{ $t('menu.system.storage') }}</template>
      <el-table row-key="engine" :loading="loading" :data="renderData" border>
        <el-table-column :label="$t('systemStorage.column.name')" prop="name" />
        <el-table-column :label="$t('systemStorage.column.path')" prop="path" />
        <el-table-column :label="$t('systemStorage.column.status')">
          <template #default="{ row: record }">
            <el-tag v-if="record.status === 1" type="success">
              {{ $t('systemStorage.status.using') }}
            </el-tag>
            <el-tag v-else type="info">
              {{ $t('systemStorage.status.unused') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column
          :label="$t('systemStorage.column.operations')"
          :width="140"
        >
          <template #default="{ row: record }">
            <el-button link size="small" @click="openConfig(record)">
              {{ $t('systemStorage.operation.config') }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
    <el-dialog
      v-model="modalVisible"
      :title="$t('systemStorage.modal.title')"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
        <template v-if="form.engine === 'local'">
          <el-alert>{{ $t('systemStorage.tip.localOnly') }}</el-alert>
        </template>
        <template v-else>
          <el-form-item prop="bucket" :label="$t('systemStorage.field.bucket')">
            <el-input
              v-model="form.bucket"
              :placeholder="$t('systemStorage.field.bucket.placeholder')"
            />
          </el-form-item>
          <el-form-item
            v-if="form.engine === 'qcloud'"
            prop="region"
            :label="$t('systemStorage.field.region')"
          >
            <el-input
              v-model="form.region"
              :placeholder="$t('systemStorage.field.region.placeholder')"
            />
          </el-form-item>
          <el-form-item
            prop="access_key"
            :label="$t('systemStorage.field.accessKey')"
          >
            <el-input
              v-model="form.access_key"
              :placeholder="$t('systemStorage.field.accessKey.placeholder')"
            />
          </el-form-item>
          <el-form-item
            prop="secret_key"
            :label="$t('systemStorage.field.secretKey')"
          >
            <el-input
              v-model="form.secret_key"
              :placeholder="$t('systemStorage.field.secretKey.placeholder')"
            />
          </el-form-item>
          <el-form-item prop="domain" :label="$t('systemStorage.field.domain')">
            <el-input
              v-model="form.domain"
              :placeholder="$t('systemStorage.field.domain.placeholder')"
            />
          </el-form-item>
        </template>
        <el-form-item prop="status" :label="$t('systemStorage.field.status')">
          <el-switch
            v-model="form.status"
            :active-value="1"
            :inactive-value="0"
          />
        </el-form-item>
      </el-form>
      <template #footer
        ><el-button @click="modalVisible = false">{{
          $t('systemStorage.operation.cancel')
        }}</el-button
        ><el-button
          type="primary"
          :loading="submitLoading"
          @click="handleSubmit"
          >{{ $t('systemStorage.operation.save') }}</el-button
        ></template
      >
    </el-dialog>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { ElMessage } from 'element-plus';
  import type { FormInstance } from 'element-plus';
  import useLoading from '@/hooks/loading';
  import {
    getStorageList,
    getStorageDetail,
    setupStorage,
    type StorageEngineItem,
    type StorageSetupForm,
  } from '@/api/system/storage';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const submitLoading = ref(false);
  const renderData = ref<StorageEngineItem[]>([]);
  const modalVisible = ref(false);
  const formRef = ref<FormInstance>();

  const form = reactive<StorageSetupForm>({
    engine: 'local',
    status: 0,
    bucket: '',
    region: '',
    access_key: '',
    secret_key: '',
    domain: '',
  });

  const required = [
    { required: true, message: t('systemStorage.tip.required') },
  ];
  const rules = {
    bucket: required,
    access_key: required,
    secret_key: required,
    domain: required,
  };

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getStorageList();
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const openConfig = async (record: StorageEngineItem) => {
    Object.assign(form, {
      engine: record.engine,
      status: record.status,
      bucket: '',
      region: '',
      access_key: '',
      secret_key: '',
      domain: '',
    });
    const { data } = await getStorageDetail(record.engine);
    Object.assign(form, data);
    form.engine = record.engine;
    formRef.value?.clearValidate();
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    if (form.engine !== 'local') {
      const valid = await formRef.value?.validate().catch(() => false);
      if (!valid) return false;
    }
    submitLoading.value = true;
    try {
      await setupStorage({ ...form });
      ElMessage.success(t('systemStorage.tip.success'));
      modalVisible.value = false;
      await fetchData();
      return true;
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemStorage',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
