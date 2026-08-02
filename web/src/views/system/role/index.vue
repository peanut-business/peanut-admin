<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.role']" />
    <a-card class="general-card" :title="$t('menu.system.role')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-button
              v-permission="['role/add']"
              type="primary"
              @click="handleAdd"
            >
              <template #icon><icon-plus /></template>
              {{ $t('systemRole.operation.create') }}
            </a-button>
            <a-button @click="fetchData(pagination.current)">
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
        :pagination="pagination"
        :bordered="{ cell: true }"
        @page-change="onPageChange"
        @page-size-change="onPageSizeChange"
      >
        <template #create_time="{ record }">
          {{ formatTime(record.create_time) }}
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['role/edit']"
              type="text"
              size="small"
              @click="handleEdit(record)"
            >
              {{ $t('systemRole.operation.edit') }}
            </a-button>
            <a-button
              v-permission="['role/edit']"
              type="text"
              size="small"
              @click="handleAuth(record)"
            >
              {{ $t('systemRole.operation.permission') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemRole.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button
                v-permission="['role/delete']"
                type="text"
                status="danger"
                size="small"
              >
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
      @before-ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="name" :label="$t('systemRole.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemRole.field.name.placeholder')"
            allow-clear
          />
        </a-form-item>
        <a-form-item field="desc" :label="$t('systemRole.field.desc')">
          <a-textarea
            v-model="form.desc"
            :placeholder="$t('systemRole.field.desc.placeholder')"
            :auto-size="{ minRows: 4, maxRows: 6 }"
            :max-length="200"
            show-word-limit
          />
        </a-form-item>
        <a-form-item field="sort" :label="$t('systemRole.field.sort')">
          <a-input-number
            v-model="form.sort"
            :min="0"
            :max="9999"
            style="width: 160px"
          />
        </a-form-item>
      </a-form>
    </a-modal>

    <a-modal
      v-model:visible="authVisible"
      :title="$t('systemRole.modal.authTitle')"
      :ok-loading="authSubmitLoading"
      :mask-closable="false"
      width="560px"
      @before-ok="handleAuthSubmit"
      @cancel="authVisible = false"
    >
      <a-spin :loading="authLoading" style="width: 100%">
        <div class="auth-toolbar">
          <a-checkbox
            :model-value="expandedKeys.length > 0"
            @change="handleExpandAll"
          >
            {{ $t('systemRole.auth.expand') }}
          </a-checkbox>
          <a-checkbox
            :model-value="isAllChecked"
            @change="handleSelectAll"
          >
            {{ $t('systemRole.auth.selectAll') }}
          </a-checkbox>
          <a-checkbox
            v-model="parentLinked"
            @change="handleLinkageChange"
          >
            {{ $t('systemRole.auth.parentLinked') }}
          </a-checkbox>
        </div>
        <div class="auth-tree">
          <a-tree
            v-model:expanded-keys="expandedKeys"
            :checked-keys="authCheckedKeys"
            :half-checked-keys="authHalfCheckedKeys"
            :data="treeData"
            :field-names="{ key: 'id', title: 'name', children: 'children' }"
            checkable
            check-strictly
            @check="handleAuthCheck"
          />
        </div>
      </a-spin>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { TreeNodeData } from '@arco-design/web-vue/es/tree/interface';
  import useLoading from '@/hooks/loading';
  import { getMenuAll, type MenuRecord } from '@/api/system/menu';
  import {
    addRole,
    deleteRole,
    editRole,
    getRoleDetail,
    getRoleList,
    type RoleAuthForm,
    type RoleBaseForm,
    type RoleRecord,
  } from '@/api/system/role';

  type AuthCheckState = 0 | 1 | 2;
  type CheckboxValue = boolean | Array<string | number | boolean>;
  type AuthCheckEvent = {
    checked?: boolean;
    node?: TreeNodeData & { id?: number };
  };

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<RoleRecord[]>([]);

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
    showPageSize: true,
  });

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemRole.columns.id'), dataIndex: 'id', width: 80 },
    { title: t('systemRole.columns.name'), dataIndex: 'name', width: 150 },
    {
      title: t('systemRole.columns.desc'),
      dataIndex: 'desc',
      ellipsis: true,
      tooltip: true,
    },
    { title: t('systemRole.columns.sort'), dataIndex: 'sort', width: 90 },
    { title: t('systemRole.columns.num'), dataIndex: 'num', width: 120 },
    {
      title: t('systemRole.columns.createTime'),
      slotName: 'create_time',
      width: 180,
    },
    {
      title: t('systemRole.columns.operations'),
      slotName: 'operations',
      width: 220,
      fixed: 'right',
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const { data } = await getRoleList({
        page_no: page,
        page_size: pagination.pageSize,
      });
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };

  const onPageChange = (current: number) => fetchData(current);
  const onPageSizeChange = (pageSize: number) => {
    pagination.pageSize = pageSize;
    fetchData(1);
  };

  const formatTime = (value?: number | string) => {
    if (!value) return '-';
    if (typeof value === 'number') {
      return new Date(value * 1000).toLocaleString();
    }
    return value;
  };

  fetchData();

  // ---- 新增/编辑基本信息 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): RoleBaseForm => ({
    id: undefined,
    name: '',
    desc: '',
    sort: 0,
  });
  const form = reactive<RoleBaseForm>(defaultForm());

  const rules = {
    name: [{ required: true, message: t('systemRole.field.name.required') }],
  };

  const resetForm = (patch: Partial<RoleBaseForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = (record: RoleRecord) => {
    isEdit.value = true;
    resetForm({
      id: record.id,
      name: record.name,
      desc: record.desc,
      sort: record.sort,
    });
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return false;

    submitLoading.value = true;
    try {
      const payload: RoleBaseForm = {
        id: form.id,
        name: form.name,
        desc: form.desc,
        sort: form.sort,
      };
      if (isEdit.value) {
        await editRole(payload);
      } else {
        await addRole(payload);
      }
      Message.success(t('systemRole.tip.success'));
      modalVisible.value = false;
      await fetchData(pagination.current);
      return true;
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: RoleRecord) => {
    await deleteRole(record.id);
    Message.success(t('systemRole.tip.success'));
    await fetchData(pagination.current);
  };

  // ---- 独立分配权限 ----
  const authVisible = ref(false);
  const authLoading = ref(false);
  const authSubmitLoading = ref(false);
  const menuTree = ref<MenuRecord[]>([]);
  const treeData = computed(
    () => menuTree.value as unknown as TreeNodeData[]
  );
  const parentLinked = ref(true);
  const expandedKeys = ref<number[]>([]);
  const authCheckedKeys = ref<number[]>([]);
  const authHalfCheckedKeys = ref<number[]>([]);
  const authForm = reactive<RoleAuthForm>({
    id: 0,
    name: '',
    desc: '',
    sort: 0,
    menu_id: [],
  });

  const allMenuIds = computed(() => {
    const ids: number[] = [];
    const visit = (nodes: MenuRecord[]) => {
      nodes.forEach((node) => {
        ids.push(node.id);
        if (node.children?.length) visit(node.children);
      });
    };
    visit(menuTree.value);
    return ids;
  });

  const menuNodeMap = computed(() => {
    const nodes = new Map<number, MenuRecord>();
    const visit = (items: MenuRecord[]) => {
      items.forEach((item) => {
        nodes.set(item.id, item);
        if (item.children?.length) visit(item.children);
      });
    };
    visit(menuTree.value);
    return nodes;
  });

  const isAllChecked = computed(
    () =>
      allMenuIds.value.length > 0 &&
      authCheckedKeys.value.length === allMenuIds.value.length
  );

  const orderKeys = (keys: Set<number>) =>
    allMenuIds.value.filter((id) => keys.has(id));

  const normalizeLinkedState = (menuIds: number[]) => {
    const selected = new Set(menuIds);
    const checked = new Set<number>();
    const halfChecked = new Set<number>();

    const visit = (node: MenuRecord): AuthCheckState => {
      const childStates = (node.children ?? []).map(visit);
      const selfSelected = selected.has(node.id);
      if (childStates.length === 0) {
        if (selfSelected) checked.add(node.id);
        return selfSelected ? 2 : 0;
      }

      const allChildrenChecked = childStates.every((state) => state === 2);
      const hasCheckedChild = childStates.some((state) => state !== 0);
      if (allChildrenChecked && (selfSelected || hasCheckedChild)) {
        checked.add(node.id);
        return 2;
      }
      if (hasCheckedChild) {
        halfChecked.add(node.id);
        return 1;
      }
      if (selfSelected) {
        checked.add(node.id);
        return 2;
      }
      return 0;
    };

    menuTree.value.forEach(visit);
    authCheckedKeys.value = orderKeys(checked);
    authHalfCheckedKeys.value = orderKeys(halfChecked);
  };

  const loadMenuTree = async () => {
    if (menuTree.value.length > 0) return;
    const { data } = await getMenuAll();
    menuTree.value = data;
  };

  const handleAuth = async (record: RoleRecord) => {
    authVisible.value = true;
    authLoading.value = true;
    try {
      const [{ data }] = await Promise.all([
        getRoleDetail(record.id),
        loadMenuTree(),
      ]);
      const menuIds = data.menu_id ?? data.menu_ids ?? [];
      Object.assign(authForm, {
        id: data.id,
        name: data.name,
        desc: data.desc,
        sort: data.sort,
        menu_id: [...menuIds],
      });
      parentLinked.value = true;
      expandedKeys.value = [];
      normalizeLinkedState(menuIds);
    } catch (error) {
      authVisible.value = false;
      throw error;
    } finally {
      authLoading.value = false;
    }
  };

  const setSubtreeChecked = (
    node: MenuRecord,
    checked: boolean,
    checkedSet: Set<number>,
    halfCheckedSet: Set<number>
  ) => {
    if (checked) checkedSet.add(node.id);
    else checkedSet.delete(node.id);
    halfCheckedSet.delete(node.id);
    node.children?.forEach((child) =>
      setSubtreeChecked(child, checked, checkedSet, halfCheckedSet)
    );
  };

  const updateAncestors = (
    node: MenuRecord,
    checkedSet: Set<number>,
    halfCheckedSet: Set<number>
  ) => {
    let parent = menuNodeMap.value.get(node.pid);
    while (parent) {
      const children = parent.children ?? [];
      const allChildrenChecked =
        children.length > 0 &&
        children.every((child) => checkedSet.has(child.id));
      const hasCheckedChild = children.some(
        (child) =>
          checkedSet.has(child.id) || halfCheckedSet.has(child.id)
      );

      if (allChildrenChecked) {
        checkedSet.add(parent.id);
        halfCheckedSet.delete(parent.id);
      } else if (hasCheckedChild) {
        checkedSet.delete(parent.id);
        halfCheckedSet.add(parent.id);
      } else {
        checkedSet.delete(parent.id);
        halfCheckedSet.delete(parent.id);
      }
      parent = menuNodeMap.value.get(parent.pid);
    }
  };

  const handleAuthCheck = (
    rawCheckedKeys: Array<number | string>,
    event: AuthCheckEvent
  ) => {
    const keys = rawCheckedKeys.map(Number);
    if (!parentLinked.value) {
      authCheckedKeys.value = keys;
      authHalfCheckedKeys.value = [];
      return;
    }

    const nodeId = Number(event.node?.id);
    const node = menuNodeMap.value.get(nodeId);
    if (!node) return;

    const checkedSet = new Set(authCheckedKeys.value);
    const halfCheckedSet = new Set(authHalfCheckedKeys.value);
    const checked = event.checked ?? keys.includes(nodeId);
    setSubtreeChecked(node, checked, checkedSet, halfCheckedSet);
    updateAncestors(node, checkedSet, halfCheckedSet);
    authCheckedKeys.value = orderKeys(checkedSet);
    authHalfCheckedKeys.value = orderKeys(halfCheckedSet);
  };

  const handleLinkageChange = (value: CheckboxValue) => {
    const linked = value === true;
    parentLinked.value = linked;
    if (linked) {
      normalizeLinkedState([
        ...authCheckedKeys.value,
        ...authHalfCheckedKeys.value,
      ]);
      return;
    }
    authCheckedKeys.value = orderKeys(
      new Set([...authCheckedKeys.value, ...authHalfCheckedKeys.value])
    );
    authHalfCheckedKeys.value = [];
  };

  const handleExpandAll = (value: CheckboxValue) => {
    const expanded = value === true;
    expandedKeys.value = expanded ? [...allMenuIds.value] : [];
  };

  const handleSelectAll = (value: CheckboxValue) => {
    const checked = value === true;
    authCheckedKeys.value = checked ? [...allMenuIds.value] : [];
    authHalfCheckedKeys.value = [];
  };

  const handleAuthSubmit = async () => {
    const menuIds = orderKeys(
      new Set([...authCheckedKeys.value, ...authHalfCheckedKeys.value])
    );
    if (menuIds.length === 0) {
      Message.warning(t('systemRole.tip.permissionRequired'));
      return false;
    }

    authSubmitLoading.value = true;
    try {
      await editRole({
        id: authForm.id,
        name: authForm.name,
        desc: authForm.desc,
        sort: authForm.sort,
        menu_id: menuIds,
      });
      Message.success(t('systemRole.tip.success'));
      authVisible.value = false;
      await fetchData(pagination.current);
      return true;
    } finally {
      authSubmitLoading.value = false;
    }
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

  .auth-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 12px;
  }

  .auth-tree {
    height: 480px;
    padding: 8px 12px;
    overflow: auto;
    border: 1px solid var(--color-neutral-3);
    border-radius: 4px;
  }
</style>
