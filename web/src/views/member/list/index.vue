<template>
  <div class="container">
    <Breadcrumb :items="['menu.member', 'menu.member.list']" />

    <a-card class="general-card search-card">
      <a-form :model="queryParams" layout="inline">
        <a-form-item field="keyword" :label="$t('member.filter.userInfo')">
          <a-input
            v-model="queryParams.keyword"
            :placeholder="$t('member.filter.keyword')"
            style="width: 240px"
            allow-clear
            @press-enter="search"
          />
        </a-form-item>
        <a-form-item :label="$t('member.filter.createTime')">
          <a-range-picker
            v-model="queryParams.createTime"
            value-format="YYYY-MM-DD"
            style="width: 260px"
            allow-clear
          />
        </a-form-item>
        <a-form-item field="channel" :label="$t('member.filter.channel')">
          <a-select
            v-model="queryParams.channel"
            :placeholder="$t('member.filter.channel.all')"
            :options="channelOptions"
            style="width: 160px"
            allow-clear
          />
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" @click="search">
              <template #icon><icon-search /></template>
              {{ $t('member.operation.search') }}
            </a-button>
            <a-button @click="resetQuery">
              <template #icon><icon-refresh /></template>
              {{ $t('member.operation.reset') }}
            </a-button>
            <a-button @click="openExport">
              <template #icon><icon-export /></template>
              {{ $t('member.operation.export') }}
            </a-button>
          </a-space>
        </a-form-item>
      </a-form>
    </a-card>

    <a-card class="general-card" :title="$t('menu.member.list')">
      <a-row style="margin-bottom: 16px">
        <a-button
          v-permission="['member/add']"
          type="primary"
          @click="handleAdd"
        >
          <template #icon><icon-plus /></template>
          {{ $t('member.operation.add') }}
        </a-button>
      </a-row>
      <a-table
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="renderData"
        :pagination="pagination"
        :bordered="{ cell: true }"
        :scroll="{ x: 1350 }"
        @page-change="onPageChange"
        @page-size-change="onPageSizeChange"
      >
        <template #avatar="{ record }">
          <a-avatar :size="46" :image-url="record.avatar">
            {{ record.nickname?.slice(0, 1) }}
          </a-avatar>
        </template>
        <template #status="{ record }">
          <a-tag :color="record.status === 1 ? 'green' : 'red'">
            {{
              record.status === 1
                ? $t('member.status.normal')
                : $t('member.status.disabled')
            }}
          </a-tag>
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['user.user/detail']"
              type="text"
              size="small"
              @click="openDetail(record)"
            >
              {{ $t('member.operation.detail') }}
            </a-button>
            <a-button
              v-permission="['member/status']"
              type="text"
              size="small"
              :status="record.status === 1 ? 'danger' : 'normal'"
              @click="handleToggleStatus(record)"
            >
              {{
                record.status === 1
                  ? $t('member.operation.disable')
                  : $t('member.operation.enable')
              }}
            </a-button>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="modalVisible"
      :title="$t('member.modal.addTitle')"
      :ok-loading="submitLoading"
      :mask-closable="false"
      @before-ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="formRules" layout="vertical">
        <a-form-item field="nickname" :label="$t('member.field.nickname')">
          <a-input
            v-model="form.nickname"
            :placeholder="$t('member.field.nickname.placeholder')"
          />
        </a-form-item>
        <a-form-item field="mobile" :label="$t('member.field.mobile')">
          <a-input v-model="form.mobile" />
        </a-form-item>
        <a-form-item field="email" :label="$t('member.field.email')">
          <a-input v-model="form.email" />
        </a-form-item>
        <a-form-item field="sex" :label="$t('member.field.sex')">
          <a-radio-group v-model="form.sex">
            <a-radio :value="0">{{ $t('member.sex.unknown') }}</a-radio>
            <a-radio :value="1">{{ $t('member.sex.male') }}</a-radio>
            <a-radio :value="2">{{ $t('member.sex.female') }}</a-radio>
          </a-radio-group>
        </a-form-item>
        <a-form-item field="tag_ids" :label="$t('member.field.tags')">
          <a-select v-model="form.tag_ids" multiple allow-clear>
            <a-option v-for="tag in tagOptions" :key="tag.id" :value="tag.id">
              {{ tag.name }}
            </a-option>
          </a-select>
        </a-form-item>
        <a-form-item field="status" :label="$t('member.field.status')">
          <a-radio-group v-model="form.status">
            <a-radio :value="1">{{ $t('member.status.normal') }}</a-radio>
            <a-radio :value="0">{{ $t('member.status.disabled') }}</a-radio>
          </a-radio-group>
        </a-form-item>
      </a-form>
    </a-modal>

    <a-drawer
      v-model:visible="detailDrawerVisible"
      :width="640"
      :title="$t('member.detail.title')"
      unmount-on-close
    >
      <a-spin :loading="detailLoading" style="width: 100%">
        <div class="detail-summary">
          <div class="detail-summary-item">
            <span class="detail-label">{{ $t('member.detail.avatar') }}</span>
            <a-avatar :size="58" :image-url="detail.avatar">
              {{ detail.nickname?.slice(0, 1) }}
            </a-avatar>
          </div>
          <div class="detail-summary-item">
            <span class="detail-label">{{ $t('member.detail.balance') }}</span>
            <div>
              ¥{{ Number(detail.user_money || 0).toFixed(2) }}
              <a-button
                v-permission="['user.user/adjustMoney']"
                type="text"
                size="small"
                @click="openBalanceFromDetail"
              >
                {{ $t('member.operation.adjustBalance') }}
              </a-button>
            </div>
          </div>
        </div>

        <a-descriptions :column="1" bordered size="large">
          <a-descriptions-item :label="$t('member.detail.nickname')">
            {{ detail.nickname || '-' }}
          </a-descriptions-item>
          <a-descriptions-item :label="$t('member.detail.account')">
            {{ detail.account || '-' }}
            <a-button
              v-permission="['user.user/edit']"
              type="text"
              size="mini"
              @click="openFieldEdit('account')"
            >
              {{ $t('member.operation.edit') }}
            </a-button>
          </a-descriptions-item>
          <a-descriptions-item :label="$t('member.detail.realName')">
            {{ detail.real_name || '-' }}
            <a-button
              v-permission="['user.user/edit']"
              type="text"
              size="mini"
              @click="openFieldEdit('real_name')"
            >
              {{ $t('member.operation.edit') }}
            </a-button>
          </a-descriptions-item>
          <a-descriptions-item :label="$t('member.detail.sex')">
            {{ detailSexLabel }}
            <a-button
              v-permission="['user.user/edit']"
              type="text"
              size="mini"
              @click="openFieldEdit('sex')"
            >
              {{ $t('member.operation.edit') }}
            </a-button>
          </a-descriptions-item>
          <a-descriptions-item :label="$t('member.detail.mobile')">
            {{ detail.mobile || '-' }}
            <a-button
              v-permission="['user.user/edit']"
              type="text"
              size="mini"
              @click="openFieldEdit('mobile')"
            >
              {{ $t('member.operation.edit') }}
            </a-button>
          </a-descriptions-item>
          <a-descriptions-item :label="$t('member.detail.channel')">
            {{ detail.channel || '-' }}
          </a-descriptions-item>
          <a-descriptions-item :label="$t('member.detail.createTime')">
            {{ detail.create_time || '-' }}
          </a-descriptions-item>
          <a-descriptions-item :label="$t('member.detail.loginTime')">
            {{ detail.login_time || '-' }}
          </a-descriptions-item>
        </a-descriptions>
      </a-spin>
    </a-drawer>

    <a-modal
      v-model:visible="fieldModalVisible"
      :title="$t('member.detail.editTitle', { field: fieldLabel })"
      :ok-loading="fieldLoading"
      :mask-closable="false"
      @before-ok="submitFieldEdit"
    >
      <a-form :model="fieldForm" layout="vertical">
        <a-form-item :label="fieldLabel" required>
          <a-select v-if="fieldForm.field === 'sex'" v-model="fieldForm.value">
            <a-option :value="0">{{ $t('member.sex.unknown') }}</a-option>
            <a-option :value="1">{{ $t('member.sex.male') }}</a-option>
            <a-option :value="2">{{ $t('member.sex.female') }}</a-option>
          </a-select>
          <a-input
            v-else
            v-model="fieldForm.value"
            :max-length="fieldForm.field === 'mobile' ? 11 : 32"
            show-word-limit
          />
        </a-form-item>
      </a-form>
    </a-modal>

    <a-modal
      v-model:visible="balanceModalVisible"
      :title="$t('member.balance.modalTitle')"
      :ok-loading="balanceLoading"
      :mask-closable="false"
      @before-ok="handleBalanceSubmit"
      @cancel="balanceModalVisible = false"
    >
      <a-form
        ref="balanceFormRef"
        :model="balanceForm"
        :rules="balanceRules"
        layout="vertical"
      >
        <a-form-item :label="$t('member.balance.current')">
          ¥{{ Number(detail.user_money || 0).toFixed(2) }}
        </a-form-item>
        <a-form-item field="action" :label="$t('member.balance.action')">
          <a-radio-group v-model="balanceForm.action">
            <a-radio :value="1">{{ $t('member.balance.increase') }}</a-radio>
            <a-radio :value="2">{{ $t('member.balance.decrease') }}</a-radio>
          </a-radio-group>
        </a-form-item>
        <a-form-item field="num" :label="$t('member.field.amount')">
          <a-input-number
            v-model="balanceForm.num"
            :placeholder="$t('member.field.amount.placeholder')"
            style="width: 100%"
            :precision="2"
            :min="0.01"
            :step="1"
          />
        </a-form-item>
        <a-form-item :label="$t('member.balance.after')">
          ¥{{ adjustedBalance.toFixed(2) }}
        </a-form-item>
        <a-form-item field="remark" :label="$t('member.field.remark')">
          <a-textarea
            v-model="balanceForm.remark"
            :placeholder="$t('member.field.remark.placeholder')"
            :max-length="128"
            show-word-limit
          />
        </a-form-item>
      </a-form>
    </a-modal>

    <a-modal
      v-model:visible="exportVisible"
      :title="$t('member.export.title')"
      :ok-text="$t('member.export.confirm')"
      :ok-loading="exportLoading"
      :mask-closable="false"
      width="540px"
      @before-ok="handleExport"
    >
      <a-spin :loading="exportInfoLoading" style="width: 100%">
        <a-form :model="exportForm" layout="vertical">
          <a-alert type="info" style="margin-bottom: 16px">
            {{
              $t('member.export.summary', {
                count: exportInfo.count,
                pages: exportInfo.sum_page,
                size: exportInfo.page_size,
              })
            }}
            <br />
            {{
              $t('member.export.limit', {
                pages: exportInfo.max_page,
                count: exportInfo.all_max_size,
              })
            }}
          </a-alert>
          <a-form-item field="page_type" :label="$t('member.export.range')">
            <a-radio-group v-model="exportForm.page_type">
              <a-radio :value="0">{{ $t('member.export.all') }}</a-radio>
              <a-radio :value="1">{{ $t('member.export.pages') }}</a-radio>
            </a-radio-group>
          </a-form-item>
          <a-form-item
            v-if="exportForm.page_type === 1"
            :label="$t('member.export.pageRange')"
          >
            <a-space>
              <a-input-number
                v-model="exportForm.page_start"
                :min="1"
                :max="exportInfo.sum_page || 1"
              />
              <span>{{ $t('member.export.to') }}</span>
              <a-input-number
                v-model="exportForm.page_end"
                :min="exportForm.page_start"
                :max="exportInfo.sum_page || 1"
              />
            </a-space>
          </a-form-item>
          <a-form-item field="file_name" :label="$t('member.export.fileName')">
            <a-input v-model="exportForm.file_name" :max-length="100" />
          </a-form-item>
        </a-form>
      </a-spin>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import { hasPermission } from '@/hooks/permission';
  import {
    addMember,
    adjustMemberMoney,
    exportMembers,
    getMemberDetail,
    getMemberExportInfo,
    getMemberList,
    getMemberTagList,
    updateMemberField,
    updateMemberStatus,
    type MemberDetail,
    type MemberEditableField,
    type MemberExportInfo,
    type MemberForm,
    type MemberListParams,
    type MemberRecord,
    type MemberTagRecord,
  } from '@/api/member';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<MemberRecord[]>([]);
  const tagOptions = ref<MemberTagRecord[]>([]);

  const queryParams = reactive({
    keyword: '',
    channel: '' as number | '',
    createTime: [] as string[],
  });
  const channelOptions = computed<SelectOptionData[]>(() => [
    { value: 1, label: t('member.channel.wechatMmp') },
    { value: 2, label: t('member.channel.wechatOa') },
    { value: 3, label: t('member.channel.h5') },
    { value: 4, label: t('member.channel.pc') },
    { value: 5, label: t('member.channel.ios') },
    { value: 6, label: t('member.channel.android') },
  ]);
  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
    showPageSize: true,
  });
  const columns = computed<TableColumnData[]>(() => [
    { title: t('member.columns.avatar'), slotName: 'avatar', width: 90 },
    { title: t('member.columns.nickname'), dataIndex: 'nickname', width: 140 },
    { title: t('member.columns.account'), dataIndex: 'account', width: 160 },
    { title: t('member.columns.mobile'), dataIndex: 'mobile', width: 140 },
    { title: t('member.columns.channel'), dataIndex: 'channel', width: 130 },
    {
      title: t('member.columns.createTime'),
      dataIndex: 'create_time',
      width: 180,
    },
    { title: t('member.columns.status'), slotName: 'status', width: 90 },
    { title: t('member.columns.balance'), dataIndex: 'balance', width: 100 },
    {
      title: t('member.columns.operations'),
      slotName: 'operations',
      width: 260,
      fixed: 'right',
    },
  ]);

  const listParams = (page = pagination.current): MemberListParams => ({
    keyword: queryParams.keyword || undefined,
    channel: queryParams.channel === '' ? undefined : queryParams.channel,
    create_time_start: queryParams.createTime[0]
      ? `${queryParams.createTime[0]} 00:00:00`
      : undefined,
    create_time_end: queryParams.createTime[1]
      ? `${queryParams.createTime[1]} 23:59:59`
      : undefined,
    page_no: page,
    page_size: pagination.pageSize,
  });

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const { data } = await getMemberList(listParams(page));
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };
  const search = () => fetchData(1);
  const resetQuery = () => {
    queryParams.keyword = '';
    queryParams.channel = '';
    queryParams.createTime = [];
    fetchData(1);
  };
  const onPageChange = (current: number) => fetchData(current);
  const onPageSizeChange = (pageSize: number) => {
    pagination.pageSize = pageSize;
    fetchData(1);
  };

  const modalVisible = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();
  const defaultForm = (): MemberForm => ({
    id: undefined,
    nickname: '',
    mobile: '',
    email: '',
    sex: 0,
    status: 1,
    tag_ids: [],
  });
  const form = reactive<MemberForm>(defaultForm());
  const formRules = {
    nickname: [
      { required: true, message: t('member.field.nickname.required') },
    ],
  };
  const resetForm = (patch: Partial<MemberForm> = {}) =>
    Object.assign(form, defaultForm(), patch);
  const handleAdd = () => {
    resetForm();
    modalVisible.value = true;
  };
  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return false;
    submitLoading.value = true;
    try {
      await addMember({ ...form });
      Message.success(t('member.tip.success'));
      modalVisible.value = false;
      await fetchData(pagination.current);
      return true;
    } finally {
      submitLoading.value = false;
    }
  };
  const handleToggleStatus = async (record: MemberRecord) => {
    const next = record.status === 1 ? 0 : 1;
    await updateMemberStatus(record.id, next);
    record.status = next;
    record.is_disable = next === 1 ? 0 : 1;
    Message.success(t('member.tip.success'));
  };

  const emptyDetail = (): MemberDetail => ({
    id: 0,
    sn: '',
    account: '',
    nickname: '',
    avatar: '',
    real_name: '',
    sex: 0,
    mobile: '',
    create_time: '',
    login_time: '',
    channel: '',
    user_money: 0,
    balance: 0,
  });
  const detailDrawerVisible = ref(false);
  const detailLoading = ref(false);
  const detail = reactive<MemberDetail>(emptyDetail());
  const detailSexLabel = computed(
    () =>
      ({
        0: t('member.sex.unknown'),
        1: t('member.sex.male'),
        2: t('member.sex.female'),
      }[detail.sex] || t('member.sex.unknown'))
  );
  const refreshDetail = async () => {
    if (!detail.id) return;
    const { data } = await getMemberDetail(detail.id);
    Object.assign(detail, data);
  };
  const openDetail = async (record: MemberRecord) => {
    Object.assign(detail, emptyDetail(), { id: record.id });
    detailDrawerVisible.value = true;
    detailLoading.value = true;
    try {
      await refreshDetail();
    } finally {
      detailLoading.value = false;
    }
  };

  const fieldModalVisible = ref(false);
  const fieldLoading = ref(false);
  const fieldForm = reactive<{
    field: MemberEditableField;
    value: any;
  }>({ field: 'account', value: '' });
  const fieldLabel = computed(
    () =>
      ({
        account: t('member.detail.account'),
        real_name: t('member.detail.realName'),
        sex: t('member.detail.sex'),
        mobile: t('member.detail.mobile'),
      }[fieldForm.field])
  );
  const openFieldEdit = (field: MemberEditableField) => {
    fieldForm.field = field;
    fieldForm.value = detail[field];
    fieldModalVisible.value = true;
  };
  const submitFieldEdit = async () => {
    if (fieldForm.value === '') {
      Message.warning(t('member.detail.valueRequired'));
      return false;
    }
    fieldLoading.value = true;
    try {
      await updateMemberField({
        id: detail.id,
        field: fieldForm.field,
        value: fieldForm.value,
      });
      Message.success(t('member.tip.success'));
      fieldModalVisible.value = false;
      await Promise.all([refreshDetail(), fetchData(pagination.current)]);
      return true;
    } catch {
      return false;
    } finally {
      fieldLoading.value = false;
    }
  };

  const balanceModalVisible = ref(false);
  const balanceLoading = ref(false);
  const balanceFormRef = ref<FormInstance>();
  const balanceForm = reactive<{ action: 1 | 2; num: number; remark: string }>({
    action: 1,
    num: 0,
    remark: '',
  });
  const balanceRules = {
    action: [{ required: true, message: t('member.balance.actionRequired') }],
    num: [{ required: true, message: t('member.field.amount.required') }],
  };
  const adjustedBalance = computed(
    () =>
      Number(detail.user_money || 0) +
      balanceForm.num * (balanceForm.action === 1 ? 1 : -1)
  );
  const openBalanceFromDetail = () => {
    balanceForm.action = 1;
    balanceForm.num = 0;
    balanceForm.remark = '';
    balanceModalVisible.value = true;
  };
  const handleBalanceSubmit = async () => {
    const err = await balanceFormRef.value?.validate();
    if (err) return false;
    if (balanceForm.num <= 0) {
      Message.warning(t('member.field.amount.positive'));
      return false;
    }
    balanceLoading.value = true;
    try {
      await adjustMemberMoney({
        user_id: detail.id,
        action: balanceForm.action,
        num: balanceForm.num,
        remark: balanceForm.remark,
      });
      Message.success(t('member.tip.success'));
      balanceModalVisible.value = false;
      await Promise.all([refreshDetail(), fetchData(pagination.current)]);
      return true;
    } catch {
      return false;
    } finally {
      balanceLoading.value = false;
    }
  };

  const emptyExportInfo = (): MemberExportInfo => ({
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
  const exportInfo = reactive<MemberExportInfo>(emptyExportInfo());
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
      const { data } = await getMemberExportInfo(listParams(1));
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
      Message.error(t('member.export.invalidRange'));
      return false;
    }
    exportLoading.value = true;
    try {
      const { data } = await exportMembers({
        ...listParams(1),
        ...exportForm,
      });
      const link = document.createElement('a');
      link.href = data.url;
      link.download = data.file_name;
      link.rel = 'noopener';
      document.body.appendChild(link);
      link.click();
      link.remove();
      Message.success(t('member.export.success'));
      return true;
    } finally {
      exportLoading.value = false;
    }
  };

  onMounted(async () => {
    const tagsPromise = hasPermission('member/tag/lists')
      ? getMemberTagList()
      : Promise.resolve(null);
    const [, tagsRes] = await Promise.all([fetchData(1), tagsPromise]);
    tagOptions.value = tagsRes?.data ?? [];
  });
</script>

<script lang="ts">
  export default { name: 'MemberList' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px;
  }

  .search-card {
    margin-bottom: 16px;
  }

  .detail-summary {
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding: 24px;
    margin-bottom: 20px;
    background: var(--color-fill-2);
    border-radius: 4px;
  }

  .detail-summary-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
  }

  .detail-label {
    color: var(--color-text-2);
  }
</style>
