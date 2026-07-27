<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.admin']" />
    <a-card class="general-card" :title="$t('menu.system.admin')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-button type="primary" @click="handleAdd">
              <template #icon><icon-plus /></template>
              {{ $t('systemAdmin.operation.create') }}
            </a-button>
            <a-button @click="fetchData">
              <template #icon><icon-refresh /></template>
              {{ $t('systemAdmin.operation.refresh') }}
            </a-button>
          </a-space>
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
        <template #roles="{ record }">
          <a-space wrap>
            <a-tag
              v-for="r in record.roles"
              :key="r.id"
              color="arcoblue"
              size="small"
            >
              {{ r.name }}
            </a-tag>
            <span v-if="!record.roles || record.roles.length === 0">-</span>
          </a-space>
        </template>
        <template #root="{ record }">
          <a-tag :color="record.root === 1 ? 'gold' : 'gray'" size="small">
            {{
              record.root === 1
                ? $t('systemAdmin.root.yes')
                : $t('systemAdmin.root.no')
            }}
          </a-tag>
        </template>
        <template #status="{ record }">
          <a-switch
            :model-value="record.disable === 0"
            :disabled="isProtected(record)"
            @change="(v) => handleStatus(record, v as boolean)"
          />
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('systemAdmin.operation.edit') }}
            </a-button>
            <a-popconfirm
              v-if="!isProtected(record)"
              :content="$t('systemAdmin.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button type="text" status="danger" size="small">
                {{ $t('systemAdmin.operation.delete') }}
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="modalVisible"
      :title="
        isEdit
          ? $t('systemAdmin.modal.editTitle')
          : $t('systemAdmin.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="username" :label="$t('systemAdmin.field.username')">
          <a-input
            v-model="form.username"
            :disabled="isEdit"
            :placeholder="$t('systemAdmin.field.username.placeholder')"
          />
        </a-form-item>
        <a-form-item field="nickname" :label="$t('systemAdmin.field.nickname')">
          <a-input
            v-model="form.nickname"
            :placeholder="$t('systemAdmin.field.nickname.placeholder')"
          />
        </a-form-item>
        <a-form-item field="password" :label="$t('systemAdmin.field.password')">
          <a-input-password
            v-model="form.password"
            :placeholder="
              isEdit
                ? $t('systemAdmin.field.password.editPlaceholder')
                : $t('systemAdmin.field.password.addPlaceholder')
            "
            allow-clear
          />
        </a-form-item>
        <a-form-item field="role_ids" :label="$t('systemAdmin.field.roles')">
          <a-select
            v-model="form.role_ids"
            :options="roleOptions"
            multiple
            allow-clear
            :placeholder="$t('systemAdmin.field.roles.placeholder')"
          />
        </a-form-item>
        <a-form-item :label="$t('systemAdmin.field.status')">
          <a-switch v-model="enabledChecked" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref, reactive } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import { useUserStore } from '@/store';
  import useLoading from '@/hooks/loading';
  import { getRoleAll } from '@/api/system/role';
  import {
    getAdminList,
    getAdminDetail,
    addAdmin,
    editAdmin,
    deleteAdmin,
    updateAdminStatus,
    type AdminRecord,
    type AdminForm,
  } from '@/api/system/admin';

  const { t } = useI18n();
  const userStore = useUserStore();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<AdminRecord[]>([]);
  const roleOptions = ref<SelectOptionData[]>([]);

  // 受保护行：超级管理员(root) 或当前登录账号，禁止禁用/删除（后端亦有兜底守卫）
  const isProtected = (record: AdminRecord) =>
    record.root === 1 || record.id === userStore.id;

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemAdmin.columns.username'), dataIndex: 'username' },
    { title: t('systemAdmin.columns.nickname'), dataIndex: 'nickname' },
    { title: t('systemAdmin.columns.roles'), slotName: 'roles' },
    { title: t('systemAdmin.columns.root'), slotName: 'root', width: 80 },
    { title: t('systemAdmin.columns.status'), slotName: 'status', width: 90 },
    {
      title: t('systemAdmin.columns.createTime'),
      dataIndex: 'create_time',
      width: 180,
    },
    {
      title: t('systemAdmin.columns.operations'),
      slotName: 'operations',
      width: 160,
    },
  ]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getAdminList();
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  // 角色下拉项只取一次
  const fetchRoles = async () => {
    const { data } = await getRoleAll();
    roleOptions.value = data.map((r) => ({ value: r.id, label: r.name }));
  };
  fetchRoles();

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): AdminForm => ({
    id: undefined,
    username: '',
    nickname: '',
    password: '',
    role_ids: [],
    disable: 0,
  });
  const form = reactive<AdminForm>(defaultForm());

  // 启用开关 ↔ 后端 disable 0/1
  const enabledChecked = computed({
    get: () => form.disable === 0,
    set: (v: boolean) => {
      form.disable = v ? 0 : 1;
    },
  });

  // 密码规则：新增必填，编辑留空则不改（故编辑时无 required）
  const rules = computed(() => ({
    username: [
      { required: true, message: t('systemAdmin.field.username.required') },
    ],
    password: isEdit.value
      ? []
      : [{ required: true, message: t('systemAdmin.field.password.required') }],
  }));

  const resetForm = (patch: Partial<AdminForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = async (record: AdminRecord) => {
    isEdit.value = true;
    const { data } = await getAdminDetail(record.id);
    resetForm({
      id: data.id,
      username: data.username,
      nickname: data.nickname,
      password: '',
      role_ids: data.role_ids ?? [],
      disable: data.disable,
    });
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      if (isEdit.value) {
        await editAdmin(form);
      } else {
        await addAdmin(form);
      }
      Message.success(t('systemAdmin.tip.success'));
      modalVisible.value = false;
      await fetchData();
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: AdminRecord) => {
    await deleteAdmin(record.id);
    Message.success(t('systemAdmin.tip.success'));
    await fetchData();
  };

  const handleStatus = async (record: AdminRecord, enabled: boolean) => {
    const disable = enabled ? 0 : 1;
    await updateAdminStatus(record.id, disable);
    record.disable = disable;
    Message.success(t('systemAdmin.tip.success'));
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemAdmin',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
