<template>
  <div class="container">
    <Breadcrumb :items="['menu.finance', 'menu.finance.refund']" />

    <a-card :bordered="false" style="margin-bottom: 16px">
      <a-row :gutter="24">
        <a-col :span="6">
          <a-statistic
            :title="$t('refund.stat.total')"
            :value="stat.total"
            :precision="2"
          />
        </a-col>
        <a-col :span="6">
          <a-statistic
            :title="$t('refund.stat.ing')"
            :value="stat.ing"
            :precision="2"
          />
        </a-col>
        <a-col :span="6">
          <a-statistic
            :title="$t('refund.stat.success')"
            :value="stat.success"
            :precision="2"
          />
        </a-col>
        <a-col :span="6">
          <a-statistic
            :title="$t('refund.stat.error')"
            :value="stat.error"
            :precision="2"
          />
        </a-col>
      </a-row>
    </a-card>

    <a-card :bordered="false" style="margin-bottom: 16px">
      <a-form :model="formModel" layout="inline">
        <a-form-item :label="$t('refund.filter.sn')">
          <a-input
            v-model="formModel.sn"
            :placeholder="$t('refund.filter.sn.placeholder')"
            allow-clear
            style="width: 200px"
            @press-enter="search"
          />
        </a-form-item>
        <a-form-item :label="$t('refund.filter.order_sn')">
          <a-input
            v-model="formModel.order_sn"
            :placeholder="$t('refund.filter.order_sn.placeholder')"
            allow-clear
            style="width: 200px"
            @press-enter="search"
          />
        </a-form-item>
        <a-form-item :label="$t('refund.filter.user_info')">
          <a-input
            v-model="formModel.user_info"
            :placeholder="$t('refund.filter.user_info.placeholder')"
            allow-clear
            style="width: 200px"
            @press-enter="search"
          />
        </a-form-item>
        <a-form-item :label="$t('refund.filter.refund_type')">
          <a-select
            v-model="formModel.refund_type"
            allow-clear
            style="width: 130px"
            :placeholder="$t('refund.filter.all')"
          >
            <a-option :value="1">{{ $t('refund.filter.admin') }}</a-option>
          </a-select>
        </a-form-item>
        <a-form-item :label="$t('refund.filter.time')">
          <a-range-picker
            v-model="formModel.timeRange"
            show-time
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            allow-clear
            style="width: 360px"
          />
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" @click="search">
              <template #icon><icon-search /></template>
              {{ $t('refund.filter.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('refund.filter.reset') }}
            </a-button>
          </a-space>
        </a-form-item>
      </a-form>
    </a-card>

    <a-card :bordered="false">
      <a-tabs v-model:active-key="activeTab" @change="handleTabChange">
        <a-tab-pane
          v-for="tab in tabs"
          :key="tab.key"
          :title="`${$t(tab.label)}(${extend[tab.extendKey] ?? 0})`"
        />
      </a-tabs>

      <a-table
        :data="list"
        :loading="loading"
        :pagination="pagination"
        :bordered="{ cell: true }"
        :scroll="{ x: 1250 }"
        row-key="id"
        @page-change="onPageChange"
        @page-size-change="onPageSizeChange"
      >
        <template #columns>
          <a-table-column
            :title="$t('refund.col.sn')"
            data-index="sn"
            :width="200"
          />
          <a-table-column :title="$t('refund.col.user')" :width="180">
            <template #cell="{ record }">
              <a-space>
                <a-avatar :size="40" :image-url="record.avatar">
                  {{ record.nickname?.slice(0, 1) }}
                </a-avatar>
                <span>{{ record.nickname || '-' }}</span>
              </a-space>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.col.order_sn')"
            data-index="order_sn"
            :width="200"
          />
          <a-table-column :title="$t('refund.col.refund_amount')" :width="120">
            <template #cell="{ record }">
              ¥ {{ record.refund_amount }}
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.col.refund_type')"
            data-index="refund_type_text"
            :width="110"
          />
          <a-table-column :title="$t('refund.col.refund_status')" :width="110">
            <template #cell="{ record }">
              <a-tag :color="statusColor(record.refund_status)">
                {{ record.refund_status_text }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.col.create_time')"
            data-index="create_time"
            :width="180"
          />
          <a-table-column
            :title="$t('refund.col.action')"
            :width="180"
            fixed="right"
          >
            <template #cell="{ record }">
              <a-space>
                <a-button
                  v-permission="['finance.refund/log']"
                  type="text"
                  size="small"
                  @click="openLog(record.id)"
                >
                  {{ $t('refund.action.log') }}
                </a-button>
                <a-popconfirm
                  v-if="record.refund_status === 2"
                  :content="$t('refund.retry.confirm')"
                  @ok="handleRetry(record.id)"
                >
                  <a-button
                    v-permission="['recharge.recharge/refundAgain']"
                    type="text"
                    size="small"
                    :loading="retryingId === record.id"
                  >
                    {{ $t('refund.action.retry') }}
                  </a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <a-drawer
      v-model:visible="logVisible"
      :title="$t('refund.log.title')"
      :width="760"
      :footer="false"
    >
      <a-table
        :data="logList"
        :loading="logLoading"
        :pagination="false"
        row-key="id"
      >
        <template #columns>
          <a-table-column
            :title="$t('refund.log.col.sn')"
            data-index="sn"
            :width="200"
          />
          <a-table-column
            :title="$t('refund.log.col.refund_amount')"
            :width="120"
          >
            <template #cell="{ record }">
              ¥ {{ record.refund_amount }}
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.log.col.refund_status')"
            :width="110"
          >
            <template #cell="{ record }">
              <a-tag :color="statusColor(record.refund_status)">
                {{ record.refund_status_text }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.log.col.create_time')"
            data-index="create_time"
            :width="180"
          />
          <a-table-column
            :title="$t('refund.log.col.handler')"
            data-index="handler"
            :width="120"
          />
        </template>
      </a-table>
    </a-drawer>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import { useI18n } from 'vue-i18n';
  import {
    getRefundLog,
    getRefundRecords,
    getRefundStat,
    refundAgain,
    type RefundListExtend,
    type RefundLogRecord,
    type RefundParams,
    type RefundRecord,
    type RefundStat,
  } from '@/api/finance';

  const { t } = useI18n();

  const stat = reactive<RefundStat>({ total: 0, ing: 0, success: 0, error: 0 });
  const extend = reactive<RefundListExtend>({
    total: 0,
    ing: 0,
    success: 0,
    error: 0,
  });

  const tabs = [
    { key: '', label: 'refund.tab.all', extendKey: 'total' },
    { key: '0', label: 'refund.tab.ing', extendKey: 'ing' },
    { key: '1', label: 'refund.tab.success', extendKey: 'success' },
    { key: '2', label: 'refund.tab.error', extendKey: 'error' },
  ] as const;
  const activeTab = ref('');

  const generateFormModel = () => ({
    sn: '',
    order_sn: '',
    user_info: '',
    refund_type: '' as string | number,
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
  const list = ref<RefundRecord[]>([]);
  const loading = ref(false);

  const statusColor = (status: number) =>
    ({ 0: 'orange', 1: 'green', 2: 'red' }[status] ?? 'gray');

  const listParams = (pageNo: number): RefundParams => {
    const params: RefundParams = {
      sn: formModel.value.sn || undefined,
      order_sn: formModel.value.order_sn || undefined,
      user_info: formModel.value.user_info || undefined,
      refund_type:
        formModel.value.refund_type === ''
          ? undefined
          : formModel.value.refund_type,
      refund_status: activeTab.value === '' ? undefined : activeTab.value,
      page_no: pageNo,
      page_size: pagination.pageSize,
    };
    if (formModel.value.timeRange.length === 2) {
      [params.start_time, params.end_time] = formModel.value.timeRange;
    }
    return params;
  };

  const fetchStat = async () => {
    const { data } = await getRefundStat();
    Object.assign(stat, data);
  };

  const fetchList = async (pageNo = 1) => {
    loading.value = true;
    try {
      const { data } = await getRefundRecords(listParams(pageNo));
      list.value = data.lists;
      pagination.current = data.page_no ?? data.pageNo ?? pageNo;
      pagination.pageSize =
        data.page_size ?? data.pageSize ?? pagination.pageSize;
      pagination.total = data.count;
      Object.assign(extend, data.extend);
    } finally {
      loading.value = false;
    }
  };

  const search = () => fetchList(1);
  const reset = () => {
    formModel.value = generateFormModel();
    fetchList(1);
  };
  const handleTabChange = (key: string | number) => {
    activeTab.value = String(key);
    fetchList(1);
  };
  const onPageChange = (current: number) => fetchList(current);
  const onPageSizeChange = (pageSize: number) => {
    pagination.pageSize = pageSize;
    fetchList(1);
  };

  const retryingId = ref(0);
  const handleRetry = async (recordId: number) => {
    retryingId.value = recordId;
    try {
      await refundAgain(recordId);
      Message.success(t('refund.retry.success'));
      await Promise.all([fetchList(pagination.current), fetchStat()]);
    } finally {
      retryingId.value = 0;
    }
  };

  const logVisible = ref(false);
  const logLoading = ref(false);
  const logList = ref<RefundLogRecord[]>([]);
  const openLog = async (recordId: number) => {
    logVisible.value = true;
    logLoading.value = true;
    logList.value = [];
    try {
      const { data } = await getRefundLog(recordId);
      logList.value = data;
    } finally {
      logLoading.value = false;
    }
  };

  onMounted(() => {
    fetchStat();
    fetchList();
  });
</script>

<script lang="ts">
  export default { name: 'FinanceRefund' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
