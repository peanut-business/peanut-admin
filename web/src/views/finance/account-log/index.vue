<template>
  <div class="container">
    <Breadcrumb :items="['menu.finance', 'menu.finance.accountLog']" />
    <a-card class="general-card" :title="$t('menu.finance.accountLog')">
      <a-alert type="warning" style="margin-bottom: 16px">
        {{ $t('accountLog.alert') }}
      </a-alert>
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
                  field="user_info"
                  :label="$t('accountLog.form.userInfo')"
                >
                  <a-input
                    v-model="formModel.user_info"
                    allow-clear
                    :placeholder="$t('accountLog.form.userInfo.placeholder')"
                    @press-enter="search"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="change_type"
                  :label="$t('accountLog.form.changeType')"
                >
                  <a-select
                    v-model="formModel.change_type"
                    allow-clear
                    :options="changeTypeOptions"
                    :placeholder="$t('accountLog.form.changeType.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="timeRange"
                  :label="$t('accountLog.form.time')"
                >
                  <a-range-picker
                    v-model="formModel.timeRange"
                    show-time
                    format="YYYY-MM-DD HH:mm:ss"
                    value-format="YYYY-MM-DD HH:mm:ss"
                    style="width: 100%"
                    allow-clear
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
              {{ $t('accountLog.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('accountLog.form.reset') }}
            </a-button>
          </a-space>
        </a-col>
      </a-row>
      <a-divider style="margin-top: 0" />
      <a-table
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="renderData"
        :pagination="pagination"
        :bordered="{ cell: true }"
        :scroll="{ x: 1250 }"
        @page-change="onPageChange"
        @page-size-change="onPageSizeChange"
      >
        <template #nickname="{ record }">
          <a-space>
            <a-avatar :size="40" :image-url="record.avatar">
              {{ record.nickname?.slice(0, 1) }}
            </a-avatar>
            <span>{{ record.nickname || '-' }}</span>
          </a-space>
        </template>
        <template #change_amount="{ record }">
          <span :class="{ 'amount-expense': record.action === 2 }">
            {{ record.change_amount }}
          </span>
        </template>
      </a-table>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref, reactive } from 'vue';
  import { useI18n } from 'vue-i18n';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import useLoading from '@/hooks/loading';
  import {
    getAccountLogList,
    getUmChangeType,
    type AccountLogRecord,
    type AccountLogParams,
  } from '@/api/finance';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<AccountLogRecord[]>([]);

  const generateFormModel = () => ({
    user_info: '',
    change_type: '',
    timeRange: [] as string[],
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
    showPageSize: true,
  });

  const changeTypeOptions = ref<SelectOptionData[]>([]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('accountLog.columns.account'), dataIndex: 'account', width: 140 },
    {
      title: t('accountLog.columns.nickname'),
      slotName: 'nickname',
      width: 180,
    },
    { title: t('accountLog.columns.mobile'), dataIndex: 'mobile', width: 140 },
    {
      title: t('accountLog.columns.changeAmount'),
      slotName: 'change_amount',
      width: 120,
    },
    {
      title: t('accountLog.columns.leftAmount'),
      dataIndex: 'left_amount',
      width: 120,
    },
    {
      title: t('accountLog.columns.changeType'),
      dataIndex: 'change_type_desc',
      width: 180,
    },
    {
      title: t('accountLog.columns.sourceSn'),
      dataIndex: 'source_sn',
      width: 180,
    },
    {
      title: t('accountLog.columns.createTime'),
      dataIndex: 'create_time',
      width: 180,
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: AccountLogParams = {
        user_info: formModel.value.user_info || undefined,
        change_type: formModel.value.change_type || undefined,
        page_no: page,
        page_size: pagination.pageSize,
      };
      if (formModel.value.timeRange?.length === 2) {
        [params.start_time, params.end_time] = formModel.value.timeRange;
      }
      const { data } = await getAccountLogList(params);
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.pageSize = data.pageSize;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };

  const fetchChangeTypes = async () => {
    const { data } = await getUmChangeType();
    changeTypeOptions.value = Object.entries(data).map(([value, label]) => ({
      label,
      value,
    }));
  };

  fetchData();
  fetchChangeTypes();

  const search = () => fetchData(1);
  const onPageChange = (current: number) => fetchData(current);
  const onPageSizeChange = (pageSize: number) => {
    pagination.pageSize = pageSize;
    fetchData(1);
  };
  const reset = () => {
    formModel.value = generateFormModel();
    fetchData(1);
  };
</script>

<script lang="ts">
  export default {
    name: 'FinanceAccountLog',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .amount-expense {
    color: rgb(var(--red-6));
  }
</style>
