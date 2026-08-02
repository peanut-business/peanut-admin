<template>
  <div class="container">
    <Breadcrumb :items="['menu.finance', 'menu.finance.recharge']" />
    <a-card class="general-card" :title="$t('menu.finance.recharge')">
      <a-alert type="warning" style="margin-bottom: 16px">
        {{ $t('recharge.alert') }}
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
                <a-form-item field="sn" :label="$t('recharge.form.sn')">
                  <a-input
                    v-model="formModel.sn"
                    allow-clear
                    :placeholder="$t('recharge.form.sn.placeholder')"
                    @press-enter="search"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="user_info"
                  :label="$t('recharge.form.userInfo')"
                >
                  <a-input
                    v-model="formModel.user_info"
                    allow-clear
                    :placeholder="$t('recharge.form.userInfo.placeholder')"
                    @press-enter="search"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="pay_way"
                  :label="$t('recharge.form.payWay')"
                >
                  <a-select
                    v-model="formModel.pay_way"
                    allow-clear
                    :options="payWayOptions"
                    :placeholder="$t('recharge.form.payWay.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="pay_status"
                  :label="$t('recharge.form.payStatus')"
                >
                  <a-select
                    v-model="formModel.pay_status"
                    allow-clear
                    :options="payStatusOptions"
                    :placeholder="$t('recharge.form.payStatus.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="16">
                <a-form-item
                  field="timeRange"
                  :label="$t('recharge.form.time')"
                  :label-col-props="{ span: 3 }"
                  :wrapper-col-props="{ span: 21 }"
                >
                  <a-range-picker
                    v-model="formModel.timeRange"
                    show-time
                    format="YYYY-MM-DD HH:mm:ss"
                    value-format="YYYY-MM-DD HH:mm:ss"
                    allow-clear
                    style="width: 100%"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-form>
        </a-col>
        <a-divider style="height: 124px" direction="vertical" />
        <a-col :flex="'86px'" style="text-align: right">
          <a-space direction="vertical" :size="10">
            <a-button type="primary" @click="search">
              <template #icon><icon-search /></template>
              {{ $t('recharge.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('recharge.form.reset') }}
            </a-button>
            <a-button @click="openExport">
              <template #icon><icon-download /></template>
              {{ $t('recharge.form.export') }}
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
        <template #user="{ record }">
          <a-space>
            <a-avatar :size="40" :image-url="record.avatar">
              {{ record.nickname?.slice(0, 1) }}
            </a-avatar>
            <span>{{ record.nickname || '-' }}</span>
          </a-space>
        </template>
        <template #pay_status="{ record }">
          <span :class="{ 'pay-unpaid': record.pay_status === 0 }">
            {{ record.pay_status_text }}
          </span>
        </template>
        <template #operations="{ record }">
          <a-popconfirm
            v-if="record.pay_status === 1"
            :content="$t('recharge.refund.confirm')"
            @ok="handleRefund(record.id)"
          >
            <a-button
              v-permission="['recharge.recharge/refund']"
              type="text"
              size="small"
              :disabled="record.refund_status === 1"
              :loading="refundingId === record.id"
            >
              {{ $t('recharge.action.refund') }}
            </a-button>
          </a-popconfirm>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="exportVisible"
      :title="$t('recharge.export.title')"
      :ok-text="$t('recharge.export.confirm')"
      :ok-loading="exportLoading"
      :mask-closable="false"
      width="540px"
      @before-ok="handleExport"
    >
      <a-spin :loading="exportInfoLoading" style="width: 100%">
        <a-form :model="exportForm" layout="vertical">
          <a-alert type="info" style="margin-bottom: 16px">
            {{
              $t('recharge.export.summary', {
                count: exportInfo.count,
                pages: exportInfo.sum_page,
                size: exportInfo.page_size,
              })
            }}
            <br />
            {{
              $t('recharge.export.limit', {
                pages: exportInfo.max_page,
                count: exportInfo.all_max_size,
              })
            }}
          </a-alert>
          <a-form-item field="page_type" :label="$t('recharge.export.range')">
            <a-radio-group v-model="exportForm.page_type">
              <a-radio :value="0">{{ $t('recharge.export.all') }}</a-radio>
              <a-radio :value="1">{{ $t('recharge.export.pages') }}</a-radio>
            </a-radio-group>
          </a-form-item>
          <a-form-item
            v-if="exportForm.page_type === 1"
            :label="$t('recharge.export.pageRange')"
          >
            <a-space>
              <a-input-number
                v-model="exportForm.page_start"
                :min="1"
                :max="exportInfo.sum_page || 1"
              />
              <span>{{ $t('recharge.export.to') }}</span>
              <a-input-number
                v-model="exportForm.page_end"
                :min="exportForm.page_start"
                :max="exportInfo.sum_page || 1"
              />
            </a-space>
          </a-form-item>
          <a-form-item
            field="file_name"
            :label="$t('recharge.export.fileName')"
          >
            <a-input v-model="exportForm.file_name" :max-length="100" />
          </a-form-item>
        </a-form>
      </a-spin>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import { useI18n } from 'vue-i18n';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import useLoading from '@/hooks/loading';
  import {
    exportRecharge,
    getRechargeExportInfo,
    getRechargeList,
    refundRecharge,
    type RechargeExportInfo,
    type RechargeParams,
    type RechargeRecord,
  } from '@/api/finance';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<RechargeRecord[]>([]);

  const generateFormModel = () => ({
    sn: '',
    user_info: '',
    pay_way: '' as string | number,
    pay_status: '' as string | number,
    timeRange: [] as string[],
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 25,
    total: 0,
    showTotal: true,
    showPageSize: true,
  });

  const payWayOptions = computed<SelectOptionData[]>(() => [
    { label: t('recharge.payWay.2'), value: 2 },
  ]);
  const payStatusOptions = computed<SelectOptionData[]>(() => [
    { label: t('recharge.payStatus.0'), value: 0 },
    { label: t('recharge.payStatus.1'), value: 1 },
  ]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('recharge.columns.user'), slotName: 'user', width: 180 },
    { title: t('recharge.columns.sn'), dataIndex: 'sn', width: 210 },
    {
      title: t('recharge.columns.orderAmount'),
      dataIndex: 'order_amount',
      width: 120,
    },
    {
      title: t('recharge.columns.payWay'),
      dataIndex: 'pay_way_text',
      width: 110,
    },
    {
      title: t('recharge.columns.payStatus'),
      slotName: 'pay_status',
      width: 110,
    },
    {
      title: t('recharge.columns.createTime'),
      dataIndex: 'create_time',
      width: 180,
    },
    {
      title: t('recharge.columns.payTime'),
      dataIndex: 'pay_time',
      width: 180,
    },
    {
      title: t('recharge.columns.operations'),
      slotName: 'operations',
      width: 120,
      fixed: 'right',
    },
  ]);

  const listParams = (pageNo: number): RechargeParams => {
    const params: RechargeParams = {
      sn: formModel.value.sn || undefined,
      user_info: formModel.value.user_info || undefined,
      pay_way:
        formModel.value.pay_way === '' ? undefined : formModel.value.pay_way,
      pay_status:
        formModel.value.pay_status === ''
          ? undefined
          : formModel.value.pay_status,
      page_no: pageNo,
      page_size: pagination.pageSize,
    };
    if (formModel.value.timeRange.length === 2) {
      [params.start_time, params.end_time] = formModel.value.timeRange;
    }
    return params;
  };

  const fetchData = async (pageNo = 1) => {
    setLoading(true);
    try {
      const { data } = await getRechargeList(listParams(pageNo));
      renderData.value = data.lists;
      pagination.current = data.page_no ?? data.pageNo ?? pageNo;
      pagination.pageSize =
        data.page_size ?? data.pageSize ?? pagination.pageSize;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };

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

  const refundingId = ref(0);
  const handleRefund = async (id: number) => {
    refundingId.value = id;
    try {
      await refundRecharge(id);
      Message.success(t('recharge.refund.success'));
      await fetchData(pagination.current);
    } finally {
      refundingId.value = 0;
    }
  };

  const emptyExportInfo = (): RechargeExportInfo => ({
    count: 0,
    page_size: pagination.pageSize,
    sum_page: 0,
    max_page: 0,
    all_max_size: 0,
    page_start: 1,
    page_end: 1,
    file_name: '',
  });
  const exportVisible = ref(false);
  const exportInfoLoading = ref(false);
  const exportLoading = ref(false);
  const exportInfo = reactive<RechargeExportInfo>(emptyExportInfo());
  const exportForm = reactive({
    page_type: 0 as 0 | 1,
    page_start: 1,
    page_end: 1,
    file_name: '',
  });

  const openExport = async () => {
    exportVisible.value = true;
    exportInfoLoading.value = true;
    try {
      const { data } = await getRechargeExportInfo(listParams(1));
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
      Message.error(t('recharge.export.invalidRange'));
      return false;
    }
    exportLoading.value = true;
    try {
      const { data } = await exportRecharge({
        ...listParams(1),
        ...exportForm,
      });
      const link = document.createElement('a');
      link.href = data.url;
      link.download = data.file_name || exportForm.file_name;
      link.rel = 'noopener';
      document.body.appendChild(link);
      link.click();
      link.remove();
      Message.success(t('recharge.export.success'));
      return true;
    } finally {
      exportLoading.value = false;
    }
  };

  fetchData();
</script>

<script lang="ts">
  export default { name: 'FinanceRecharge' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .pay-unpaid {
    color: rgb(var(--red-6));
  }
</style>
