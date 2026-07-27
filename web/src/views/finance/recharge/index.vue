<template>
  <div class="container">
    <Breadcrumb :items="['menu.finance', 'menu.finance.recharge']" />
    <a-card class="general-card" :title="$t('menu.finance.recharge')">
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
                <a-form-item field="keyword" :label="$t('recharge.form.keyword')">
                  <a-input
                    v-model="formModel.keyword"
                    allow-clear
                    :placeholder="$t('recharge.form.keyword.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="status" :label="$t('recharge.form.status')">
                  <a-select
                    v-model="formModel.status"
                    allow-clear
                    :options="statusOptions"
                    :placeholder="$t('recharge.form.status.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="pay_way" :label="$t('recharge.form.payWay')">
                  <a-select
                    v-model="formModel.pay_way"
                    allow-clear
                    :options="payWayOptions"
                    :placeholder="$t('recharge.form.payWay.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="timeRange" :label="$t('recharge.form.time')">
                  <a-range-picker v-model="formModel.timeRange" style="width: 100%" />
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
              {{ $t('recharge.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('recharge.form.reset') }}
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
        <template #status="{ record }">
          <a-tag :color="statusColor(record.status)">
            {{ $t(`recharge.status.${record.status}`) }}
          </a-tag>
        </template>
        <template #pay_way="{ record }">
          {{ $t(`recharge.payWay.${record.pay_way}`) }}
        </template>
        <template #pay_time="{ record }">
          {{ formatTime(record.pay_time) }}
        </template>
        <template #create_time="{ record }">
          {{ formatTime(record.create_time) }}
        </template>
      </a-table>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import useLoading from '@/hooks/loading';
  import { getRechargeList, type RechargeRecord, type RechargeParams } from '@/api/finance';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<RechargeRecord[]>([]);

  const generateFormModel = () => ({
    keyword: '',
    status: '',
    pay_way: '',
    timeRange: [] as string[],
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({ current: 1, pageSize: 15, total: 0, showTotal: true });

  const statusOptions = computed<SelectOptionData[]>(() => [
    { label: t('recharge.status.0'), value: '0' },
    { label: t('recharge.status.1'), value: '1' },
    { label: t('recharge.status.2'), value: '2' },
  ]);

  const payWayOptions = computed<SelectOptionData[]>(() => [
    { label: t('recharge.payWay.1'), value: '1' },
    { label: t('recharge.payWay.2'), value: '2' },
  ]);

  const statusColor = (status: number) =>
    ({ 0: 'orange', 1: 'green', 2: 'red' }[status] ?? 'gray');

  const formatTime = (ts: number | string) => {
    if (!ts) return '-';
    if (typeof ts === 'number') return new Date(ts * 1000).toLocaleString();
    return ts;
  };

  const columns = computed<TableColumnData[]>(() => [
    { title: t('recharge.columns.id'), dataIndex: 'id', width: 80 },
    { title: t('recharge.columns.orderSn'), dataIndex: 'order_sn', width: 200 },
    { title: t('recharge.columns.member'), slotName: 'member', width: 160 },
    { title: t('recharge.columns.amount'), dataIndex: 'amount', width: 120 },
    { title: t('recharge.columns.payWay'), slotName: 'pay_way', width: 110 },
    { title: t('recharge.columns.status'), slotName: 'status', width: 100 },
    { title: t('recharge.columns.payTime'), slotName: 'pay_time', width: 180 },
    { title: t('recharge.columns.createTime'), slotName: 'create_time', width: 180 },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: RechargeParams = {
        keyword: formModel.value.keyword || undefined,
        status: formModel.value.status !== '' ? formModel.value.status : undefined,
        pay_way: formModel.value.pay_way !== '' ? formModel.value.pay_way : undefined,
        page,
        limit: pagination.pageSize,
      };
      if (formModel.value.timeRange?.length === 2) {
        params.start_time = Math.floor(new Date(formModel.value.timeRange[0]).getTime() / 1000);
        params.end_time   = Math.floor(new Date(formModel.value.timeRange[1]).getTime() / 1000);
      }
      const { data } = await getRechargeList(params);
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
  const reset = () => { formModel.value = generateFormModel(); fetchData(1); };
</script>

<script lang="ts">
  export default { name: 'FinanceRecharge' };
</script>

<style scoped lang="less">
  .container { padding: 0 20px 20px 20px; }
  .member-sn { color: var(--color-text-3); font-size: 12px; }
</style>
