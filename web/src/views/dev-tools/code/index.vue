<template>
  <div class="container">
    <Breadcrumb :items="['开发工具', '代码生成器']" />
    <a-card class="general-card" title="代码生成器">
      <a-row align="center" justify="space-between" style="margin-bottom: 16px">
        <a-col :span="12">
          <a-space>
            <a-input-search
              v-model="keyword"
              allow-clear
              placeholder="按表名、说明或实体筛选"
              style="width: 300px"
              @search="() => fetchData(1)"
              @press-enter="() => fetchData(1)"
            />
            <a-button @click="fetchData(1)">查询</a-button>
          </a-space>
        </a-col>
        <a-col>
          <a-space>
            <a-button
              v-permission="['generator/import']"
              type="primary"
              @click="openSourceTables"
            >
              <template #icon><icon-plus /></template>
              导入数据表
            </a-button>
            <a-button
              v-permission="['generator/generate']"
              :disabled="selectedKeys.length === 0"
              :loading="generateLoading"
              @click="generateSelected"
            >
              <template #icon><icon-code /></template>
              生成并下载
            </a-button>
          </a-space>
        </a-col>
      </a-row>

      <a-table
        v-model:selected-keys="selectedKeys"
        row-key="id"
        :loading="loading"
        :columns="columns"
        :data="renderData"
        :pagination="pagination"
        :row-selection="{ type: 'checkbox', showCheckedAll: true }"
        :bordered="{ cell: true }"
        @page-change="onPageChange"
      >
        <template #template_type="{ record }">
          <a-tag>{{ record.template_type === 'tree' ? '树形' : 'CRUD' }}</a-tag>
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['generator/detail']"
              type="text"
              size="small"
              @click="openEdit(record)"
            >
              配置
            </a-button>
            <a-button
              v-permission="['generator/sync']"
              type="text"
              size="small"
              @click="handleSync(record)"
            >
              同步字段
            </a-button>
            <a-button
              v-permission="['generator/preview']"
              type="text"
              size="small"
              @click="openPreview(record)"
            >
              预览
            </a-button>
            <a-popconfirm
              content="确定删除该生成配置吗？"
              @ok="handleDelete(record)"
            >
              <a-button
                v-permission="['generator/delete']"
                type="text"
                status="danger"
                size="small"
              >
                删除
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="sourceVisible"
      title="导入数据表"
      width="760px"
      :ok-loading="importLoading"
      @ok="handleImport"
      @cancel="sourceVisible = false"
    >
      <a-input-search
        v-model="sourceKeyword"
        allow-clear
        placeholder="按表名或说明筛选"
        style="margin-bottom: 12px"
        @search="() => fetchSourceTables(1)"
        @press-enter="() => fetchSourceTables(1)"
      />
      <a-table
        v-model:selected-keys="sourceSelectedKeys"
        row-key="table_name"
        :loading="sourceLoading"
        :columns="sourceColumns"
        :data="sourceTables"
        :pagination="sourcePagination"
        :row-selection="{ type: 'checkbox', showCheckedAll: true }"
        :bordered="{ cell: true }"
        @page-change="fetchSourceTables"
      />
    </a-modal>

    <a-modal
      v-model:visible="editVisible"
      title="配置生成规则"
      width="1120px"
      :ok-loading="saveLoading"
      :mask-closable="false"
      @ok="handleSave"
      @cancel="editVisible = false"
    >
      <a-form
        ref="formRef"
        :model="editForm"
        :rules="formRules"
        layout="vertical"
      >
        <a-row :gutter="16">
          <a-col :span="8">
            <a-form-item field="table_comment" label="表说明">
              <a-input v-model="editForm.table_comment" />
            </a-form-item>
          </a-col>
          <a-col :span="8">
            <a-form-item field="module_name" label="模块名称">
              <a-input v-model="editForm.module_name" />
            </a-form-item>
          </a-col>
          <a-col :span="8">
            <a-form-item field="entity_name" label="实体名称">
              <a-input v-model="editForm.entity_name" />
            </a-form-item>
          </a-col>
          <a-col :span="8">
            <a-form-item field="template_type" label="模板类型">
              <a-select v-model="editForm.template_type">
                <a-option value="crud">CRUD</a-option>
                <a-option value="tree">树形</a-option>
              </a-select>
            </a-form-item>
          </a-col>
          <a-col :span="8">
            <a-form-item field="author" label="作者">
              <a-input v-model="editForm.author" />
            </a-form-item>
          </a-col>
        </a-row>

        <a-divider orientation="left">字段配置</a-divider>
        <a-table
          row-key="id"
          :data="editForm.columns"
          :pagination="false"
          :bordered="{ cell: true }"
          :scroll="{ x: 1320 }"
        >
          <template #columns>
            <a-table-column
              title="字段"
              data-index="column_name"
              :width="130"
            />
            <a-table-column
              title="类型"
              data-index="column_type"
              :width="130"
            />
            <a-table-column title="说明" :width="220">
              <template #cell="{ record }">
                <a-input v-model="record.column_comment" />
              </template>
            </a-table-column>
            <a-table-column title="列表" :width="80">
              <template #cell="{ record }">
                <a-switch
                  v-model="record.is_lists"
                  :checked-value="1"
                  :unchecked-value="0"
                />
              </template>
            </a-table-column>
            <a-table-column title="查询" :width="80">
              <template #cell="{ record }">
                <a-switch
                  v-model="record.is_query"
                  :checked-value="1"
                  :unchecked-value="0"
                />
              </template>
            </a-table-column>
            <a-table-column title="新增" :width="80">
              <template #cell="{ record }">
                <a-switch
                  v-model="record.is_insert"
                  :checked-value="1"
                  :unchecked-value="0"
                />
              </template>
            </a-table-column>
            <a-table-column title="编辑" :width="80">
              <template #cell="{ record }">
                <a-switch
                  v-model="record.is_update"
                  :checked-value="1"
                  :unchecked-value="0"
                />
              </template>
            </a-table-column>
            <a-table-column title="查询条件" :width="130">
              <template #cell="{ record }">
                <a-select v-model="record.query_type" style="width: 115px">
                  <a-option
                    v-for="item in queryTypes"
                    :key="item"
                    :value="item"
                    >{{ item }}</a-option
                  >
                </a-select>
              </template>
            </a-table-column>
            <a-table-column title="控件" :width="130">
              <template #cell="{ record }">
                <a-select v-model="record.view_type" style="width: 115px">
                  <a-option
                    v-for="item in viewTypes"
                    :key="item"
                    :value="item"
                    >{{ item }}</a-option
                  >
                </a-select>
              </template>
            </a-table-column>
            <a-table-column title="字典标识" :width="160">
              <template #cell="{ record }">
                <a-input v-model="record.dict_type" />
              </template>
            </a-table-column>
          </template>
        </a-table>

        <template v-if="editForm.template_type === 'tree'">
          <a-divider orientation="left">树形配置</a-divider>
          <a-row :gutter="16">
            <a-col :span="8">
              <a-form-item label="主键字段">
                <a-select v-model="editForm.tree_config.id_field">
                  <a-option
                    v-for="column in editForm.columns"
                    :key="column.id"
                    :value="column.column_name"
                    >{{ column.column_name }}</a-option
                  >
                </a-select>
              </a-form-item>
            </a-col>
            <a-col :span="8">
              <a-form-item label="父级字段">
                <a-select v-model="editForm.tree_config.parent_field">
                  <a-option
                    v-for="column in editForm.columns"
                    :key="column.id"
                    :value="column.column_name"
                    >{{ column.column_name }}</a-option
                  >
                </a-select>
              </a-form-item>
            </a-col>
            <a-col :span="8">
              <a-form-item label="名称字段">
                <a-select v-model="editForm.tree_config.name_field">
                  <a-option
                    v-for="column in editForm.columns"
                    :key="column.id"
                    :value="column.column_name"
                    >{{ column.column_name }}</a-option
                  >
                </a-select>
              </a-form-item>
            </a-col>
          </a-row>
        </template>

        <a-divider orientation="left">模型关系</a-divider>
        <a-space direction="vertical" fill>
          <a-space
            v-for="(relation, index) in editForm.relations"
            :key="relationKey(relation, index)"
            fill
          >
            <a-select
              v-model="relation.target_table_id"
              placeholder="目标配置"
              style="width: 200px"
            >
              <a-option
                v-for="model in models"
                :key="model.id"
                :value="model.id"
                >{{ model.entity_name }}（{{ model.table_name }}）</a-option
              >
            </a-select>
            <a-input
              v-model="relation.name"
              placeholder="关系名称"
              style="width: 150px"
            />
            <a-select v-model="relation.type" style="width: 130px">
              <a-option value="belongsTo">belongsTo</a-option>
              <a-option value="hasOne">hasOne</a-option>
              <a-option value="hasMany">hasMany</a-option>
            </a-select>
            <a-input
              v-model="relation.local_key"
              placeholder="本地字段"
              style="width: 130px"
            />
            <a-input
              v-model="relation.foreign_key"
              placeholder="目标字段"
              style="width: 130px"
            />
            <a-button type="text" status="danger" @click="removeRelation(index)"
              >删除</a-button
            >
          </a-space>
          <a-button type="outline" @click="addRelation">新增关系</a-button>
        </a-space>
      </a-form>
    </a-modal>

    <a-modal
      v-model:visible="previewVisible"
      title="代码预览"
      width="1080px"
      :footer="false"
    >
      <a-tabs
        v-if="previewFiles.length"
        v-model:active-key="previewActiveKey"
        type="card"
      >
        <a-tab-pane
          v-for="file in previewFiles"
          :key="file.path"
          :title="file.path"
        >
          <a-typography-paragraph copyable style="margin-bottom: 8px">{{
            file.path
          }}</a-typography-paragraph>
          <a-tag color="arcoblue" style="margin-bottom: 8px">
            {{ file.language }}
          </a-tag>
          <pre class="code-preview"><code>{{ file.content }}</code></pre>
        </a-tab-pane>
      </a-tabs>
      <a-empty v-else description="暂无预览" />
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import useLoading from '@/hooks/loading';
  import {
    deleteGenerator,
    downloadGenerator,
    generateGenerator,
    getGeneratorDetail,
    getGeneratorList,
    getGeneratorModels,
    getGeneratorSourceTables,
    importGeneratorTables,
    previewGenerator,
    syncGenerator,
    updateGenerator,
    type GeneratorColumn,
    type GeneratorGenerateResult,
    type GeneratorModel,
    type GeneratorPreviewFile,
    type GeneratorRecord,
    type GeneratorRelation,
    type GeneratorSourceTable,
    type GeneratorUpdateForm,
  } from '@/api/system/generator';

  const { loading, setLoading } = useLoading(true);
  const renderData = ref<GeneratorRecord[]>([]);
  const keyword = ref('');
  const selectedKeys = ref<number[]>([]);
  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });

  const columns = computed<TableColumnData[]>(() => [
    { title: '编号', dataIndex: 'id', width: 70 },
    { title: '数据表', dataIndex: 'table_name', width: 180 },
    { title: '表说明', dataIndex: 'table_comment', width: 220 },
    { title: '模块', dataIndex: 'module_name', width: 140 },
    { title: '实体', dataIndex: 'entity_name', width: 160 },
    { title: '模板', slotName: 'template_type', width: 90 },
    { title: '操作', slotName: 'operations', width: 300 },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const { data } = await getGeneratorList({
        keyword: keyword.value || undefined,
        page_no: page,
        page_size: pagination.pageSize,
      });
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
      selectedKeys.value = selectedKeys.value.filter((id) =>
        renderData.value.some((row) => row.id === id)
      );
    } finally {
      setLoading(false);
    }
  };
  fetchData();
  const onPageChange = (page: number) => fetchData(page);

  const sourceVisible = ref(false);
  const sourceLoading = ref(false);
  const importLoading = ref(false);
  const sourceKeyword = ref('');
  const sourceTables = ref<GeneratorSourceTable[]>([]);
  const sourceSelectedKeys = ref<string[]>([]);
  const sourcePagination = reactive({
    current: 1,
    pageSize: 10,
    total: 0,
    showTotal: true,
  });
  const sourceColumns = computed<TableColumnData[]>(() => [
    { title: '表名', dataIndex: 'table_name' },
    { title: '表说明', dataIndex: 'table_comment' },
    { title: '引擎', dataIndex: 'engine', width: 100 },
    { title: '估计行数', dataIndex: 'table_rows', width: 100 },
    { title: '创建时间', dataIndex: 'create_time', width: 180 },
  ]);

  const fetchSourceTables = async (page = 1) => {
    sourceLoading.value = true;
    try {
      const { data } = await getGeneratorSourceTables({
        keyword: sourceKeyword.value || undefined,
        page_no: page,
        page_size: sourcePagination.pageSize,
      });
      sourceTables.value = data.lists;
      sourcePagination.current = data.pageNo;
      sourcePagination.total = data.count;
    } finally {
      sourceLoading.value = false;
    }
  };
  const openSourceTables = async () => {
    sourceVisible.value = true;
    sourceSelectedKeys.value = [];
    await fetchSourceTables(1);
  };
  const handleImport = async () => {
    if (!sourceSelectedKeys.value.length) {
      Message.warning('请选择至少一张数据表');
      return false;
    }
    importLoading.value = true;
    try {
      await importGeneratorTables(sourceSelectedKeys.value);
      Message.success('导入成功');
      sourceVisible.value = false;
      await fetchData(1);
      return true;
    } finally {
      importLoading.value = false;
    }
  };

  const editVisible = ref(false);
  const saveLoading = ref(false);
  const formRef = ref<FormInstance>();
  const models = ref<GeneratorModel[]>([]);
  const editForm = reactive<
    GeneratorUpdateForm & {
      columns: GeneratorColumn[];
      relations: GeneratorRelation[];
      tree_config: Record<string, string>;
    }
  >({
    id: 0,
    table_comment: '',
    module_name: '',
    entity_name: '',
    template_type: 'crud',
    author: '',
    tree_config: { id_field: '', parent_field: '', name_field: '' },
    relations: [],
    columns: [],
  });
  const formRules = {
    table_comment: [{ required: true, message: '表说明不能为空' }],
    module_name: [{ required: true, message: '模块名称不能为空' }],
    entity_name: [{ required: true, message: '实体名称不能为空' }],
    template_type: [{ required: true, message: '模板类型不能为空' }],
  };
  const openEdit = async (record: GeneratorRecord) => {
    const [{ data }, modelResult] = await Promise.all([
      getGeneratorDetail(record.id),
      getGeneratorModels(),
    ]);
    Object.assign(editForm, {
      id: data.id,
      table_comment: data.table_comment,
      module_name: data.module_name,
      entity_name: data.entity_name,
      template_type: data.template_type === 'tree' ? 'tree' : 'crud',
      author: data.author || '',
      tree_config: {
        id_field: data.tree_config?.id_field || '',
        parent_field: data.tree_config?.parent_field || '',
        name_field: data.tree_config?.name_field || '',
      },
      relations: (data.relations || []).map((relation) => ({ ...relation })),
      columns: (data.columns || []).map((column) => ({ ...column })),
    });
    models.value = modelResult.data.filter((model) => model.id !== data.id);
    editVisible.value = true;
  };
  const handleSave = async () => {
    const error = await formRef.value?.validate();
    if (error) return false;
    saveLoading.value = true;
    try {
      await updateGenerator({
        ...editForm,
        columns: editForm.columns.map((column) => ({
          id: column.id,
          column_comment: column.column_comment,
          is_required: column.is_required,
          is_insert: column.is_insert,
          is_update: column.is_update,
          is_lists: column.is_lists,
          is_query: column.is_query,
          query_type: column.query_type,
          view_type: column.view_type,
          dict_type: column.dict_type,
        })),
      });
      Message.success('保存成功');
      editVisible.value = false;
      await fetchData(pagination.current);
      return true;
    } finally {
      saveLoading.value = false;
    }
  };
  const addRelation = () => {
    const first = models.value[0];
    if (!first) {
      Message.warning('暂无可关联的生成配置');
      return;
    }
    editForm.relations.push({
      target_table_id: first.id,
      name: '',
      type: 'belongsTo',
      local_key: 'id',
      foreign_key: 'id',
    });
  };
  const removeRelation = (index: number) => editForm.relations.splice(index, 1);
  const relationKey = (relation: GeneratorRelation, index: number) =>
    `${relation.target_table_id}-${index}`;

  const handleSync = async (record: GeneratorRecord) => {
    await syncGenerator(record.id);
    Message.success('同步成功');
    await fetchData(pagination.current);
  };
  const handleDelete = async (record: GeneratorRecord) => {
    await deleteGenerator([record.id]);
    Message.success('删除成功');
    selectedKeys.value = selectedKeys.value.filter((id) => id !== record.id);
    await fetchData(pagination.current);
  };

  const previewVisible = ref(false);
  const previewFiles = ref<GeneratorPreviewFile[]>([]);
  const previewActiveKey = ref('');
  const openPreview = async (record: GeneratorRecord) => {
    const { data } = await previewGenerator(record.id);
    previewFiles.value = data;
    previewActiveKey.value = data[0]?.path || '';
    previewVisible.value = true;
  };

  const generateLoading = ref(false);
  const generateSelected = async () => {
    if (!selectedKeys.value.length) return;
    generateLoading.value = true;
    try {
      const { data } = await generateGenerator(selectedKeys.value);
      // The download is deliberately consumed immediately; the server token is one-shot.
      // eslint-disable-next-line no-use-before-define
      await downloadGenerated(data);
      Message.success('代码已生成并下载');
      selectedKeys.value = [];
    } finally {
      generateLoading.value = false;
    }
  };
  const downloadGenerated = async (result: GeneratorGenerateResult) => {
    const blob = await downloadGenerator(result.download_token);
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = result.file_name;
    anchor.rel = 'noopener';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  };

  const queryTypes = ['=', '<>', '>', '>=', '<', '<=', 'like', 'between'];
  const viewTypes = [
    'input',
    'textarea',
    'select',
    'radio',
    'checkbox',
    'switch',
    'date',
    'datetime',
    'number',
  ];
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px;
  }

  .code-preview {
    max-height: 62vh;
    margin: 0;
    padding: 16px;
    overflow: auto;
    color: var(--color-text-1);
    background: var(--color-fill-2);
    border-radius: 4px;
    white-space: pre;
  }
</style>
