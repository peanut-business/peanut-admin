<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.dept']" />
    <a-card class="general-card" :title="$t('menu.system.dept')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-button type="primary" @click="handleAdd()">
              <template #icon><icon-plus /></template>
              {{ $t('systemDept.operation.create') }}
            </a-button>
            <a-button @click="fetchData">
              <template #icon><icon-refresh /></template>
              {{ $t('systemDept.operation.refresh') }}
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
        :default-expand-all-rows="true"
      >
        <template #is_disable="{ record }">
          <a-switch
            :model-value="record.is_disable === 0"
            @change="(v) => handleStatus(record, v as boolean)"
          />
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleAdd(record)">
              {{ $t('systemDept.operation.addChild') }}
            </a-button>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('systemDept.operation.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemDept.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button type="text" status="danger" size="small">
                {{ $t('systemDept.operation.delete') }}
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
          ? $t('systemDept.modal.editTitle')
          : $t('systemDept.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="pid" :label="$t('systemDept.field.pid')">
          <a-tree-select
            v-model="form.pid"
            :data="parentTree"
            :field-names="{ key: 'id', title: 'name', children: 'children' }"
            :placeholder="$t('systemDept.field.pid.placeholder')"
            allow-clear
          />
        </a-form-item>
        <a-form-item field="name" :label="$t('systemDept.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemDept.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="leader" :label="$t('systemDept.field.leader')">
          <a-input
            v-model="form.leader"
            :placeholder="$t('systemDept.field.leader.placeholder')"
          />
        </a-form-item>
        <a-form-item field="mobile" :label="$t('systemDept.field.mobile')">
          <a-input
            v-model="form.mobile"
            :placeholder="$t('systemDept.field.mobile.placeholder')"
          />
        </a-form-item>
        <a-form-item field="sort" :label="$t('systemDept.field.sort')">
          <a-input-number v-model="form.sort" :min="0" style="width: 160px" />
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
  import {
    getDeptList,
    addDept,
    editDept,
    deleteDept,
    updateDeptStatus,
    type DeptRecord,
    type DeptForm,
  } from '@/api/system/dept';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<DeptRecord[]>([]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemDept.columns.name'), dataIndex: 'name' },
    { title: t('systemDept.columns.leader'), dataIndex: 'leader', width: 140 },
    { title: t('systemDept.columns.mobile'), dataIndex: 'mobile', width: 160 },
    { title: t('systemDept.columns.sort'), dataIndex: 'sort', width: 80 },
    {
      title: t('systemDept.columns.status'),
      slotName: 'is_disable',
      width: 90,
    },
    {
      title: t('systemDept.columns.operations'),
      slotName: 'operations',
      width: 220,
    },
  ]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getDeptList();
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  // ---- 上级部门选择树：根节点 + 全部部门 ----
  // field-names 把 id/name 映射到 key/title，运行期结构安全，故用窄化 cast。
  interface DeptTreeNode {
    id: number;
    name: string;
    children: DeptTreeNode[];
  }
  const parentTree = computed(() => {
    const strip = (nodes: DeptRecord[]): DeptTreeNode[] =>
      nodes.map((n) => ({
        id: n.id,
        name: n.name,
        children: n.children ? strip(n.children) : [],
      }));
    const tree: DeptTreeNode[] = [
      {
        id: 0,
        name: t('systemDept.field.pid.root'),
        children: strip(renderData.value),
      },
    ];
    return tree as unknown as TreeNodeData[];
  });

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): DeptForm => ({
    id: undefined,
    pid: 0,
    name: '',
    leader: '',
    mobile: '',
    sort: 0,
    is_disable: 0,
  });
  const form = reactive<DeptForm>(defaultForm());

  const rules = {
    name: [{ required: true, message: t('systemDept.field.name.required') }],
  };

  const resetForm = (patch: Partial<DeptForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = (parent?: DeptRecord) => {
    isEdit.value = false;
    resetForm({ pid: parent ? parent.id : 0 });
    modalVisible.value = true;
  };

  const handleEdit = (record: DeptRecord) => {
    isEdit.value = true;
    resetForm({ ...record, children: undefined });
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return;
    submitLoading.value = true;
    try {
      if (isEdit.value) {
        await editDept(form);
      } else {
        await addDept(form);
      }
      Message.success(t('systemDept.tip.success'));
      modalVisible.value = false;
      await fetchData();
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: DeptRecord) => {
    await deleteDept(record.id);
    Message.success(t('systemDept.tip.success'));
    await fetchData();
  };

  const handleStatus = async (record: DeptRecord, enabled: boolean) => {
    await updateDeptStatus(record.id, enabled ? 0 : 1);
    record.is_disable = enabled ? 0 : 1;
    Message.success(t('systemDept.tip.success'));
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemDept',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
