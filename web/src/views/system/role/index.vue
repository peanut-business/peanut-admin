<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.role']" />
    <a-card class="general-card" :title="$t('menu.system.role')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-button type="primary" @click="handleAdd">
              <template #icon><icon-plus /></template>
              {{ $t('systemRole.operation.create') }}
            </a-button>
            <a-button @click="fetchData">
              <template #icon><icon-refresh /></template>
              {{ $t('systemRole.operation.refresh') }}
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
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('systemRole.operation.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemRole.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button type="text" status="danger" size="small">
                {{ $t('systemRole.operation.delete') }}
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
          ? $t('systemRole.modal.editTitle')
          : $t('systemRole.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="name" :label="$t('systemRole.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemRole.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="desc" :label="$t('systemRole.field.desc')">
          <a-textarea
            v-model="form.desc"
            :placeholder="$t('systemRole.field.desc.placeholder')"
            :auto-size="{ minRows: 2, maxRows: 4 }"
          />
        </a-form-item>
        <a-form-item field="sort" :label="$t('systemRole.field.sort')">
          <a-input-number v-model="form.sort" :min="0" style="width: 160px" />
        </a-form-item>
        <a-form-item :label="$t('systemRole.field.menus')">
          <a-tree
            v-model:checked-keys="form.menu_ids"
            :data="treeData"
            :field-names="{ key: 'id', title: 'name', children: 'children' }"
            checkable
            check-strictly
            :default-expand-all="true"
          />
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
  import type { TreeNodeData } from '@arco-design/web-vue/es/tree/interface';
  import useLoading from '@/hooks/loading';
  import { getMenuAll, type MenuRecord } from '@/api/system/menu';
  import {
    getRoleList,
    getRoleDetail,
    addRole,
    editRole,
    deleteRole,
    type RoleRecord,
    type RoleForm,
  } from '@/api/system/role';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<RoleRecord[]>([]);
  const menuTree = ref<MenuRecord[]>([]);
  // 菜单节点用 id/name/children，由 <a-tree field-names> 重映射；MenuRecord.icon
  // 是 string 而 TreeNodeData.icon 是 () => VNode，两者结构不兼容，故此处窄化转换。
  const treeData = computed(() => menuTree.value as unknown as TreeNodeData[]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemRole.columns.name'), dataIndex: 'name' },
    { title: t('systemRole.columns.desc'), dataIndex: 'desc' },
    { title: t('systemRole.columns.sort'), dataIndex: 'sort', width: 80 },
    {
      title: t('systemRole.columns.operations'),
      slotName: 'operations',
      width: 160,
    },
  ]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getRoleList();
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  // 权限树数据只取一次（全量菜单树，含按钮 A）
  const fetchMenuTree = async () => {
    const { data } = await getMenuAll();
    menuTree.value = data;
  };
  fetchMenuTree();

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): RoleForm => ({
    id: undefined,
    name: '',
    desc: '',
    sort: 0,
    menu_ids: [],
  });
  const form = reactive<RoleForm>(defaultForm());

  const rules = {
    name: [{ required: true, message: t('systemRole.field.name.required') }],
  };

  const resetForm = (patch: Partial<RoleForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = async (record: RoleRecord) => {
    isEdit.value = true;
    // 详情接口回填 menu_ids（check-strictly 下按原样勾选，无级联）
    const { data } = await getRoleDetail(record.id);
    resetForm({
      id: data.id,
      name: data.name,
      desc: data.desc,
      sort: data.sort,
      menu_ids: data.menu_ids ?? [],
    });
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      if (isEdit.value) {
        await editRole(form);
      } else {
        await addRole(form);
      }
      Message.success(t('systemRole.tip.success'));
      modalVisible.value = false;
      await fetchData();
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: RoleRecord) => {
    await deleteRole(record.id);
    Message.success(t('systemRole.tip.success'));
    await fetchData();
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemRole',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
