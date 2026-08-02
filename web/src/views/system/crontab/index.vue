<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.crontab']" />
    <a-card class="general-card" :title="$t('menu.system.crontab')">
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
                <a-form-item
                  field="name"
                  :label="$t('systemCrontab.form.name')"
                >
                  <a-input
                    v-model="formModel.name"
                    allow-clear
                    :placeholder="$t('systemCrontab.form.name.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="status"
                  :label="$t('systemCrontab.form.status')"
                >
                  <a-select
                    v-model="formModel.status"
                    allow-clear
                    :options="statusOptions"
                    :placeholder="$t('systemCrontab.form.status.placeholder')"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-form>
        </a-col>
        <a-divider style="height: 56px" direction="vertical" />
        <a-col :flex="'86px'" style="text-align: right">
          <a-space direction="vertical" :size="18">
            <a-button type="primary" @click="search">
              <template #icon><icon-search /></template>
              {{ $t('systemCrontab.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('systemCrontab.form.reset') }}
            </a-button>
          </a-space>
        </a-col>
      </a-row>
      <a-divider style="margin-top: 0" />
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-button
            v-permission="['crontab/add']"
            type="primary"
            @click="handleAdd"
          >
            <template #icon><icon-plus /></template>
            {{ $t('systemCrontab.operation.create') }}
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
        <template #status="{ record }">
          <a-tag :color="statusColor(record.status)">
            {{ record.status_desc }}
          </a-tag>
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-if="record.status !== 1"
              v-permission="['crontab/operate']"
              type="text"
              size="small"
              @click="handleOperate(record, 'start')"
            >
              {{ $t('systemCrontab.operation.start') }}
            </a-button>
            <a-button
              v-else
              v-permission="['crontab/operate']"
              type="text"
              size="small"
              status="warning"
              @click="handleOperate(record, 'stop')"
            >
              {{ $t('systemCrontab.operation.stop') }}
            </a-button>
            <a-button
              v-permission="['crontab/edit']"
              type="text"
              size="small"
              @click="handleEdit(record)"
            >
              {{ $t('systemCrontab.operation.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemCrontab.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button
                v-permission="['crontab/delete']"
                type="text"
                status="danger"
                size="small"
              >
                {{ $t('systemCrontab.operation.delete') }}
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
          ? $t('systemCrontab.modal.editTitle')
          : $t('systemCrontab.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      width="640px"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="name" :label="$t('systemCrontab.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemCrontab.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="command" :label="$t('systemCrontab.field.command')">
          <a-input
            v-model="form.command"
            :placeholder="$t('systemCrontab.field.command.placeholder')"
          />
        </a-form-item>
        <a-form-item field="params" :label="$t('systemCrontab.field.params')">
          <a-input
            v-model="form.params"
            :placeholder="$t('systemCrontab.field.params.placeholder')"
          />
        </a-form-item>
        <a-form-item field="sort" :label="$t('systemCrontab.field.sort')">
          <a-input-number v-model="form.sort" :min="0" style="width: 160px" />
        </a-form-item>
        <a-form-item
          field="expression"
          :label="$t('systemCrontab.field.expression')"
        >
          <a-input-group style="width: 100%">
            <a-input
              v-model="form.expression"
              :placeholder="$t('systemCrontab.field.expression.placeholder')"
            />
            <a-button
              v-permission="['crontab/expression']"
              @click="previewExpression"
            >
              {{ $t('systemCrontab.field.preview') }}
            </a-button>
          </a-input-group>
        </a-form-item>
        <a-form-item
          v-if="previewList.length"
          :label="$t('systemCrontab.field.nextRuns')"
        >
          <ul class="preview-list">
            <li v-for="p in previewList" :key="p.time">{{ p.date }}</li>
          </ul>
        </a-form-item>
        <a-form-item field="status" :label="$t('systemCrontab.field.status')">
          <a-radio-group v-model="form.status">
            <a-radio :value="1">{{ $t('systemCrontab.status.start') }}</a-radio>
            <a-radio :value="2">{{ $t('systemCrontab.status.stop') }}</a-radio>
          </a-radio-group>
        </a-form-item>
        <a-form-item field="remark" :label="$t('systemCrontab.field.remark')">
          <a-textarea
            v-model="form.remark"
            :placeholder="$t('systemCrontab.field.remark.placeholder')"
            :max-length="255"
            show-word-limit
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getCrontabList,
    getCrontabExpression,
    addCrontab,
    editCrontab,
    deleteCrontab,
    operateCrontab,
    type CrontabRecord,
    type CrontabForm,
    type CrontabListParams,
    type CrontabStatus,
    type ExpressionItem,
  } from '@/api/system/crontab';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<CrontabRecord[]>([]);

  const generateFormModel = () => ({
    name: '',
    status: '' as CrontabStatus | '',
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });

  const statusOptions = computed<SelectOptionData[]>(() => [
    { label: t('systemCrontab.status.start'), value: 1 },
    { label: t('systemCrontab.status.stop'), value: 2 },
    { label: t('systemCrontab.status.error'), value: 3 },
  ]);

  const statusColor = (status: CrontabStatus) =>
    ({ 1: 'green', 2: 'gray', 3: 'red' }[status] || 'gray');

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemCrontab.columns.id'), dataIndex: 'id', width: 70 },
    { title: t('systemCrontab.columns.name'), dataIndex: 'name' },
    { title: t('systemCrontab.columns.command'), dataIndex: 'command' },
    { title: t('systemCrontab.columns.expression'), dataIndex: 'expression' },
    { title: t('systemCrontab.columns.status'), slotName: 'status', width: 90 },
    {
      title: t('systemCrontab.columns.lastTime'),
      dataIndex: 'last_time',
      width: 170,
    },
    { title: t('systemCrontab.columns.time'), dataIndex: 'time', width: 90 },
    {
      title: t('systemCrontab.columns.maxTime'),
      dataIndex: 'max_time',
      width: 90,
    },
    {
      title: t('systemCrontab.columns.error'),
      dataIndex: 'error',
      width: 180,
    },
    {
      title: t('systemCrontab.columns.operations'),
      slotName: 'operations',
      width: 220,
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: CrontabListParams = {
        ...formModel.value,
        page_no: page,
        page_size: pagination.pageSize,
      };
      const { data } = await getCrontabList(params);
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
  const previewList = ref<ExpressionItem[]>([]);

  const defaultForm = (): CrontabForm => ({
    id: undefined,
    name: '',
    type: 1,
    command: '',
    params: '',
    sort: 0,
    status: 1,
    expression: '',
    remark: '',
  });
  const form = reactive<CrontabForm>(defaultForm());

  const rules = {
    name: [{ required: true, message: t('systemCrontab.field.name.required') }],
    command: [
      { required: true, message: t('systemCrontab.field.command.required') },
    ],
    expression: [
      { required: true, message: t('systemCrontab.field.expression.required') },
    ],
  };

  const resetForm = (patch: Partial<CrontabForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
    previewList.value = [];
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = (record: CrontabRecord) => {
    isEdit.value = true;
    resetForm({
      id: record.id,
      name: record.name,
      type: record.type,
      command: record.command,
      params: record.params,
      sort: record.sort,
      status: record.status === 3 ? 2 : record.status,
      expression: record.expression,
      remark: record.remark,
    });
    modalVisible.value = true;
  };

  const previewExpression = async () => {
    if (!form.expression) {
      Message.warning(t('systemCrontab.field.expression.required'));
      return;
    }
    const { data } = await getCrontabExpression(form.expression);
    if (Array.isArray(data) && data.length) {
      previewList.value = data;
    } else {
      previewList.value = [];
      Message.error(t('systemCrontab.tip.badExpression'));
    }
  };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      if (isEdit.value) {
        await editCrontab(form);
      } else {
        await addCrontab(form);
      }
      Message.success(t('systemCrontab.tip.success'));
      modalVisible.value = false;
      await fetchData(pagination.current);
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: CrontabRecord) => {
    await deleteCrontab(record.id);
    Message.success(t('systemCrontab.tip.success'));
    await fetchData(pagination.current);
  };

  const handleOperate = async (
    record: CrontabRecord,
    operate: 'start' | 'stop'
  ) => {
    await operateCrontab(record.id, operate);
    Message.success(t('systemCrontab.tip.success'));
    await fetchData(pagination.current);
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemCrontab',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .preview-list {
    margin: 0;
    padding: 8px 12px;
    list-style: none;
    width: 100%;
    background: var(--color-fill-2);
    border-radius: 4px;

    li {
      line-height: 22px;
      font-size: 13px;
      color: var(--color-text-2);
    }
  }
</style>
