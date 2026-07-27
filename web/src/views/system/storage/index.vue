<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.storage']" />
    <a-card class="general-card" :title="$t('menu.system.storage')">
      <a-table
        row-key="engine"
        :loading="loading"
        :data="renderData"
        :pagination="false"
        :bordered="false"
      >
        <template #columns>
          <a-table-column
            :title="$t('systemStorage.column.name')"
            data-index="name"
          />
          <a-table-column
            :title="$t('systemStorage.column.path')"
            data-index="path"
          />
          <a-table-column :title="$t('systemStorage.column.status')">
            <template #cell="{ record }">
              <a-tag v-if="record.status === 1" color="green">
                {{ $t('systemStorage.status.using') }}
              </a-tag>
              <a-tag v-else color="gray">
                {{ $t('systemStorage.status.unused') }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('systemStorage.column.operations')"
            :width="140"
          >
            <template #cell="{ record }">
              <a-button type="text" size="small" @click="openConfig(record)">
                {{ $t('systemStorage.operation.config') }}
              </a-button>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>
    <a-modal
      v-model:visible="modalVisible"
      :title="$t('systemStorage.modal.title')"
      :ok-text="$t('systemStorage.operation.save')"
      :cancel-text="$t('systemStorage.operation.cancel')"
      :confirm-loading="submitLoading"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <template v-if="form.engine === 'local'">
          <a-alert>{{ $t('systemStorage.tip.localOnly') }}</a-alert>
        </template>
        <template v-else>
          <a-form-item field="bucket" :label="$t('systemStorage.field.bucket')">
            <a-input
              v-model="form.bucket"
              :placeholder="$t('systemStorage.field.bucket.placeholder')"
            />
          </a-form-item>
          <a-form-item
            v-if="form.engine === 'qcloud'"
            field="region"
            :label="$t('systemStorage.field.region')"
          >
            <a-input
              v-model="form.region"
              :placeholder="$t('systemStorage.field.region.placeholder')"
            />
          </a-form-item>
          <a-form-item
            field="access_key"
            :label="$t('systemStorage.field.accessKey')"
          >
            <a-input
              v-model="form.access_key"
              :placeholder="$t('systemStorage.field.accessKey.placeholder')"
            />
          </a-form-item>
          <a-form-item
            field="secret_key"
            :label="$t('systemStorage.field.secretKey')"
          >
            <a-input
              v-model="form.secret_key"
              :placeholder="$t('systemStorage.field.secretKey.placeholder')"
            />
          </a-form-item>
          <a-form-item field="domain" :label="$t('systemStorage.field.domain')">
            <a-input
              v-model="form.domain"
              :placeholder="$t('systemStorage.field.domain.placeholder')"
            />
          </a-form-item>
        </template>
        <a-form-item field="status" :label="$t('systemStorage.field.status')">
          <a-switch
            v-model="form.status"
            :checked-value="1"
            :unchecked-value="0"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
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
      const err = await formRef.value?.validate();
      if (err) return false;
    }
    submitLoading.value = true;
    try {
      await setupStorage({ ...form });
      Message.success(t('systemStorage.tip.success'));
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
