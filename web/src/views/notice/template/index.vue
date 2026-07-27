<template>
  <div class="container">
    <!-- 搜索栏 -->
    <a-card :bordered="false" style="margin-bottom: 16px">
      <a-row :gutter="16" align="center">
        <a-col :span="6">
          <a-input
            v-model="filterForm.name"
            :placeholder="$t('noticeTemplate.filter.name')"
            allow-clear
            @press-enter="handleSearch"
          />
        </a-col>
        <a-col :span="5">
          <a-select
            v-model="filterForm.channel"
            :placeholder="$t('noticeTemplate.filter.channel')"
            allow-clear
            style="width: 100%"
          >
            <a-option value="1">{{ $t('noticeTemplate.channel.1') }}</a-option>
            <a-option value="2">{{ $t('noticeTemplate.channel.2') }}</a-option>
            <a-option value="3">{{ $t('noticeTemplate.channel.3') }}</a-option>
          </a-select>
        </a-col>
        <a-col :span="5">
          <a-select
            v-model="filterForm.is_disable"
            :placeholder="$t('noticeTemplate.filter.status')"
            allow-clear
            style="width: 100%"
          >
            <a-option value="0">{{ $t('noticeTemplate.status.enabled') }}</a-option>
            <a-option value="1">{{ $t('noticeTemplate.status.disabled') }}</a-option>
          </a-select>
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
        <a-col flex="none">
          <a-button type="primary" @click="openAddModal">
            <template #icon><icon-plus /></template>
            {{ $t('noticeTemplate.operation.add') }}
          </a-button>
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
          <a-table-column :title="$t('noticeTemplate.columns.name')" data-index="name" />
          <a-table-column :title="$t('noticeTemplate.columns.code')" data-index="code" />
          <a-table-column :title="$t('noticeTemplate.columns.channel')" data-index="channel">
            <template #cell="{ record }">
              <a-tag :color="channelColor(record.channel)">
                {{ $t(`noticeTemplate.channel.${record.channel}`) }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column :title="$t('noticeTemplate.columns.is_disable')" data-index="is_disable">
            <template #cell="{ record }">
              <a-tag :color="record.is_disable ? 'red' : 'green'">
                {{ record.is_disable ? $t('noticeTemplate.status.disabled') : $t('noticeTemplate.status.enabled') }}
              </a-tag>
            </template>
          </a-table-column>
          <a-table-column :title="$t('noticeTemplate.columns.remark')" data-index="remark" />
          <a-table-column :title="$t('noticeTemplate.columns.create_time')" data-index="create_time">
            <template #cell="{ record }">
              {{ formatTime(record.create_time) }}
            </template>
          </a-table-column>
          <a-table-column :title="$t('noticeTemplate.columns.operations')" align="center" :width="120">
            <template #cell="{ record }">
              <a-space>
                <a-button type="text" size="small" @click="openEditModal(record)">
                  {{ $t('noticeTemplate.operation.edit') }}
                </a-button>
                <a-popconfirm
                  :content="$t('noticeTemplate.delete.confirm')"
                  @ok="handleDelete(record.id)"
                >
                  <a-button type="text" size="small" status="danger">
                    {{ $t('noticeTemplate.operation.delete') }}
                  </a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- 新增/编辑弹窗 -->
    <a-modal
      v-model:visible="modalVisible"
      :title="editingId ? $t('noticeTemplate.modal.editTitle') : $t('noticeTemplate.modal.addTitle')"
      :width="600"
      @before-ok="handleSubmit"
      @cancel="resetModal"
    >
      <a-form :model="modalForm" layout="vertical" ref="formRef">
        <a-form-item
          field="name"
          :label="$t('noticeTemplate.field.name')"
          :rules="[{ required: true, message: $t('noticeTemplate.field.name') }]"
        >
          <a-input v-model="modalForm.name" allow-clear />
        </a-form-item>
        <a-form-item
          field="code"
          :label="$t('noticeTemplate.field.code')"
          :rules="[{ required: true, message: $t('noticeTemplate.field.code') }]"
          :extra="$t('noticeTemplate.field.code.tip')"
        >
          <a-input v-model="modalForm.code" allow-clear />
        </a-form-item>
        <a-form-item
          field="channel"
          :label="$t('noticeTemplate.field.channel')"
          :rules="[{ required: true, message: $t('noticeTemplate.field.channel') }]"
        >
          <a-select v-model="modalForm.channel" style="width: 160px">
            <a-option :value="1">{{ $t('noticeTemplate.channel.1') }}</a-option>
            <a-option :value="2">{{ $t('noticeTemplate.channel.2') }}</a-option>
            <a-option :value="3">{{ $t('noticeTemplate.channel.3') }}</a-option>
          </a-select>
        </a-form-item>
        <a-form-item
          field="title"
          :label="$t('noticeTemplate.field.title')"
          :extra="$t('noticeTemplate.field.title.tip')"
        >
          <a-input v-model="modalForm.title" allow-clear />
        </a-form-item>
        <a-form-item
          field="content"
          :label="$t('noticeTemplate.field.content')"
          :rules="[{ required: true, message: $t('noticeTemplate.field.content') }]"
          :extra="$t('noticeTemplate.field.content.tip')"
        >
          <a-textarea v-model="modalForm.content" :auto-size="{ minRows: 3, maxRows: 8 }" allow-clear />
        </a-form-item>
        <a-form-item field="is_disable" :label="$t('noticeTemplate.field.is_disable')">
          <a-switch
            v-model="modalForm.is_disable"
            :checked-value="1"
            :unchecked-value="0"
            checked-color="red"
          />
        </a-form-item>
        <a-form-item field="remark" :label="$t('noticeTemplate.field.remark')">
          <a-input v-model="modalForm.remark" allow-clear />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Message } from '@arco-design/web-vue';
import {
  getNoticeTemplateList,
  addNoticeTemplate,
  editNoticeTemplate,
  deleteNoticeTemplate,
  NoticeTemplateRecord,
} from '@/api/notice';

const { t } = useI18n();

// ─── 列表 ─────────────────────────────────────────────────────────────────────
const loading = ref(false);
const tableData = ref<NoticeTemplateRecord[]>([]);
const pagination = reactive({ current: 1, pageSize: 15, total: 0, showTotal: true });

const filterForm = reactive({ name: '', channel: '', is_disable: '' });

const fetchData = async () => {
  loading.value = true;
  try {
    const res = await getNoticeTemplateList({
      ...filterForm,
      page: pagination.current,
      limit: pagination.pageSize,
    });
    const payload = (res.data as unknown as { data: { list: NoticeTemplateRecord[]; total: number } }).data;
    tableData.value = payload.list;
    pagination.total = payload.total;
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => { pagination.current = 1; fetchData(); };
const handleReset = () => {
  filterForm.name = '';
  filterForm.channel = '';
  filterForm.is_disable = '';
  handleSearch();
};
const onPageChange = (page: number) => { pagination.current = page; fetchData(); };

// ─── 增删改 ────────────────────────────────────────────────────────────────────
const modalVisible = ref(false);
const editingId = ref<number | null>(null);
const formRef = ref();
const modalForm = reactive<Partial<NoticeTemplateRecord>>({
  name: '', code: '', channel: 1, title: '', content: '', is_disable: 0, remark: '',
});

const openAddModal = () => {
  editingId.value = null;
  Object.assign(modalForm, { name: '', code: '', channel: 1, title: '', content: '', is_disable: 0, remark: '' });
  modalVisible.value = true;
};

const openEditModal = (record: NoticeTemplateRecord) => {
  editingId.value = record.id;
  Object.assign(modalForm, record);
  modalVisible.value = true;
};

const resetModal = () => { editingId.value = null; formRef.value?.resetFields(); };

const handleSubmit = async (done: (closed: boolean) => void) => {
  const err = await formRef.value?.validate();
  if (err) { done(false); return; }
  try {
    if (editingId.value) {
      await editNoticeTemplate({ ...modalForm, id: editingId.value });
    } else {
      await addNoticeTemplate({ ...modalForm });
    }
    Message.success(t('noticeTemplate.tip.success'));
    fetchData();
    done(true);
  } catch {
    done(false);
  }
};

const handleDelete = async (id: number) => {
  await deleteNoticeTemplate([id]);
  Message.success(t('noticeTemplate.tip.success'));
  fetchData();
};

// ─── 工具 ─────────────────────────────────────────────────────────────────────
const channelColor = (ch: number) => ({ 1: 'blue', 2: 'purple', 3: 'orange' }[ch] ?? 'gray');
const formatTime = (ts: number) => new Date(ts * 1000).toLocaleString();

onMounted(fetchData);
</script>
