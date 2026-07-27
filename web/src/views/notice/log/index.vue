<template>
  <div class="container">
    <!-- 搜索栏 -->
    <a-card :bordered="false" style="margin-bottom: 16px">
      <a-row :gutter="16" align="center">
        <a-col :span="6">
          <a-input
            v-model="filterForm.receiver"
            :placeholder="$t('noticeLog.filter.receiver')"
            allow-clear
            @press-enter="handleSearch"
          />
        </a-col>
        <a-col :span="4">
          <a-select
            v-model="filterForm.channel"
            :placeholder="$t('noticeLog.filter.channel')"
            allow-clear
            style="width: 100%"
          >
            <a-option value="1">{{ $t('noticeLog.channel.1') }}</a-option>
            <a-option value="2">{{ $t('noticeLog.channel.2') }}</a-option>
            <a-option value="3">{{ $t('noticeLog.channel.3') }}</a-option>
          </a-select>
        </a-col>
        <a-col :span="4">
          <a-select
            v-model="filterForm.status"
            :placeholder="$t('noticeLog.filter.status')"
            allow-clear
            style="width: 100%"
          >
            <a-option value="0">{{ $t('noticeLog.status.0') }}</a-option>
            <a-option value="1">{{ $t('noticeLog.status.1') }}</a-option>
            <a-option value="2">{{ $t('noticeLog.status.2') }}</a-option>
          </a-select>
        </a-col>
        <a-col :span="7">
          <a-range-picker
            v-model="filterForm.timeRange"
            style="width: 100%"
            :placeholder="[$t('noticeLog.filter.time'), $t('noticeLog.filter.time')]"
          />
        </a-col>
        <a-col :flex="1">
          <a-space>
            <a-button type="primary" @click="handleSearch">
              <template #icon><icon-search /></template>
              {{ $t('form.search') }}
            </a-button>
            <a-button @click="handleReset">{{ $t('form.reset') }}</a-button>
          </a-space>
        </a-col>
      </a-row>
    </a-card>

    <!-- 表格 -->
    <a-card :bordered="false">
      <a-table
        :data="tableData"
        :loading="loading"
        :pagination="pagination"
        row-key="id"
        @page-change="onPageChange"
      >
        <template #columns>
          <a-table-column title="ID" data-index="id" :width="70" />
          <a-table-column :title="$t('noticeLog.columns.template_name')" data-index="template_name" />
          <a-table-column :title="$t('noticeLog.columns.channel')" data-index="channel" :width="80">
            <template #cell="{ record }">
              <a-tag :color="channelColor(record.channel)">
                {{ $t(`noticeLog.channel.${record.channel}`) }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column :title="$t('noticeLog.columns.receiver')" data-index="receiver" />
          <a-table-column :title="$t('noticeLog.columns.title')" data-index="title" :ellipsis="true" :tooltip="true" />
          <a-table-column :title="$t('noticeLog.columns.status')" data-index="status" :width="90">
            <template #cell="{ record }">
              <a-tag :color="statusColor(record.status)">
                {{ $t(`noticeLog.status.${record.status}`) }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column :title="$t('noticeLog.columns.send_time')" data-index="send_time" :width="160">
            <template #cell="{ record }">
              {{ formatTime(record.send_time) }}
            </template>
          </a-table-column>
          <a-table-column :title="$t('noticeLog.columns.operations')" align="center" :width="80">
            <template #cell="{ record }">
              <a-button type="text" size="small" @click="openDetail(record)">
                {{ $t('form.detail') }}
              </a-button>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- 详情弹窗 -->
    <a-modal
      v-model:visible="detailVisible"
      :title="$t('noticeLog.detail.title')"
      :footer="false"
      :width="600"
    >
      <a-descriptions :data="detailItems" bordered :column="1" />
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { getNoticeLogList, NoticeLogRecord } from '@/api/notice';

const { t } = useI18n();

// ─── 列表 ─────────────────────────────────────────────────────────────────────
const loading = ref(false);
const tableData = ref<NoticeLogRecord[]>([]);
const pagination = reactive({ current: 1, pageSize: 15, total: 0, showTotal: true });

const filterForm = reactive({
  receiver: '',
  channel: '',
  status: '',
  timeRange: [] as string[],
});

const fetchData = async () => {
  loading.value = true;
  try {
    const params: Record<string, unknown> = {
      page: pagination.current,
      limit: pagination.pageSize,
    };
    if (filterForm.receiver) params.receiver = filterForm.receiver;
    if (filterForm.channel)  params.channel  = filterForm.channel;
    if (filterForm.status)   params.status   = filterForm.status;
    if (filterForm.timeRange?.length === 2) {
      params.start_time = Math.floor(new Date(filterForm.timeRange[0]).getTime() / 1000);
      params.end_time   = Math.floor(new Date(filterForm.timeRange[1]).getTime() / 1000);
    }
    const res = await getNoticeLogList(params as Parameters<typeof getNoticeLogList>[0]);
    const payload = (res.data as unknown as { data: { list: NoticeLogRecord[]; total: number } }).data;
    tableData.value = payload.list;
    pagination.total = payload.total;
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => { pagination.current = 1; fetchData(); };
const handleReset = () => {
  filterForm.receiver  = '';
  filterForm.channel   = '';
  filterForm.status    = '';
  filterForm.timeRange = [];
  handleSearch();
};
const onPageChange = (page: number) => { pagination.current = page; fetchData(); };

// ─── 详情 ─────────────────────────────────────────────────────────────────────
const detailVisible = ref(false);
const currentRecord = ref<NoticeLogRecord | null>(null);

const openDetail = (record: NoticeLogRecord) => {
  currentRecord.value = record;
  detailVisible.value = true;
};

const detailItems = computed(() => {
  const r = currentRecord.value;
  if (!r) return [];
  return [
    { label: 'ID', value: String(r.id) },
    { label: t('noticeLog.columns.template_name'), value: r.template_name },
    { label: t('noticeLog.columns.channel'), value: t(`noticeLog.channel.${r.channel}`) },
    { label: t('noticeLog.columns.receiver'), value: r.receiver },
    { label: t('noticeLog.columns.title'), value: r.title || '-' },
    { label: t('noticeLog.detail.content'), value: r.content || '-' },
    { label: t('noticeLog.columns.status'), value: t(`noticeLog.status.${r.status}`) },
    { label: t('noticeLog.detail.error'), value: r.error || '-' },
    { label: t('noticeLog.columns.send_time'), value: formatTime(r.send_time) },
  ];
});

// ─── 工具 ─────────────────────────────────────────────────────────────────────
const channelColor = (ch: number) => ({ 1: 'blue', 2: 'purple', 3: 'orange' }[ch] ?? 'gray');
const statusColor  = (s: number)  => ({ 0: 'gray', 1: 'green', 2: 'red' }[s] ?? 'gray');
const formatTime   = (ts: number) => ts ? new Date(ts * 1000).toLocaleString() : '-';

onMounted(fetchData);
</script>
