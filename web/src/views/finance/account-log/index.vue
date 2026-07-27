<template>
  <div class="container">
    <Breadcrumb :items="['menu.finance', 'menu.finance.accountLog']" />
    <a-card class="general-card" :title="$t('menu.finance.accountLog')">
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
                  field="keyword"
                  :label="$t('accountLog.form.keyword')"
                >
                  <a-input
                    v-model="formModel.keyword"
                    allow-clear
                    :placeholder="$t('accountLog.form.keyword.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="direction"
                  :label="$t('accountLog.form.direction')"
                >
                  <a-select
                    v-model="formModel.direction"
                    allow-clear
                    :options="directionOptions"
                    :placeholder="$t('accountLog.form.direction.placeholder')"
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
        @page-change="onPageChange"
      >
        <template #member="{ record }">
          <div>{{ record.member_nickname || '-' }}</div>
          <div class="member-sn">{{ record.member_sn }}</div>
        </template>
        <template #change_amount="{ record }">
          <span
            :class="record.direction === 1 ? 'amount-income' : 'amount-expense'"
          >
            {{ record.direction === 1 ? '+' : '' }}{{ record.change_amount }}
          </span>
        </template>
        <template #direction="{ record }">
          <a-tag :color="record.direction === 1 ? 'green' : 'red'">
            {{ $t(`accountLog.direction.${record.direction}`) }}
          </a-tag>
        </template>
        <template #source_type="{ record }">
          {{ $t(`accountLog.source.${record.source_type}`) }}
        </template>
        <template #create_time="{ record }">
          {{ formatTime(record.create_time) }}
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
    type AccountLogRecord,
    type AccountLogParams,
  } from '@/api/finance';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<AccountLogRecord[]>([]);

  const generateFormModel = () => ({
    keyword: '',
    direction: '',
    timeRange: [] as string[],
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });

  const directionOptions = computed<SelectOptionData[]>(() => [
    { label: t('accountLog.direction.1'), value: '1' },
    { label: t('accountLog.direction.2'), value: '2' },
  ]);

  const formatTime = (ts: number) =>
    ts ? new Date(ts * 1000).toLocaleString() : '-';

  const columns = computed<TableColumnData[]>(() => [
    { title: t('accountLog.columns.id'), dataIndex: 'id', width: 80 },
    { title: t('accountLog.columns.member'), slotName: 'member', width: 160 },
    {
      title: t('accountLog.columns.changeAmount'),
      slotName: 'change_amount',
      width: 130,
    },
    {
      title: t('accountLog.columns.afterAmount'),
      dataIndex: 'after_amount',
      width: 120,
    },
    {
      title: t('accountLog.columns.direction'),
      slotName: 'direction',
      width: 90,
    },
    {
      title: t('accountLog.columns.source'),
      slotName: 'source_type',
      width: 120,
    },
    { title: t('accountLog.columns.remark'), dataIndex: 'remark' },
    {
      title: t('accountLog.columns.createTime'),
      slotName: 'create_time',
      width: 180,
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: AccountLogParams = {
        keyword: formModel.value.keyword || undefined,
        direction: formModel.value.direction || undefined,
        page,
        limit: pagination.pageSize,
      };
      if (formModel.value.timeRange?.length === 2) {
        params.start_time = Math.floor(
          new Date(formModel.value.timeRange[0]).getTime() / 1000
        );
        params.end_time = Math.floor(
          new Date(formModel.value.timeRange[1]).getTime() / 1000
        );
      }
      const { data } = await getAccountLogList(params);
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

  .member-sn {
    color: var(--color-text-3);
    font-size: 12px;
  }

  .amount-income {
    color: rgb(var(--green-6));
  }

  .amount-expense {
    color: rgb(var(--red-6));
  }
</style>
