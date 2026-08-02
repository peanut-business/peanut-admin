<template>
  <div class="container">
    <Breadcrumb :items="['menu.article', 'menu.article.cate']" />
    <a-card class="general-card" :title="$t('menu.article.cate')">
      <a-alert type="warning" style="margin-bottom: 16px">
        {{ $t('articleCate.alert') }}
      </a-alert>
      <a-row style="margin-bottom: 16px">
        <a-col :span="24">
          <a-button
            v-permission="['article.articleCate/add']"
            type="primary"
            @click="openAdd"
          >
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
        :pagination="pagination"
        :bordered="{ cell: true }"
        @page-change="onPageChange"
      >
        <template #is_show="{ record }">
          <a-switch
            v-permission="['article.articleCate/updateStatus']"
            :model-value="record.is_show === 1"
            @change="(v) => onStatusChange(record, v)"
          />
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['article.articleCate/edit']"
              type="text"
              size="small"
              @click="openEdit(record)"
            >
              {{ $t('articleCate.button.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('articleCate.confirm.delete')"
              @ok="onDelete(record)"
            >
              <a-button
                v-permission="['article.articleCate/delete']"
                type="text"
                size="small"
                status="danger"
              >
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
              :max-length="90"
              show-word-limit
            />
          </a-form-item>
          <a-form-item field="sort" :label="$t('articleCate.form.sort')">
            <div>
              <a-input-number
                v-model="form.sort"
                :min="0"
                :max="9999"
                style="width: 160px"
              />
              <div class="form-tip">
                {{ $t('articleCate.form.sort.tip') }}
              </div>
            </div>
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
  import { computed, nextTick, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import {
    getArticleCateList,
    getArticleCateDetail,
    addArticleCate,
    editArticleCate,
    deleteArticleCate,
    updateArticleCateStatus,
    type ArticleCateRecord,
  } from '@/api/article';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<ArticleCateRecord[]>([]);

  const pagination = reactive({
    current: 1,
    pageSize: 25,
    total: 0,
    showTotal: true,
  });

  const columns = computed<TableColumnData[]>(() => [
    { title: t('articleCate.columns.name'), dataIndex: 'name' },
    {
      title: t('articleCate.columns.articleCount'),
      dataIndex: 'article_count',
      width: 120,
    },
    { title: t('articleCate.columns.isShow'), slotName: 'is_show', width: 100 },
    { title: t('articleCate.columns.sort'), dataIndex: 'sort', width: 100 },
    {
      title: t('articleCate.columns.operations'),
      slotName: 'operations',
      width: 160,
    },
  ]);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const { data } = await getArticleCateList({
        page_no: page,
        page_size: pagination.pageSize,
      });
      renderData.value = data.lists;
      pagination.current = data.page_no;
      pagination.pageSize = data.page_size;
      pagination.total = data.count;
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const onPageChange = (current: number) => fetchData(current);

  const formRef = ref<FormInstance>();
  const modalVisible = ref(false);
  const generateForm = () => ({ id: 0, name: '', sort: 0, is_show: 1 });
  const form = ref(generateForm());

  const rules = {
    name: [
      { required: true, message: t('articleCate.form.name.required') },
      {
        minLength: 1,
        maxLength: 90,
        message: t('articleCate.form.name.length'),
      },
    ],
    sort: [
      {
        validator: (
          value: number,
          callback: (message?: string) => void
        ) => {
          if (value == null || Number(value) < 0) {
            callback(t('articleCate.form.sort.min'));
            return;
          }
          callback();
        },
      },
    ],
  };

  const openAdd = async () => {
    form.value = generateForm();
    modalVisible.value = true;
    await nextTick();
    formRef.value?.clearValidate();
  };

  const openEdit = async (record: ArticleCateRecord) => {
    const { data } = await getArticleCateDetail(record.id);
    form.value = {
      id: data.id,
      name: data.name,
      sort: data.sort,
      is_show: data.is_show,
    };
    modalVisible.value = true;
    await nextTick();
    formRef.value?.clearValidate();
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
    fetchData(pagination.current);
    return true;
  };

  const onDelete = async (record: ArticleCateRecord) => {
    await deleteArticleCate(record.id);
    Message.success(t('articleCate.message.success'));
    fetchData(pagination.current);
  };

  const onStatusChange = async (record: ArticleCateRecord, val: unknown) => {
    const previousStatus = record.is_show;
    const nextStatus = val ? 1 : 0;
    try {
      await updateArticleCateStatus(record.id, nextStatus);
      record.is_show = nextStatus;
      Message.success(t('articleCate.message.success'));
    } catch {
      record.is_show = previousStatus;
    }
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

  .form-tip {
    margin-top: 4px;
    color: var(--color-text-3);
    font-size: 12px;
    line-height: 20px;
  }
</style>
