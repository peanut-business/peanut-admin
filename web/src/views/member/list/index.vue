<template>
  <div class="container">
    <Breadcrumb :items="['menu.member', 'menu.member.list']" />
    <a-card class="general-card" :title="$t('menu.member.list')">
      <a-row style="margin-bottom: 16px" justify="space-between">
        <a-col :span="18">
          <a-space>
            <a-input
              v-model="filterKeyword"
              :placeholder="$t('member.filter.keyword')"
              style="width: 260px"
              allow-clear
              @press-enter="fetchData"
            />
            <a-select
              v-model="filterStatus"
              :placeholder="$t('member.filter.status')"
              style="width: 120px"
              allow-clear
              @change="fetchData"
            >
              <a-option value="">{{ $t('member.filter.status.all') }}</a-option>
              <a-option value="1">{{ $t('member.status.normal') }}</a-option>
              <a-option value="0">{{ $t('member.status.disabled') }}</a-option>
            </a-select>
            <a-button type="primary" @click="fetchData">
              <template #icon><icon-search /></template>
            </a-button>
          </a-space>
        </a-col>
        <a-col :span="6" style="text-align: right">
          <a-button type="primary" @click="handleAdd">
            <template #icon><icon-plus /></template>
            {{ $t('member.operation.add') }}
          </a-button>
        </a-col>
      </a-row>
      <a-table
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="renderData"
        :pagination="{ pageSize: 20 }"
        :bordered="{ cell: true }"
      >
        <template #status="{ record }">
          <a-tag :color="record.status === 1 ? 'green' : 'red'">
            {{
              record.status === 1
                ? $t('member.status.normal')
                : $t('member.status.disabled')
            }}
          </a-tag>
        </template>
        <template #tags="{ record }">
          <a-space wrap>
            <a-tag v-for="tag in record.tags || []" :key="tag.id" size="small">
              {{ tag.name }}
            </a-tag>
          </a-space>
        </template>
        <template #createTime="{ record }">
          {{
            record.create_time
              ? new Date(record.create_time * 1000).toLocaleDateString()
              : '-'
          }}
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('member.operation.edit') }}
            </a-button>
            <a-button
              type="text"
              size="small"
              @click="handleAdjustBalance(record)"
            >
              {{ $t('member.operation.adjustBalance') }}
            </a-button>
            <a-button
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

    <!-- 新增/编辑弹窗 -->
    <a-modal
      v-model:visible="modalVisible"
      :title="
        isEdit ? $t('member.modal.editTitle') : $t('member.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
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
        <a-form-item
          v-if="!isEdit"
          field="status"
          :label="$t('member.field.status')"
        >
          <a-radio-group v-model="form.status">
            <a-radio :value="1">{{ $t('member.status.normal') }}</a-radio>
            <a-radio :value="0">{{ $t('member.status.disabled') }}</a-radio>
          </a-radio-group>
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- 余额调整弹窗 -->
    <a-modal
      v-model:visible="balanceModalVisible"
      :title="$t('member.balance.modalTitle')"
      :ok-loading="balanceLoading"
      :mask-closable="false"
      @ok="handleBalanceSubmit"
      @cancel="balanceModalVisible = false"
    >
      <a-form
        ref="balanceFormRef"
        :model="balanceForm"
        :rules="balanceRules"
        layout="vertical"
      >
        <a-form-item field="amount" :label="$t('member.field.amount')">
          <a-input-number
            v-model="balanceForm.amount"
            :placeholder="$t('member.field.amount.placeholder')"
            style="width: 100%"
            :precision="2"
            :step="1"
          />
        </a-form-item>
        <a-form-item field="remark" :label="$t('member.field.remark')">
          <a-input
            v-model="balanceForm.remark"
            :placeholder="$t('member.field.remark.placeholder')"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { ref, reactive, computed, onMounted } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getMemberList,
    getMemberTagList,
    addMember,
    editMember,
    updateMemberStatus,
    adjustMemberBalance,
    type MemberRecord,
    type MemberTagRecord,
    type MemberForm,
  } from '@/api/member';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<MemberRecord[]>([]);
  const tagOptions = ref<MemberTagRecord[]>([]);
  const filterKeyword = ref('');
  const filterStatus = ref('');

  const columns = computed<TableColumnData[]>(() => [
    { title: t('member.columns.sn'), dataIndex: 'sn', width: 160 },
    { title: t('member.columns.nickname'), dataIndex: 'nickname' },
    { title: t('member.columns.mobile'), dataIndex: 'mobile', width: 140 },
    { title: t('member.columns.tags'), slotName: 'tags' },
    { title: t('member.columns.status'), slotName: 'status', width: 90 },
    { title: t('member.columns.balance'), dataIndex: 'balance', width: 100 },
    { title: t('member.columns.points'), dataIndex: 'points', width: 90 },
    {
      title: t('member.columns.createTime'),
      slotName: 'createTime',
      width: 120,
    },
    {
      title: t('member.columns.operations'),
      slotName: 'operations',
      width: 240,
    },
  ]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getMemberList({
        keyword: filterKeyword.value || undefined,
        status: filterStatus.value || undefined,
      });
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };

  onMounted(async () => {
    const [, tagsRes] = await Promise.all([fetchData(), getMemberTagList()]);
    tagOptions.value = tagsRes.data;
  });

  const modalVisible = ref(false);
  const isEdit = ref(false);
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
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };
  const handleEdit = (record: MemberRecord) => {
    isEdit.value = true;
    resetForm({ ...record });
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      if (isEdit.value) {
        await editMember(form);
      } else {
        await addMember(form);
      }
      Message.success(t('member.tip.success'));
      modalVisible.value = false;
      await fetchData();
    } finally {
      submitLoading.value = false;
    }
  };

  const handleToggleStatus = async (record: MemberRecord) => {
    const next = record.status === 1 ? 0 : 1;
    await updateMemberStatus(record.id, next);
    record.status = next;
    Message.success(t('member.tip.success'));
  };

  const balanceModalVisible = ref(false);
  const balanceLoading = ref(false);
  const balanceFormRef = ref<FormInstance>();
  const balanceMemberId = ref(0);
  const balanceForm = reactive({ amount: 0, remark: '' });
  const balanceRules = {
    amount: [{ required: true, message: t('member.field.amount.required') }],
  };

  const handleAdjustBalance = (record: MemberRecord) => {
    balanceMemberId.value = record.id;
    balanceForm.amount = 0;
    balanceForm.remark = '';
    balanceModalVisible.value = true;
  };

  const handleBalanceSubmit = async () => {
    const err = await balanceFormRef.value?.validate();
    if (err) return;
    balanceLoading.value = true;
    try {
      await adjustMemberBalance(
        balanceMemberId.value,
        balanceForm.amount,
        balanceForm.remark
      );
      Message.success(t('member.tip.success'));
      balanceModalVisible.value = false;
      await fetchData();
    } finally {
      balanceLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default { name: 'MemberList' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
