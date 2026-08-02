<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.dept']" />
    <a-card class="general-card" :title="$t('menu.system.dept')">
      <a-form :model="queryParams" layout="inline" style="margin-bottom: 16px">
        <a-form-item field="name" :label="$t('systemDept.search.name')">
          <a-input
            v-model="queryParams.name"
            :placeholder="$t('systemDept.search.name.placeholder')"
            allow-clear
            @press-enter="fetchData"
          />
        </a-form-item>
        <a-form-item field="status" :label="$t('systemDept.search.status')">
          <a-select
            v-model="queryParams.status"
            :placeholder="$t('systemDept.search.status.all')"
            allow-clear
            style="width: 180px"
          >
            <a-option :value="1">{{ $t('systemDept.status.normal') }}</a-option>
            <a-option :value="0">{{ $t('systemDept.status.disabled') }}</a-option>
          </a-select>
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" @click="fetchData">
              {{ $t('systemDept.operation.search') }}
            </a-button>
            <a-button @click="resetQuery">
              {{ $t('systemDept.operation.reset') }}
            </a-button>
          </a-space>
        </a-form-item>
      </a-form>
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-button
              v-permission="['dept/add']"
              type="primary"
              @click="handleAdd()"
            >
              <template #icon><icon-plus /></template>
              {{ $t('systemDept.operation.create') }}
            </a-button>
            <a-button @click="toggleExpand">
              {{ $t('systemDept.operation.expand') }}
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
        v-model:expanded-keys="expandedKeys"
      >
        <template #status="{ record }">
          <a-tag :color="record.status === 1 ? 'green' : 'red'">
            {{ record.status_desc }}
          </a-tag>
        </template>
        <template #update_time="{ record }">
          {{ formatTime(record.update_time) }}
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['dept/add']"
              type="text"
              size="small"
              @click="handleAdd(record)"
            >
              {{ $t('systemDept.operation.addChild') }}
            </a-button>
            <a-button
              v-permission="['dept/edit']"
              type="text"
              size="small"
              @click="handleEdit(record)"
            >
              {{ $t('systemDept.operation.edit') }}
            </a-button>
            <a-popconfirm
              v-if="record.pid !== 0"
              :content="$t('systemDept.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button
                v-permission="['dept/delete']"
                type="text"
                status="danger"
                size="small"
              >
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
        <a-form-item
          v-if="form.pid !== 0"
          field="pid"
          :label="$t('systemDept.field.pid')"
        >
          <a-tree-select
            v-model="form.pid"
            :data="parentTree"
            :field-names="{ key: 'id', title: 'name', children: 'children' }"
            :placeholder="$t('systemDept.field.pid.placeholder')"
          />
        </a-form-item>
        <a-form-item field="name" :label="$t('systemDept.field.name')">
          <a-input
            v-model="form.name"
            :max-length="30"
            :placeholder="$t('systemDept.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="leader" :label="$t('systemDept.field.leader')">
          <a-input
            v-model="form.leader"
            :max-length="30"
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
          <a-input-number
            v-model="form.sort"
            :min="0"
            :max="9999"
            style="width: 160px"
          />
        </a-form-item>
        <a-form-item field="status" :label="$t('systemDept.field.status')">
          <a-switch v-model="form.status" :checked-value="1" :unchecked-value="0" />
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
    getDeptAll,
    getDeptDetail,
    addDept,
    editDept,
    deleteDept,
    type DeptRecord,
    type DeptForm,
  } from '@/api/system/dept';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<DeptRecord[]>([]);
  const deptOptions = ref<DeptRecord[]>([]);
  const expandedKeys = ref<number[]>([]);
  const isExpanded = ref(true);
  const queryParams = reactive<{ name: string; status: number | '' }>({
    name: '',
    status: '',
  });

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemDept.columns.name'), dataIndex: 'name' },
    {
      title: t('systemDept.columns.status'),
      slotName: 'status',
      width: 110,
    },
    { title: t('systemDept.columns.sort'), dataIndex: 'sort', width: 100 },
    {
      title: t('systemDept.columns.updateTime'),
      slotName: 'update_time',
      width: 180,
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
      const { data } = await getDeptList({
        name: queryParams.name || undefined,
        status: queryParams.status === '' ? undefined : queryParams.status,
      });
      renderData.value = data;
      if (isExpanded.value) {
        expandedKeys.value = collectIds(data);
      }
    } finally {
      setLoading(false);
    }
  };

  const resetQuery = () => {
    queryParams.name = '';
    queryParams.status = '';
    fetchData();
  };

  const collectIds = (nodes: DeptRecord[]): number[] =>
    nodes.flatMap((node) => [
      node.id,
      ...collectIds(node.children || []),
    ]);

  const toggleExpand = () => {
    isExpanded.value = !isExpanded.value;
    expandedKeys.value = isExpanded.value ? collectIds(renderData.value) : [];
  };

  const formatTime = (value?: number | string): string => {
    if (!value) return '-';
    if (typeof value === 'string') return value;
    return new Date(value * 1000).toLocaleString('zh-CN', { hour12: false });
  };

  fetchData();

  // ---- 上级部门选择树：仅正常部门 ----
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
    return strip(deptOptions.value) as unknown as TreeNodeData[];
  });

  const loadDeptOptions = async () => {
    const { data } = await getDeptAll();
    deptOptions.value = data;
  };

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): DeptForm => ({
    id: undefined,
    pid: undefined,
    name: '',
    leader: '',
    mobile: '',
    sort: 0,
    status: 1,
    is_disable: 0,
  });
  const form = reactive<DeptForm>(defaultForm());

  const rules = {
    name: [{ required: true, message: t('systemDept.field.name.required') }],
    pid: [{ required: true, message: t('systemDept.field.pid.required') }],
    mobile: [
      {
        validator: (value: string, callback: (error?: string) => void) => {
          if (!value || /^1[3-9]\d{9}$/.test(value)) callback();
          else callback(t('systemDept.field.mobile.invalid'));
        },
      },
    ],
  };

  const resetForm = (patch: Partial<DeptForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = async (parent?: DeptRecord) => {
    isEdit.value = false;
    await loadDeptOptions();
    resetForm({ pid: parent?.id });
    modalVisible.value = true;
  };

  const handleEdit = async (record: DeptRecord) => {
    isEdit.value = true;
    const [{ data }] = await Promise.all([
      getDeptDetail(record.id),
      loadDeptOptions(),
    ]);
    resetForm({ ...data, children: undefined });
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
