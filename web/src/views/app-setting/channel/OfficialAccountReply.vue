<template>
  <a-spin :loading="loading" style="width: 100%">
    <a-alert v-if="!canView" type="warning">
      {{ $t('channel.officialAccount.permissionDenied') }}
    </a-alert>
    <template v-else>
      <a-row align="center" justify="space-between" style="margin: 16px 0">
        <a-col>
          <a-select
            v-model="filterType"
            allow-clear
            style="width: 180px"
            :placeholder="$t('channel.reply.filterType')"
            @change="onTypeChange"
          >
            <a-option :value="''">{{ $t('channel.reply.allTypes') }}</a-option>
            <a-option :value="1">{{ $t('channel.reply.subscribe') }}</a-option>
            <a-option :value="2">{{ $t('channel.reply.keyword') }}</a-option>
            <a-option :value="3">{{ $t('channel.reply.default') }}</a-option>
          </a-select>
        </a-col>
        <a-col>
          <a-button
            v-permission="['setting/official-account/reply/add']"
            type="primary"
            @click="handleAdd"
          >
            {{ $t('channel.reply.add') }}
          </a-button>
        </a-col>
      </a-row>
      <a-table
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="rows"
        :pagination="pagination"
        :bordered="{ cell: true }"
        @page-change="onPageChange"
      >
        <template #reply_type="{ record }">
          {{ replyTypeLabel(record.reply_type) }}
        </template>
        <template #matching_type="{ record }">
          <span v-if="record.reply_type === 2">
            {{ matchingTypeLabel(record.matching_type) }}
          </span>
          <span v-else>-</span>
        </template>
        <template #content_type>
          {{ $t('channel.reply.text') }}
        </template>
        <template #status="{ record }">
          <a-switch
            :model-value="record.status === 1"
            v-permission="['setting/official-account/reply/status']"
            @change="(value) => handleStatus(record, value as boolean)"
          />
          <span v-if="!hasStatusPermission">
            {{ record.status === 1 ? $t('channel.reply.enabled') : $t('channel.reply.disabled') }}
          </span>
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['setting/official-account/reply/edit']"
              type="text"
              size="small"
              @click="handleEdit(record)"
            >
              {{ $t('channel.reply.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('channel.reply.deleteConfirm')"
              @ok="handleDelete(record)"
            >
              <a-button
                v-permission="['setting/official-account/reply/delete']"
                type="text"
                status="danger"
                size="small"
              >
                {{ $t('channel.reply.delete') }}
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </template>
  </a-spin>

  <a-modal
    v-model:visible="modalVisible"
    :title="isEdit ? $t('channel.reply.editTitle') : $t('channel.reply.addTitle')"
    :ok-loading="submitLoading"
    :mask-closable="false"
    @ok="handleSubmit"
    @cancel="modalVisible = false"
  >
    <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
      <a-form-item field="reply_type" :label="$t('channel.reply.type')">
        <a-select v-model="form.reply_type">
          <a-option :value="1">{{ $t('channel.reply.subscribe') }}</a-option>
          <a-option :value="2">{{ $t('channel.reply.keyword') }}</a-option>
          <a-option :value="3">{{ $t('channel.reply.default') }}</a-option>
        </a-select>
      </a-form-item>
      <a-form-item field="name" :label="$t('channel.reply.name')">
        <a-input v-model="form.name" :max-length="100" />
      </a-form-item>
      <template v-if="form.reply_type === 2">
        <a-form-item field="keyword" :label="$t('channel.reply.keywordValue')">
          <a-input v-model="form.keyword" :max-length="255" />
        </a-form-item>
        <a-form-item
          field="matching_type"
          :label="$t('channel.reply.matchingType')"
        >
          <a-radio-group v-model="form.matching_type">
            <a-radio :value="1">{{ $t('channel.reply.exact') }}</a-radio>
            <a-radio :value="2">{{ $t('channel.reply.fuzzy') }}</a-radio>
          </a-radio-group>
        </a-form-item>
      </template>
      <a-form-item :label="$t('channel.reply.contentType')">
        <a-tag color="arcoblue">{{ $t('channel.reply.text') }}</a-tag>
      </a-form-item>
      <a-form-item field="content" :label="$t('channel.reply.content')">
        <a-textarea
          v-model="form.content"
          :max-length="5000"
          show-word-limit
          :auto-size="{ minRows: 4, maxRows: 10 }"
        />
      </a-form-item>
      <a-form-item field="status" :label="$t('channel.reply.status')">
        <a-switch
          v-model="form.status"
          :checked-value="1"
          :unchecked-value="0"
        />
      </a-form-item>
      <a-form-item
        v-if="form.reply_type === 2"
        field="sort"
        :label="$t('channel.reply.sort')"
      >
        <a-input-number v-model="form.sort" :min="0" :precision="0" />
      </a-form-item>
    </a-form>
  </a-modal>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import { hasPermission } from '@/hooks/permission';
  import {
    addOfficialAccountReply,
    deleteOfficialAccountReply,
    editOfficialAccountReply,
    getOfficialAccountReplyDetail,
    getOfficialAccountReplyList,
    updateOfficialAccountReplyStatus,
    type OfficialAccountMatchingType,
    type OfficialAccountReplyForm,
    type OfficialAccountReplyRecord,
    type OfficialAccountReplyType,
  } from '@/api/official-account';

  const { t } = useI18n();
  const canView = computed(() =>
    hasPermission('setting/official-account/reply/lists')
  );
  const hasStatusPermission = computed(() =>
    hasPermission('setting/official-account/reply/status')
  );
  const loading = ref(false);
  const rows = ref<OfficialAccountReplyRecord[]>([]);
  const filterType = ref<OfficialAccountReplyType | ''>('');
  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });
  const columns = computed<TableColumnData[]>(() => [
    { title: t('channel.reply.columns.name'), dataIndex: 'name' },
    {
      title: t('channel.reply.columns.type'),
      slotName: 'reply_type',
      width: 110,
    },
    {
      title: t('channel.reply.columns.keyword'),
      dataIndex: 'keyword',
      width: 160,
    },
    {
      title: t('channel.reply.columns.matchingType'),
      slotName: 'matching_type',
      width: 110,
    },
    {
      title: t('channel.reply.columns.contentType'),
      slotName: 'content_type',
      width: 90,
    },
    { title: t('channel.reply.columns.content'), dataIndex: 'content' },
    {
      title: t('channel.reply.columns.status'),
      slotName: 'status',
      width: 100,
    },
    { title: t('channel.reply.columns.sort'), dataIndex: 'sort', width: 80 },
    {
      title: t('channel.reply.columns.operations'),
      slotName: 'operations',
      width: 170,
    },
  ]);

  const defaultForm = (): OfficialAccountReplyForm => ({
    name: '',
    keyword: '',
    reply_type: 1,
    matching_type: 1,
    content_type: 1,
    content: '',
    status: 1,
    sort: 0,
  });
  const form = reactive<OfficialAccountReplyForm>(defaultForm());
  const formRef = ref<FormInstance>();
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const detailLoading = ref(false);
  const rules = {
    reply_type: [{ required: true }],
    name: [{ required: true, message: t('channel.reply.nameRequired') }],
    keyword: [
      {
        validator: (value: string, callback: (error?: string) => void) => {
          if (form.reply_type === 2 && !value.trim()) {
            callback(t('channel.reply.keywordRequired'));
            return;
          }
          callback();
        },
      },
    ],
    content: [
      { required: true, message: t('channel.reply.contentRequired') },
    ],
    status: [{ required: true }],
    sort: [
      {
        validator: (value: number, callback: (error?: string) => void) => {
          if (form.reply_type === 2 && (!Number.isInteger(value) || value < 0)) {
            callback(t('channel.reply.sortInvalid'));
            return;
          }
          callback();
        },
      },
    ],
  };

  const fetchData = async (page = 1) => {
    if (!canView.value) return;
    loading.value = true;
    try {
      const { data } = await getOfficialAccountReplyList({
        reply_type: filterType.value || undefined,
        page_no: page,
        page_size: pagination.pageSize,
      });
      rows.value = data.list;
      pagination.current = data.page_no;
      pagination.total = data.total;
    } finally {
      loading.value = false;
    }
  };
  onMounted(() => fetchData());

  const onTypeChange = (value: unknown) => {
    if (value === '') filterType.value = '';
    else if (typeof value === 'string' || typeof value === 'number') {
      filterType.value = Number(value) as OfficialAccountReplyType;
    } else filterType.value = '';
    fetchData(1);
  };
  const onPageChange = (page: number) => fetchData(page);

  const replyTypeLabel = (value: OfficialAccountReplyType) => {
    if (value === 1) return t('channel.reply.subscribe');
    if (value === 2) return t('channel.reply.keyword');
    return t('channel.reply.default');
  };
  const matchingTypeLabel = (value: OfficialAccountMatchingType) =>
    t(value === 1 ? 'channel.reply.exact' : 'channel.reply.fuzzy');

  const resetForm = (patch: Partial<OfficialAccountReplyForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = async (record: OfficialAccountReplyRecord) => {
    isEdit.value = true;
    detailLoading.value = true;
    try {
      const { data } = await getOfficialAccountReplyDetail(record.id);
      resetForm({ ...data });
      modalVisible.value = true;
    } finally {
      detailLoading.value = false;
    }
  };

  const handleSubmit = async () => {
    const errors = await formRef.value?.validate();
    if (errors) return;
    if (!form.name.trim() || !form.content.trim()) {
      Message.error(t('channel.reply.contentRequired'));
      return;
    }
    if (form.reply_type === 2) {
      if (!form.keyword.trim()) {
        Message.error(t('channel.reply.keywordRequired'));
        return;
      }
      if (!Number.isInteger(form.sort) || form.sort < 0) {
        Message.error(t('channel.reply.sortInvalid'));
        return;
      }
    }
    const payload: OfficialAccountReplyForm = {
      ...(form.id ? { id: form.id } : {}),
      name: form.name.trim(),
      keyword: form.reply_type === 2 ? form.keyword.trim() : '',
      reply_type: form.reply_type,
      matching_type: form.reply_type === 2 ? form.matching_type : 1,
      content_type: 1,
      content: form.content.trim(),
      status: form.status,
      sort: form.reply_type === 2 ? form.sort : 0,
    };
    submitLoading.value = true;
    try {
      if (isEdit.value) await editOfficialAccountReply(payload);
      else await addOfficialAccountReply(payload);
      Message.success(t('channel.tip.success'));
      modalVisible.value = false;
      await fetchData(pagination.current);
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: OfficialAccountReplyRecord) => {
    await deleteOfficialAccountReply(record.id);
    Message.success(t('channel.tip.success'));
    await fetchData(pagination.current);
  };

  const handleStatus = async (
    record: OfficialAccountReplyRecord,
    enabled: boolean
  ) => {
    const status: 0 | 1 = enabled ? 1 : 0;
    await updateOfficialAccountReplyStatus(record.id, status);
    record.status = status;
    Message.success(t('channel.tip.success'));
  };
</script>

<script lang="ts">
  export default { name: 'OfficialAccountReply' };
</script>
