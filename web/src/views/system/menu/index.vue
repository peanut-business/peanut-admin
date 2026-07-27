<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.menu']" />
    <a-card class="general-card" :title="$t('menu.system.menu')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-button type="primary" @click="handleAdd()">
              <template #icon><icon-plus /></template>
              {{ $t('systemMenu.operation.create') }}
            </a-button>
            <a-button @click="fetchData">
              <template #icon><icon-refresh /></template>
              {{ $t('systemMenu.operation.refresh') }}
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
        <template #type="{ record }">
          <a-tag :color="typeColor[record.type as MenuType]">
            {{ $t(`systemMenu.type.${record.type}`) }}
          </a-tag>
        </template>
        <template #is_disable="{ record }">
          <a-switch
            :model-value="record.is_disable === 0"
            @change="(v) => handleStatus(record, v as boolean)"
          />
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-if="record.type !== 'A'"
              type="text"
              size="small"
              @click="handleAdd(record)"
            >
              {{ $t('systemMenu.operation.addChild') }}
            </a-button>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('systemMenu.operation.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemMenu.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button type="text" status="danger" size="small">
                {{ $t('systemMenu.operation.delete') }}
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
          ? $t('systemMenu.modal.editTitle')
          : $t('systemMenu.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="type" :label="$t('systemMenu.field.type')">
          <a-radio-group v-model="form.type" type="button">
            <a-radio value="M">{{ $t('systemMenu.type.M') }}</a-radio>
            <a-radio value="C">{{ $t('systemMenu.type.C') }}</a-radio>
            <a-radio value="A">{{ $t('systemMenu.type.A') }}</a-radio>
          </a-radio-group>
        </a-form-item>
        <a-form-item field="pid" :label="$t('systemMenu.field.pid')">
          <a-tree-select
            v-model="form.pid"
            :data="parentTree"
            :field-names="{ key: 'id', title: 'name', children: 'children' }"
            :placeholder="$t('systemMenu.field.pid.placeholder')"
            allow-clear
          />
        </a-form-item>
        <a-form-item field="name" :label="$t('systemMenu.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemMenu.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item
          v-if="form.type !== 'A'"
          field="icon"
          :label="$t('systemMenu.field.icon')"
        >
          <a-input
            v-model="form.icon"
            :placeholder="$t('systemMenu.field.icon.placeholder')"
          />
        </a-form-item>
        <a-form-item
          v-if="form.type !== 'M'"
          field="paths"
          :label="$t('systemMenu.field.paths')"
        >
          <a-input
            v-model="form.paths"
            :placeholder="$t('systemMenu.field.paths.placeholder')"
          />
        </a-form-item>
        <a-form-item
          v-if="form.type === 'C'"
          field="component"
          :label="$t('systemMenu.field.component')"
        >
          <a-input
            v-model="form.component"
            :placeholder="$t('systemMenu.field.component.placeholder')"
          />
        </a-form-item>
        <a-form-item field="perms" :label="$t('systemMenu.field.perms')">
          <a-input
            v-model="form.perms"
            :placeholder="$t('systemMenu.field.perms.placeholder')"
          />
        </a-form-item>
        <a-form-item field="sort" :label="$t('systemMenu.field.sort')">
          <a-input-number v-model="form.sort" :min="0" style="width: 160px" />
        </a-form-item>
        <a-form-item :label="$t('systemMenu.field.flags')">
          <a-space size="large">
            <a-checkbox v-model="showChecked">
              {{ $t('systemMenu.field.isShow') }}
            </a-checkbox>
            <a-checkbox v-model="cacheChecked">
              {{ $t('systemMenu.field.isCache') }}
            </a-checkbox>
          </a-space>
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
  import useLoading from '@/hooks/loading';
  import {
    getMenuList,
    addMenu,
    editMenu,
    deleteMenu,
    updateMenuStatus,
    type MenuRecord,
    type MenuForm,
    type MenuType,
  } from '@/api/system/menu';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<MenuRecord[]>([]);

  const typeColor: Record<MenuType, string> = {
    M: 'arcoblue',
    C: 'green',
    A: 'gray',
  };

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemMenu.columns.name'), dataIndex: 'name' },
    { title: t('systemMenu.columns.type'), slotName: 'type', width: 90 },
    { title: t('systemMenu.columns.icon'), dataIndex: 'icon', width: 140 },
    { title: t('systemMenu.columns.perms'), dataIndex: 'perms' },
    { title: t('systemMenu.columns.sort'), dataIndex: 'sort', width: 80 },
    {
      title: t('systemMenu.columns.status'),
      slotName: 'is_disable',
      width: 90,
    },
    {
      title: t('systemMenu.columns.operations'),
      slotName: 'operations',
      width: 220,
    },
  ]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getMenuList();
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  // ---- 上级菜单选择树：只允许挂在 M/C 下，按钮(A)不能当父级 ----
  const parentTree = computed(() => {
    const strip = (nodes: MenuRecord[]): any[] =>
      nodes
        .filter((n) => n.type !== 'A')
        .map((n) => ({
          id: n.id,
          name: n.name,
          children: n.children ? strip(n.children) : [],
        }));
    return [
      {
        id: 0,
        name: t('systemMenu.field.pid.root'),
        children: strip(renderData.value),
      },
    ];
  });

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): MenuForm => ({
    id: undefined,
    pid: 0,
    type: 'C',
    name: '',
    icon: '',
    sort: 0,
    perms: '',
    paths: '',
    component: '',
    is_cache: 0,
    is_show: 1,
    is_disable: 0,
  });
  const form = reactive<MenuForm>(defaultForm());

  // 布尔勾选 ↔ 后端 0/1 映射
  const showChecked = computed({
    get: () => form.is_show === 1,
    set: (v: boolean) => {
      form.is_show = v ? 1 : 0;
    },
  });
  const cacheChecked = computed({
    get: () => form.is_cache === 1,
    set: (v: boolean) => {
      form.is_cache = v ? 1 : 0;
    },
  });

  const rules = {
    name: [{ required: true, message: t('systemMenu.field.name.required') }],
    type: [{ required: true, message: t('systemMenu.field.type.required') }],
  };

  const resetForm = (patch: Partial<MenuForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = (parent?: MenuRecord) => {
    isEdit.value = false;
    resetForm({ pid: parent ? parent.id : 0 });
    modalVisible.value = true;
  };

  const handleEdit = (record: MenuRecord) => {
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
        await editMenu(form);
      } else {
        await addMenu(form);
      }
      Message.success(t('systemMenu.tip.success'));
      modalVisible.value = false;
      await fetchData();
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: MenuRecord) => {
    await deleteMenu(record.id);
    Message.success(t('systemMenu.tip.success'));
    await fetchData();
  };

  const handleStatus = async (record: MenuRecord, enabled: boolean) => {
    await updateMenuStatus(record.id, enabled ? 0 : 1);
    record.is_disable = enabled ? 0 : 1;
    Message.success(t('systemMenu.tip.success'));
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemMenu',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
