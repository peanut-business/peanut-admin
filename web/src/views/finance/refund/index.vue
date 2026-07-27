<template>
  <div class="container">
    <!-- 统计卡片 -->
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

    <!-- 搜索条件 -->
    <a-card :bordered="false" style="margin-bottom: 16px">
      <a-form :model="params" layout="inline">
        <a-form-item :label="$t('refund.filter.sn')">
          <a-input
            v-model="params.sn"
            :placeholder="$t('refund.filter.sn.placeholder')"
            allow-clear
            style="width: 200px"
            @press-enter="handleSearch"
          />
        </a-form-item>
        <a-form-item :label="$t('refund.filter.order_sn')">
          <a-input
            v-model="params.order_sn"
            :placeholder="$t('refund.filter.order_sn.placeholder')"
            allow-clear
            style="width: 200px"
            @press-enter="handleSearch"
          />
        </a-form-item>
        <a-form-item :label="$t('refund.filter.user_info')">
          <a-input
            v-model="params.user_info"
            :placeholder="$t('refund.filter.user_info.placeholder')"
            allow-clear
            style="width: 200px"
            @press-enter="handleSearch"
          />
        </a-form-item>
        <a-form-item :label="$t('refund.filter.refund_type')">
          <a-select
            v-model="params.refund_type"
            style="width: 130px"
            allow-clear
          >
            <a-option value="">全部</a-option>
            <a-option :value="1">后台退款</a-option>
          </a-select>
        </a-form-item>
        <a-form-item :label="$t('refund.filter.time')">
          <a-range-picker v-model="timeRange" style="width: 280px" />
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" @click="handleSearch">查询</a-button>
            <a-button @click="handleReset">重置</a-button>
          </a-space>
        </a-form-item>
      </a-form>
    </a-card>

    <!-- 表格 -->
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
        :pagination="{
          total,
          current: params.page,
          pageSize: params.limit,
          showTotal: true,
        }"
        row-key="id"
        @page-change="(p: number) => { params.page = p; fetchList(); }"
        @page-size-change="(s: number) => { params.limit = s; params.page = 1; fetchList(); }"
      >
        <template #columns>
          <a-table-column
            :title="$t('refund.col.sn')"
            data-index="sn"
            :width="190"
          />
          <a-table-column :title="$t('refund.col.user')" :width="160">
            <template #cell="{ record }">
              <a-space>
                <a-avatar
                  v-if="record.avatar"
                  :image-url="record.avatar"
                  :size="32"
                />
                <span>{{ record.nickname }}</span>
              </a-space>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.col.order_sn')"
            data-index="order_sn"
            :width="190"
          />
          <a-table-column :title="$t('refund.col.refund_amount')" :width="110">
            <template #cell="{ record }">
              ¥{{ record.refund_amount }}
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.col.refund_type')"
            data-index="refund_type_text"
            :width="100"
          />
          <a-table-column :title="$t('refund.col.refund_status')" :width="100">
            <template #cell="{ record }">
              <a-tag
                :color="
                  record.refund_status === 0
                    ? 'orange'
                    : record.refund_status === 1
                    ? 'green'
                    : 'red'
                "
              >
                {{ record.refund_status_text }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.col.create_time')"
            data-index="create_time"
            :width="160"
          >
            <template #cell="{ record }">
              {{ formatTime(record.create_time) }}
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.col.action')"
            :width="120"
            fixed="right"
          >
            <template #cell="{ record }">
              <a-button type="text" size="small" @click="openLog(record.id)">
                {{ $t('refund.action.log') }}
              </a-button>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- 退款日志 Drawer -->
    <a-drawer
      v-model:visible="logVisible"
      :title="$t('refund.log.title')"
      :width="680"
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
            :width="190"
          />
          <a-table-column
            :title="$t('refund.log.col.refund_amount')"
            :width="110"
          >
            <template #cell="{ record }">
              ¥{{ record.refund_amount }}
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.log.col.refund_status')"
            :width="100"
          >
            <template #cell="{ record }">
              <a-tag
                :color="
                  record.refund_status === 0
                    ? 'orange'
                    : record.refund_status === 1
                    ? 'green'
                    : 'red'
                "
              >
                {{ record.refund_status_text }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column
            :title="$t('refund.log.col.create_time')"
            data-index="create_time"
            :width="160"
          >
            <template #cell="{ record }">
              {{ formatTime(record.create_time) }}
            </template>
          </a-table-column>
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
  import { reactive, ref, onMounted } from 'vue';
  import {
    getRefundStat,
    getRefundRecords,
    getRefundLog,
    RefundStat,
    RefundRecord,
    RefundListRes,
  } from '@/api/finance';

  // ── 统计 ──────────────────────────────────────────────────────────────────
  const stat = reactive<RefundStat>({ total: 0, ing: 0, success: 0, error: 0 });

  const fetchStat = async () => {
    const res = await getRefundStat();
    const data = (res.data as any).data as RefundStat;
    Object.assign(stat, data);
  };

  // ── tabs ──────────────────────────────────────────────────────────────────
  const tabs = [
    { key: '', label: 'refund.tab.all', extendKey: 'total' },
    { key: '0', label: 'refund.tab.ing', extendKey: 'ing' },
    { key: '1', label: 'refund.tab.success', extendKey: 'success' },
    { key: '2', label: 'refund.tab.error', extendKey: 'error' },
  ] as const;
  const activeTab = ref('');
  const extend = reactive({ total: 0, ing: 0, success: 0, error: 0 });

  // ── 搜索参数 ───────────────────────────────────────────────────────────────
  const timeRange = ref<string[]>([]);
  const params = reactive({
    sn: '',
    order_sn: '',
    user_info: '',
    refund_type: '' as string,
    refund_status: '' as string,
    start_time: '' as number | string,
    end_time: '' as number | string,
    page: 1,
    limit: 15,
  });

  // ── 列表 ───────────────────────────────────────────────────────────────────
  const list = ref<RefundRecord[]>([]);
  const total = ref(0);
  const loading = ref(false);

  const formatTime = (val: number | string): string => {
    if (!val) return '';
    if (typeof val === 'string') return val;
    const d = new Date(val * 1000);
    return d.toLocaleString('zh-CN', { hour12: false });
  };

  const fetchList = async () => {
    loading.value = true;
    // compute timestamps from timeRange v-model
    if (timeRange.value && timeRange.value.length === 2) {
      params.start_time = Math.floor(new Date(timeRange.value[0]).getTime() / 1000);
      params.end_time = Math.floor(new Date(timeRange.value[1]).getTime() / 1000);
    } else {
      params.start_time = '';
      params.end_time = '';
    }
    try {
      const res = await getRefundRecords({ ...params });
      const data = (res.data as any).data as RefundListRes;
      list.value = data.lists;
      total.value = data.count;
      Object.assign(extend, data.extend);
    } finally {
      loading.value = false;
    }
  };

  const handleTabChange = (key: string | number) => {
    params.refund_status = String(key);
    params.page = 1;
    fetchList();
  };

  const handleSearch = () => {
    params.page = 1;
    fetchList();
  };

  const handleReset = () => {
    Object.assign(params, {
      sn: '',
      order_sn: '',
      user_info: '',
      refund_type: '',
      refund_status: activeTab.value,
      start_time: '',
      end_time: '',
      page: 1,
    });
    timeRange.value = [];
    fetchList();
  };

  // ── 退款日志 ───────────────────────────────────────────────────────────────
  const logVisible = ref(false);
  const logLoading = ref(false);
  const logList = ref<any[]>([]);

  const openLog = async (recordId: number) => {
    logVisible.value = true;
    logLoading.value = true;
    logList.value = [];
    try {
      const res = await getRefundLog(recordId);
      logList.value = (res.data as any).data as any[];
    } finally {
      logLoading.value = false;
    }
  };

  onMounted(() => {
    fetchStat();
    fetchList();
  });
</script>
