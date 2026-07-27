<template>
  <div class="container">
    <Breadcrumb :items="['menu.article', 'menu.article.cate']" />
    <a-card class="general-card" :title="$t('menu.article.cate')">
      <a-row style="margin-bottom: 16px">
        <a-col :span="24">
          <a-button type="primary" @click="openAdd">
            <template #icon><icon-plus /></template>
            {{ $t('articleCate.button.add') }}
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
        <template #is_show="{ record }">
          <a-switch
            :model-value="record.is_show === 1"
            @change="(v) => onStatusChange(record, v)"
          />
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="openEdit(record)">
              {{ $t('articleCate.button.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('articleCate.confirm.delete')"
              @ok="onDelete(record)"
            >
              <a-button type="text" size="small" status="danger">
                {{ $t('articleCate.button.delete') }}
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>

      <a-modal
        v-model:visible="modalVisible"
        :title="
          form.id ? $t('articleCate.modal.edit') : $t('articleCate.modal.add')
        "
        @before-ok="onSubmit"
        @cancel="modalVisible = false"
      >
        <a-form ref="formRef" :model="form" :rules="rules" auto-label-width>
          <a-form-item field="name" :label="$t('articleCate.form.name')">
            <a-input
              v-model="form.name"
              :placeholder="$t('articleCate.form.name.placeholder')"
            />
          </a-form-item>
          <a-form-item field="sort" :label="$t('articleCate.form.sort')">
            <a-input-number v-model="form.sort" :min="0" />
          </a-form-item>
          <a-form-item field="is_show" :label="$t('articleCate.form.isShow')">
            <a-switch
              v-model="form.is_show"
              :checked-value="1"
              :unchecked-value="0"
            />
          </a-form-item>
        </a-form>
      </a-modal>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getArticleCateList,
    addArticleCate,
    editArticleCate,
    deleteArticleCate,
    updateArticleCateStatus,
    type ArticleCateRecord,
  } from '@/api/article';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<ArticleCateRecord[]>([]);

  const columns = computed<TableColumnData[]>(() => [
    { title: t('articleCate.columns.id'), dataIndex: 'id', width: 80 },
    { title: t('articleCate.columns.name'), dataIndex: 'name' },
    {
      title: t('articleCate.columns.articleCount'),
      dataIndex: 'article_count',
      width: 120,
    },
    { title: t('articleCate.columns.sort'), dataIndex: 'sort', width: 100 },
    { title: t('articleCate.columns.isShow'), slotName: 'is_show', width: 100 },
    {
      title: t('articleCate.columns.operations'),
      slotName: 'operations',
      width: 160,
    },
  ]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getArticleCateList();
      renderData.value = data;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const formRef = ref<FormInstance>();
  const modalVisible = ref(false);
  const generateForm = () => ({ id: 0, name: '', sort: 0, is_show: 1 });
  const form = ref(generateForm());

  const rules = {
    name: [{ required: true, message: t('articleCate.form.name.placeholder') }],
  };

  const openAdd = () => {
    form.value = generateForm();
    modalVisible.value = true;
  };

  const openEdit = (record: ArticleCateRecord) => {
    form.value = {
      id: record.id,
      name: record.name,
      sort: record.sort,
      is_show: record.is_show,
    };
    modalVisible.value = true;
  };

  const onSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return false;
    if (form.value.id) {
      await editArticleCate(form.value);
    } else {
      await addArticleCate(form.value);
    }
    Message.success(t('articleCate.message.success'));
    fetchData();
    return true;
  };

  const onDelete = async (record: ArticleCateRecord) => {
    await deleteArticleCate(record.id);
    Message.success(t('articleCate.message.success'));
    fetchData();
  };

  const onStatusChange = async (record: ArticleCateRecord, val: unknown) => {
    await updateArticleCateStatus(record.id, val ? 1 : 0);
    record.is_show = val ? 1 : 0;
    Message.success(t('articleCate.message.success'));
  };
</script>

<script lang="ts">
  export default {
    name: 'ArticleCate',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
