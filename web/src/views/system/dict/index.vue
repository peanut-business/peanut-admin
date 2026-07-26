<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.dict']" />
    <a-card class="general-card" :title="$t('menu.system.dict')">
      <a-row>
        <a-col :flex="1">
          <a-form
            :model="formModel"
            :label-col-props="{ span: 6 }"
            :wrapper-col-props="{ span: 18 }"
            label-align="left"
          >
            <a-row :gutter="16">
              <a-col :span="8">
                <a-form-item field="name" :label="$t('systemDict.form.name')">
                  <a-input
                    v-model="formModel.name"
                    allow-clear
                    :placeholder="$t('systemDict.form.name.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="type" :label="$t('systemDict.form.type')">
                  <a-input
                    v-model="formModel.type"
                    allow-clear
                    :placeholder="$t('systemDict.form.type.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item
                  field="is_disable"
                  :label="$t('systemDict.form.status')"
                >
                  <a-select
                    v-model="formModel.is_disable"
                    allow-clear
                    :options="statusOptions"
                    :placeholder="$t('systemDict.form.status.placeholder')"
                  />
                </a-form-item>
              </a-col>
            </a-row>
          </a-form>
        </a-col>
        <a-divider style="height: 84px" direction="vertical" />
        <a-col :flex="'86px'" style="text-align: right">
          <a-space direction="vertical" :size="18">
            <a-button type="primary" @click="search">
              <template #icon><icon-search /></template>
              {{ $t('systemDict.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('systemDict.form.reset') }}
            </a-button>
          </a-space>
        </a-col>
      </a-row>
      <a-divider style="margin-top: 0" />
      <a-row style="margin-bottom: 16px">
        <a-col :span="12">
          <a-button type="primary" @click="handleAdd">
            <template #icon><icon-plus /></template>
            {{ $t('systemDict.operation.create') }}
          </a-button>
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
      >
        <template #is_disable="{ record }">
          <a-switch
            :model-value="record.is_disable === 0"
            @change="(v) => handleStatus(record, v as boolean)"
          />
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="openData(record)">
              {{ $t('systemDict.operation.manageData') }}
            </a-button>
            <a-button type="text" size="small" @click="handleEdit(record)">
              {{ $t('systemDict.operation.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('systemDict.delete.confirm')"
              @ok="handleDelete(record)"
            >
              <a-button type="text" status="danger" size="small">
                {{ $t('systemDict.operation.delete') }}
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
          ? $t('systemDict.modal.editTitle')
          : $t('systemDict.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="name" :label="$t('systemDict.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemDict.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="type" :label="$t('systemDict.field.type')">
          <a-input
            v-model="form.type"
            :placeholder="$t('systemDict.field.type.placeholder')"
          />
        </a-form-item>
        <a-form-item field="remark" :label="$t('systemDict.field.remark')">
          <a-textarea
            v-model="form.remark"
            :placeholder="$t('systemDict.field.remark.placeholder')"
            :max-length="255"
            show-word-limit
          />
        </a-form-item>
      </a-form>
    </a-modal>

    <DataDrawer
      v-model:visible="drawerVisible"
      :type-id="currentType.id"
      :type-name="currentType.name"
    />
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref, reactive } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getDictTypeList,
    addDictType,
    editDictType,
    deleteDictType,
    updateDictTypeStatus,
    type DictTypeRecord,
    type DictTypeForm,
    type DictTypeListParams,
  } from '@/api/system/dict';
  import DataDrawer from './components/DataDrawer.vue';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<DictTypeRecord[]>([]);

  const generateFormModel = () => ({
    name: '',
    type: '',
    is_disable: '' as number | '',
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });

  const statusOptions = computed<SelectOptionData[]>(() => [
    { label: t('systemDict.status.enabled'), value: 0 },
    { label: t('systemDict.status.disabled'), value: 1 },
  ]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemDict.columns.id'), dataIndex: 'id', width: 80 },
    { title: t('systemDict.columns.name'), dataIndex: 'name' },
    { title: t('systemDict.columns.type'), dataIndex: 'type' },
    { title: t('systemDict.columns.remark'), dataIndex: 'remark' },
    {
      title: t('systemDict.columns.status'),
      slotName: 'is_disable',
      width: 90,
    },
    {
      title: t('systemDict.columns.operations'),
      slotName: 'operations',
      width: 220,
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: DictTypeListParams = {
        ...formModel.value,
        page_no: page,
        page_size: pagination.pageSize,
      };
      const { data } = await getDictTypeList(params);
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const search = () => fetchData(1);
  const onPageChange = (current: number) => fetchData(current);
  const reset = () => {
    formModel.value = generateFormModel();
    fetchData(1);
  };

  // ---- 数据抽屉 ----
  const drawerVisible = ref(false);
  const currentType = reactive({ id: 0, name: '' });
  const openData = (record: DictTypeRecord) => {
    currentType.id = record.id;
    currentType.name = record.name;
    drawerVisible.value = true;
  };

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): DictTypeForm => ({
    id: undefined,
    name: '',
    type: '',
    is_disable: 0,
    remark: '',
  });
  const form = reactive<DictTypeForm>(defaultForm());

  const rules = {
    name: [{ required: true, message: t('systemDict.field.name.required') }],
    type: [{ required: true, message: t('systemDict.field.type.required') }],
  };

  const resetForm = (patch: Partial<DictTypeForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = (record: DictTypeRecord) => {
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
        await editDictType(form);
      } else {
        await addDictType(form);
      }
      Message.success(t('systemDict.tip.success'));
      modalVisible.value = false;
      await fetchData(pagination.current);
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: DictTypeRecord) => {
    await deleteDictType(record.id);
    Message.success(t('systemDict.tip.success'));
    await fetchData(pagination.current);
  };

  const handleStatus = async (record: DictTypeRecord, enabled: boolean) => {
    await updateDictTypeStatus(record.id, enabled ? 0 : 1);
    record.is_disable = enabled ? 0 : 1;
    Message.success(t('systemDict.tip.success'));
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemDict',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
