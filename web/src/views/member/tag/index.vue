<template>
  <div class="container">
    <Breadcrumb :items="['menu.member', 'menu.member.tag']" />
    <a-card class="general-card" :title="$t('menu.member.tag')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-button type="primary" @click="handleAdd">
            <template #icon><icon-plus /></template>
            {{ $t('memberTag.operation.add') }}
          </a-button>
        </a-col>
      </a-row>
      <a-table
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="renderData"
        :pagination="false"
        :bordered="{ cell: true }"
      >
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('memberTag.operation.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('memberTag.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button type="text" status="danger" size="small">
                {{ $t('memberTag.operation.delete') }}
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="modalVisible"
      :title="isEdit ? $t('memberTag.modal.editTitle') : $t('memberTag.modal.addTitle')"
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="name" :label="$t('memberTag.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('memberTag.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="remark" :label="$t('memberTag.field.remark')">
          <a-input
            v-model="form.remark"
            :placeholder="$t('memberTag.field.remark.placeholder')"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { ref, reactive, computed } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getMemberTagList,
    addMemberTag,
    editMemberTag,
    deleteMemberTag,
    type MemberTagRecord,
    type MemberTagForm,
  } from '@/api/member';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<MemberTagRecord[]>([]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('memberTag.columns.name'), dataIndex: 'name' },
    { title: t('memberTag.columns.remark'), dataIndex: 'remark' },
    { title: t('memberTag.columns.operations'), slotName: 'operations', width: 160 },
  ]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getMemberTagList();
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();
  const defaultForm = (): MemberTagForm => ({ id: undefined, name: '', remark: '' });
  const form = reactive<MemberTagForm>(defaultForm());
  const rules = { name: [{ required: true, message: t('memberTag.field.name.required') }] };

  const resetForm = (patch: Partial<MemberTagForm> = {}) => Object.assign(form, defaultForm(), patch);
  const handleAdd = () => { isEdit.value = false; resetForm(); modalVisible.value = true; };
  const handleEdit = (record: MemberTagRecord) => { isEdit.value = true; resetForm({ ...record }); modalVisible.value = true; };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      isEdit.value ? await editMemberTag(form) : await addMemberTag(form);
      Message.success(t('memberTag.tip.success'));
      modalVisible.value = false;
      await fetchData();
    } finally { submitLoading.value = false; }
  };

  const handleDelete = async (record: MemberTagRecord) => {
    await deleteMemberTag(record.id);
    Message.success(t('memberTag.tip.success'));
    await fetchData();
  };
</script>

<script lang="ts">
  export default { name: 'MemberTag' };
</script>

<style scoped lang="less">
  .container { padding: 0 20px 20px 20px; }
</style>