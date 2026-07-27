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
          <a-popconfirm
            :content="$t('systemLog.clear.confirm')"
            @ok="handleClear"
          >
            <a-button status="danger">
              <template #icon><icon-delete /></template>
              {{ $t('systemLog.operation.clear') }}
            </a-button>
          </a-popconfirm>
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
      </a-table>
    </a-card>
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
    clearOperationLog,
    type OperationLogRecord,
    type OperationLogParams,
  } from '@/api/system/log';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<OperationLogRecord[]>([]);

  const generateFormModel = () => ({
    username: '',
    uri: '',
    method: '',
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
      dataIndex: 'create_time',
      width: 180,
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: OperationLogParams = {
        ...formModel.value,
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
