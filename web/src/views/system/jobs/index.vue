<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.jobs']" />
    <a-card class="general-card" :title="$t('menu.system.jobs')">
      <a-row>
        <a-col :flex="1">
          <a-form
            :model="formModel"
            :label-col-props="{ span: 6 }"
            :wrapper-col-props="{ span: 18 }"
            label-align="left"
          >
            <a-row :gutter="16">
              <a-col :span="8">
                <a-form-item field="name" :label="$t('systemJobs.form.name')">
                  <a-input
                    v-model="formModel.name"
                    allow-clear
                    :placeholder="$t('systemJobs.form.name.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="code" :label="$t('systemJobs.form.code')">
                  <a-input
                    v-model="formModel.code"
                    allow-clear
                    :placeholder="$t('systemJobs.form.code.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="is_disable"
                  :label="$t('systemJobs.form.status')"
                >
                  <a-select
                    v-model="formModel.is_disable"
                    allow-clear
                    :options="statusOptions"
                    :placeholder="$t('systemJobs.form.status.placeholder')"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-form>
        </a-col>
        <a-divider style="height: 84px" direction="vertical" />
        <a-col :flex="'86px'" style="text-align: right">
          <a-space direction="vertical" :size="18">
            <a-button type="primary" @click="search">
              <template #icon><icon-search /></template>
              {{ $t('systemJobs.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('systemJobs.form.reset') }}
            </a-button>
          </a-space>
        </a-col>
      </a-row>
      <a-divider style="margin-top: 0" />
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-button type="primary" @click="handleAdd">
            <template #icon><icon-plus /></template>
            {{ $t('systemJobs.operation.create') }}
          </a-button>
        </a-col>
      </a-row>
      <a-table
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="renderData"
        :pagination="pagination"
        :bordered="{ cell: true }"
        @page-change="onPageChange"
      >
        <template #is_disable="{ record }">
          <a-switch
            :model-value="record.is_disable === 0"
            @change="(v) => handleStatus(record, v as boolean)"
          />
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('systemJobs.operation.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemJobs.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button type="text" status="danger" size="small">
                {{ $t('systemJobs.operation.delete') }}
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="modalVisible"
      :title="
        isEdit
          ? $t('systemJobs.modal.editTitle')
          : $t('systemJobs.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="name" :label="$t('systemJobs.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemJobs.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="code" :label="$t('systemJobs.field.code')">
          <a-input
            v-model="form.code"
            :placeholder="$t('systemJobs.field.code.placeholder')"
          />
        </a-form-item>
        <a-form-item field="sort" :label="$t('systemJobs.field.sort')">
          <a-input-number v-model="form.sort" :min="0" style="width: 160px" />
        </a-form-item>
        <a-form-item field="remark" :label="$t('systemJobs.field.remark')">
          <a-textarea
            v-model="form.remark"
            :placeholder="$t('systemJobs.field.remark.placeholder')"
            :max-length="500"
            show-word-limit
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref, reactive } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getJobsList,
    addJobs,
    editJobs,
    deleteJobs,
    updateJobsStatus,
    type JobsRecord,
    type JobsForm,
    type JobsListParams,
  } from '@/api/system/jobs';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<JobsRecord[]>([]);

  const generateFormModel = () => ({
    name: '',
    code: '',
    is_disable: '' as number | '',
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });

  const statusOptions = computed<SelectOptionData[]>(() => [
    { label: t('systemJobs.status.enabled'), value: 0 },
    { label: t('systemJobs.status.disabled'), value: 1 },
  ]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemJobs.columns.id'), dataIndex: 'id', width: 80 },
    { title: t('systemJobs.columns.name'), dataIndex: 'name' },
    { title: t('systemJobs.columns.code'), dataIndex: 'code', width: 160 },
    { title: t('systemJobs.columns.sort'), dataIndex: 'sort', width: 80 },
    { title: t('systemJobs.columns.remark'), dataIndex: 'remark' },
    {
      title: t('systemJobs.columns.status'),
      slotName: 'is_disable',
      width: 90,
    },
    {
      title: t('systemJobs.columns.operations'),
      slotName: 'operations',
      width: 160,
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: JobsListParams = {
        ...formModel.value,
        page_no: page,
        page_size: pagination.pageSize,
      };
      const { data } = await getJobsList(params);
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const search = () => fetchData(1);
  const onPageChange = (current: number) => fetchData(current);
  const reset = () => {
    formModel.value = generateFormModel();
    fetchData(1);
  };

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): JobsForm => ({
    id: undefined,
    name: '',
    code: '',
    sort: 0,
    is_disable: 0,
    remark: '',
  });
  const form = reactive<JobsForm>(defaultForm());

  const rules = {
    name: [{ required: true, message: t('systemJobs.field.name.required') }],
    code: [{ required: true, message: t('systemJobs.field.code.required') }],
  };

  const resetForm = (patch: Partial<JobsForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = (record: JobsRecord) => {
    isEdit.value = true;
    resetForm({ ...record });
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      if (isEdit.value) {
        await editJobs(form);
      } else {
        await addJobs(form);
      }
      Message.success(t('systemJobs.tip.success'));
      modalVisible.value = false;
      await fetchData(pagination.current);
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: JobsRecord) => {
    await deleteJobs(record.id);
    Message.success(t('systemJobs.tip.success'));
    await fetchData(pagination.current);
  };

  const handleStatus = async (record: JobsRecord, enabled: boolean) => {
    await updateJobsStatus(record.id, enabled ? 0 : 1);
    record.is_disable = enabled ? 0 : 1;
    Message.success(t('systemJobs.tip.success'));
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemJobs',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
