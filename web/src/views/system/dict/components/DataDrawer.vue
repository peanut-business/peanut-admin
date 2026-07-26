<template>
  <a-drawer
    :visible="visible"
    :width="720"
    :title="`${$t('systemDict.data.title')} - ${typeName}`"
    :footer="false"
    unmount-on-close
    @cancel="$emit('update:visible', false)"
  >
    <a-row style="margin-bottom: 16px" justify="space-between">
      <a-col :span="12">
        <a-button type="primary" @click="handleAdd">
          <template #icon><icon-plus /></template>
          {{ $t('systemDict.data.create') }}
        </a-button>
      </a-col>
      <a-col :span="10">
        <a-input-search
          v-model="keyword"
          allow-clear
          :placeholder="$t('systemDict.data.search.placeholder')"
          @search="() => fetchData(1)"
          @press-enter="() => fetchData(1)"
        />
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
          <a-button type="text" size="small" @click="handleEdit(record)">
            {{ $t('systemDict.operation.edit') }}
          </a-button>
          <a-popconfirm
            :content="$t('systemDict.data.delete.confirm')"
            @ok="handleDelete(record)"
          >
            <a-button type="text" status="danger" size="small">
              {{ $t('systemDict.operation.delete') }}
            </a-button>
          </a-popconfirm>
        </a-space>
      </template>
    </a-table>

    <a-modal
      v-model:visible="modalVisible"
      :title="
        isEdit
          ? $t('systemDict.data.modal.editTitle')
          : $t('systemDict.data.modal.addTitle')
      "
      :ok-loading="submitLoading"
      :mask-closable="false"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-form-item field="name" :label="$t('systemDict.data.field.name')">
          <a-input
            v-model="form.name"
            :placeholder="$t('systemDict.data.field.name.placeholder')"
          />
        </a-form-item>
        <a-form-item field="value" :label="$t('systemDict.data.field.value')">
          <a-input
            v-model="form.value"
            :placeholder="$t('systemDict.data.field.value.placeholder')"
          />
        </a-form-item>
        <a-form-item field="sort" :label="$t('systemDict.data.field.sort')">
          <a-input-number v-model="form.sort" :min="0" style="width: 160px" />
        </a-form-item>
        <a-form-item field="remark" :label="$t('systemDict.data.field.remark')">
          <a-textarea
            v-model="form.remark"
            :placeholder="$t('systemDict.data.field.remark.placeholder')"
            :max-length="255"
            show-word-limit
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </a-drawer>
</template>

<script lang="ts" setup>
  import { computed, ref, reactive, watch } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getDictDataList,
    addDictData,
    editDictData,
    deleteDictData,
    updateDictDataStatus,
    type DictDataRecord,
    type DictDataForm,
    type DictDataListParams,
  } from '@/api/system/dict';

  const props = defineProps<{
    visible: boolean;
    typeId: number;
    typeName: string;
  }>();
  defineEmits<{ (e: 'update:visible', v: boolean): void }>();

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(false);
  const renderData = ref<DictDataRecord[]>([]);
  const keyword = ref('');

  const pagination = reactive({
    current: 1,
    pageSize: 10,
    total: 0,
    showTotal: true,
  });

  const columns = computed<TableColumnData[]>(() => [
    { title: t('systemDict.data.columns.name'), dataIndex: 'name' },
    { title: t('systemDict.data.columns.value'), dataIndex: 'value' },
    { title: t('systemDict.data.columns.sort'), dataIndex: 'sort', width: 80 },
    {
      title: t('systemDict.data.columns.status'),
      slotName: 'is_disable',
      width: 80,
    },
    {
      title: t('systemDict.data.columns.operations'),
      slotName: 'operations',
      width: 140,
    },
  ]);

  const fetchData = async (page = 1) => {
    if (!props.typeId) return;
    setLoading(true);
    try {
      const params: DictDataListParams = {
        type_id: props.typeId,
        name: keyword.value,
        page_no: page,
        page_size: pagination.pageSize,
      };
      const { data } = await getDictDataList(params);
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };

  const onPageChange = (current: number) => fetchData(current);

  // 抽屉打开或切换类型时刷新
  watch(
    () => [props.visible, props.typeId],
    ([vis]) => {
      if (vis) {
        keyword.value = '';
        pagination.current = 1;
        fetchData(1);
      }
    }
  );

  // ---- 弹窗 & 表单 ----
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();

  const defaultForm = (): DictDataForm => ({
    id: undefined,
    name: '',
    value: '',
    sort: 0,
    is_disable: 0,
    remark: '',
  });
  const form = reactive<DictDataForm>(defaultForm());

  const rules = {
    name: [
      { required: true, message: t('systemDict.data.field.name.required') },
    ],
    value: [
      { required: true, message: t('systemDict.data.field.value.required') },
    ],
  };

  const resetForm = (patch: Partial<DictDataForm> = {}) => {
    Object.assign(form, defaultForm(), patch);
  };

  const handleAdd = () => {
    isEdit.value = false;
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = (record: DictDataRecord) => {
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
        await editDictData(form);
      } else {
        await addDictData({ ...form, type_id: props.typeId });
      }
      Message.success(t('systemDict.tip.success'));
      modalVisible.value = false;
      await fetchData(pagination.current);
    } finally {
      submitLoading.value = false;
    }
  };

  const handleDelete = async (record: DictDataRecord) => {
    await deleteDictData(record.id);
    Message.success(t('systemDict.tip.success'));
    await fetchData(pagination.current);
  };

  const handleStatus = async (record: DictDataRecord, enabled: boolean) => {
    await updateDictDataStatus(record.id, enabled ? 0 : 1);
    record.is_disable = enabled ? 0 : 1;
    Message.success(t('systemDict.tip.success'));
  };
</script>
