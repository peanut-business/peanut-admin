<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.log']" />
    <a-card class="general-card" :title="$t('menu.system.log')">
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
                  field="username"
                  :label="$t('systemLog.form.username')"
                >
                  <a-input
                    v-model="formModel.username"
                    allow-clear
                    :placeholder="$t('systemLog.form.username.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="uri" :label="$t('systemLog.form.uri')">
                  <a-input
                    v-model="formModel.uri"
                    allow-clear
                    :placeholder="$t('systemLog.form.uri.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="method"
                  :label="$t('systemLog.form.method')"
                >
                  <a-select
                    v-model="formModel.method"
                    allow-clear
                    :options="methodOptions"
                    :placeholder="$t('systemLog.form.method.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="ip" :label="$t('systemLog.form.ip')">
                  <a-input
                    v-model="formModel.ip"
                    allow-clear
                    :placeholder="$t('systemLog.form.ip.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="timeRange"
                  :label="$t('systemLog.form.time')"
                >
                  <a-range-picker
                    v-model="formModel.timeRange"
                    show-time
                    value-format="YYYY-MM-DD HH:mm:ss"
                    allow-clear
                    style="width: 100%"
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
              {{ $t('systemLog.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('systemLog.form.reset') }}
            </a-button>
          </a-space>
        </a-col>
      </a-row>
      <a-divider style="margin-top: 0" />
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-button
              v-permission="['log/lists']"
              type="outline"
              @click="openExport"
            >
              <template #icon><icon-export /></template>
              {{ $t('systemLog.operation.export') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemLog.clear.confirm')"
              @ok="handleClear"
            >
              <a-button v-permission="['log/clear']" status="danger">
                <template #icon><icon-delete /></template>
                {{ $t('systemLog.operation.clear') }}
              </a-button>
            </a-popconfirm>
          </a-space>
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
        <template #method="{ record }">
          <a-tag color="green">{{ record.method }}</a-tag>
        </template>
        <template #params="{ record }">
          <a-typography-text
            code
            copyable
            :ellipsis="{ rows: 1, showTooltip: true }"
          >
            {{ record.params }}
          </a-typography-text>
        </template>
        <template #createTime="{ record }">
          {{ formatTime(record.create_time) }}
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="exportVisible"
      :title="$t('systemLog.export.title')"
      :ok-text="$t('systemLog.export.confirm')"
      :ok-loading="exportLoading"
      :mask-closable="false"
      width="540px"
      @before-ok="handleExport"
    >
      <a-spin :loading="exportInfoLoading" style="width: 100%">
        <a-alert type="info" style="margin-bottom: 16px">
          {{
            $t('systemLog.export.summary', {
              count: exportInfo.count,
              pages: exportInfo.sum_page,
              size: exportInfo.page_size,
            })
          }}
          <br />
          {{
            $t('systemLog.export.limit', {
              pages: exportInfo.max_page,
              count: exportInfo.all_max_size,
            })
          }}
        </a-alert>
        <a-form :model="exportForm" layout="vertical">
          <a-form-item field="page_type" :label="$t('systemLog.export.range')">
            <a-radio-group v-model="exportForm.page_type">
              <a-radio :value="0">{{ $t('systemLog.export.all') }}</a-radio>
              <a-radio :value="1">{{ $t('systemLog.export.pages') }}</a-radio>
            </a-radio-group>
          </a-form-item>
          <a-form-item
            v-if="exportForm.page_type === 1"
            :label="$t('systemLog.export.pageRange')"
          >
            <a-space>
              <a-input-number
                v-model="exportForm.page_start"
                :min="1"
                :max="exportInfo.sum_page || 1"
              />
              <span>{{ $t('systemLog.export.to') }}</span>
              <a-input-number
                v-model="exportForm.page_end"
                :min="exportForm.page_start"
                :max="exportInfo.sum_page || 1"
              />
            </a-space>
          </a-form-item>
          <a-form-item
            field="file_name"
            :label="$t('systemLog.export.fileName')"
          >
            <a-input v-model="exportForm.file_name" :max-length="100" />
          </a-form-item>
        </a-form>
      </a-spin>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref, reactive } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import useLoading from '@/hooks/loading';
  import {
    getOperationLogList,
    getOperationLogExportInfo,
    exportOperationLog,
    clearOperationLog,
    type OperationLogRecord,
    type OperationLogParams,
    type OperationLogExportInfo,
  } from '@/api/system/log';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<OperationLogRecord[]>([]);

  const generateFormModel = () => ({
    username: '',
    uri: '',
    method: '',
    ip: '',
    timeRange: [] as string[],
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });

  const methodOptions = computed<SelectOptionData[]>(() => [
    { label: 'POST', value: 'POST' },
    { label: 'GET', value: 'GET' },
    { label: 'PUT', value: 'PUT' },
    { label: 'DELETE', value: 'DELETE' },
  ]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemLog.columns.id'), dataIndex: 'id', width: 80 },
    {
      title: t('systemLog.columns.username'),
      dataIndex: 'username',
      width: 120,
    },
    { title: t('systemLog.columns.ip'), dataIndex: 'ip', width: 140 },
    { title: t('systemLog.columns.uri'), dataIndex: 'uri' },
    { title: t('systemLog.columns.method'), slotName: 'method', width: 90 },
    { title: t('systemLog.columns.params'), slotName: 'params', width: 240 },
    {
      title: t('systemLog.columns.createTime'),
      slotName: 'createTime',
      width: 180,
    },
  ]);

  const formatTime = (value?: number | string): string => {
    if (!value) return '-';
    if (typeof value === 'string') return value;
    return new Date(value * 1000).toLocaleString('zh-CN', { hour12: false });
  };

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: OperationLogParams = {
        username: formModel.value.username || undefined,
        uri: formModel.value.uri || undefined,
        method: formModel.value.method || undefined,
        ip: formModel.value.ip || undefined,
        start_time: formModel.value.timeRange[0] || undefined,
        end_time: formModel.value.timeRange[1] || undefined,
        page_no: page,
        page_size: pagination.pageSize,
      };
      const { data } = await getOperationLogList(params);
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

  const handleClear = async () => {
    await clearOperationLog();
    Message.success(t('systemLog.tip.success'));
    fetchData(1);
  };

  // ---- 两阶段 XLSX 导出 ----
  const exportVisible = ref(false);
  const exportInfoLoading = ref(false);
  const exportLoading = ref(false);
  const emptyExportInfo = (): OperationLogExportInfo => ({
    count: 0,
    page_size: pagination.pageSize,
    sum_page: 0,
    max_page: 0,
    all_max_size: 0,
    page_start: 1,
    page_end: 1,
    file_name: '',
  });
  const exportInfo = reactive<OperationLogExportInfo>(emptyExportInfo());
  const exportForm = reactive({
    page_type: 0 as 0 | 1,
    page_start: 1,
    page_end: 1,
    file_name: '',
  });
  const listParams = (): OperationLogParams => ({
    username: formModel.value.username || undefined,
    uri: formModel.value.uri || undefined,
    method: formModel.value.method || undefined,
    ip: formModel.value.ip || undefined,
    start_time: formModel.value.timeRange[0] || undefined,
    end_time: formModel.value.timeRange[1] || undefined,
    page_no: 1,
    page_size: pagination.pageSize,
  });
  const openExport = async () => {
    exportVisible.value = true;
    exportInfoLoading.value = true;
    try {
      const { data } = await getOperationLogExportInfo(listParams());
      Object.assign(exportInfo, data);
      exportForm.page_type = 0;
      exportForm.page_start = data.page_start;
      exportForm.page_end = data.page_end;
      exportForm.file_name = data.file_name;
    } finally {
      exportInfoLoading.value = false;
    }
  };
  const handleExport = async () => {
    if (exportInfoLoading.value) return false;
    if (
      exportForm.page_type === 1 &&
      exportForm.page_end < exportForm.page_start
    ) {
      Message.error(t('systemLog.export.invalidRange'));
      return false;
    }
    exportLoading.value = true;
    try {
      const { data } = await exportOperationLog({
        ...listParams(),
        ...exportForm,
      });
      const link = document.createElement('a');
      link.href = data.url;
      link.download = data.file_name;
      link.rel = 'noopener';
      document.body.appendChild(link);
      link.click();
      link.remove();
      Message.success(t('systemLog.export.success'));
      return true;
    } finally {
      exportLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemLog',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
