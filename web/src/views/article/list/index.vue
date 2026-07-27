<template>
  <div class="container">
    <Breadcrumb :items="['menu.article', 'menu.article.list']" />
    <a-card class="general-card" :title="$t('menu.article.list')">
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
                <a-form-item field="title" :label="$t('article.form.title')">
                  <a-input
                    v-model="formModel.title"
                    allow-clear
                    :placeholder="$t('article.form.title.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="cate_id" :label="$t('article.form.cate')">
                  <a-select
                    v-model="formModel.cate_id"
                    allow-clear
                    :options="cateOptions"
                    :placeholder="$t('article.form.cate.placeholder')"
                  />
                </a-form-item>
              </a-col>
              <a-col :span="8">
                <a-form-item field="is_show" :label="$t('article.form.isShow')">
                  <a-select
                    v-model="formModel.is_show"
                    allow-clear
                    :options="showOptions"
                    :placeholder="$t('article.form.isShow.placeholder')"
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
              {{ $t('article.form.search') }}
            </a-button>
            <a-button @click="reset">
              <template #icon><icon-refresh /></template>
              {{ $t('article.form.reset') }}
            </a-button>
          </a-space>
        </a-col>
      </a-row>
      <a-divider style="margin-top: 0" />
      <a-row style="margin-bottom: 16px">
        <a-col :span="24">
          <a-button type="primary" @click="openAdd">
            <template #icon><icon-plus /></template>
            {{ $t('article.button.add') }}
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
        <template #image="{ record }">
          <a-image
            v-if="record.image"
            :src="record.image"
            width="60"
            height="40"
            fit="cover"
          />
          <span v-else>-</span>
        </template>
        <template #is_show="{ record }">
          <a-switch
            :model-value="record.is_show === 1"
            @change="(v) => onStatusChange(record, v)"
          />
        </template>
        <template #create_time="{ record }">
          {{ formatTime(record.create_time) }}
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="openEdit(record)">
              {{ $t('article.button.edit') }}
            </a-button>
            <a-popconfirm
              :content="$t('article.confirm.delete')"
              @ok="onDelete(record)"
            >
              <a-button type="text" size="small" status="danger">
                {{ $t('article.button.delete') }}
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>

      <a-modal
        v-model:visible="modalVisible"
        :title="form.id ? $t('article.modal.edit') : $t('article.modal.add')"
        :width="720"
        @before-ok="onSubmit"
        @cancel="modalVisible = false"
      >
        <a-form ref="formRef" :model="form" :rules="rules" auto-label-width>
          <a-form-item field="cate_id" :label="$t('article.field.cate')">
            <a-select
              v-model="form.cate_id"
              :options="cateOptions"
              :placeholder="$t('article.form.cate.placeholder')"
            />
          </a-form-item>
          <a-form-item field="title" :label="$t('article.field.title')">
            <a-input
              v-model="form.title"
              :placeholder="$t('article.form.title.placeholder')"
            />
          </a-form-item>
          <a-form-item field="image" :label="$t('article.field.image')">
            <a-upload
              :action="uploadAction"
              :headers="uploadHeaders"
              :show-file-list="false"
              list-type="picture-card"
              accept="image/*"
              @success="onImageSuccess"
              @error="onImageError"
            >
              <template #upload-button>
                <div class="img-uploader">
                  <img v-if="form.image" :src="form.image" alt="cover" />
                  <icon-plus v-else />
                </div>
              </template>
            </a-upload>
          </a-form-item>
          <a-form-item field="author" :label="$t('article.field.author')">
            <a-input v-model="form.author" />
          </a-form-item>
          <a-form-item field="intro" :label="$t('article.field.intro')">
            <a-textarea
              v-model="form.intro"
              :max-length="255"
              show-word-limit
            />
          </a-form-item>
          <a-form-item field="content" :label="$t('article.field.content')">
            <a-textarea
              v-model="form.content"
              :auto-size="{ minRows: 6, maxRows: 16 }"
            />
          </a-form-item>
          <a-form-item field="sort" :label="$t('article.field.sort')">
            <a-input-number v-model="form.sort" :min="0" />
          </a-form-item>
          <a-form-item field="is_show" :label="$t('article.field.isShow')">
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
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { TableColumnData } from '@arco-design/web-vue/es/table/interface';
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import type { FileItem } from '@arco-design/web-vue/es/upload/interfaces';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import { getToken } from '@/utils/auth';
  import {
    getArticleList,
    getArticleCateAll,
    getArticleDetail,
    addArticle,
    editArticle,
    deleteArticle,
    updateArticleStatus,
    type ArticleRecord,
    type ArticleListParams,
  } from '@/api/article';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<ArticleRecord[]>([]);
  const cateOptions = ref<SelectOptionData[]>([]);

  const uploadAction = '/api/admin/upload/image';
  const uploadHeaders = computed(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });

  const generateFormModel = () => ({
    title: '',
    cate_id: '' as number | string,
    is_show: '' as number | string,
  });
  const formModel = ref(generateFormModel());

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
    showTotal: true,
  });

  const showOptions = computed<SelectOptionData[]>(() => [
    { label: t('article.show.1'), value: 1 },
    { label: t('article.show.0'), value: 0 },
  ]);

  // 后端已将 int 时间戳格式化为 "Y-m-d H:i:s" 字符串，直接展示；
  // 兼容极少数返回秒级整数的场景。
  const formatTime = (ts: number | string) => {
    if (!ts) return '-';
    if (typeof ts === 'number') return new Date(ts * 1000).toLocaleString();
    return ts;
  };

  const columns = computed<TableColumnData[]>(() => [
    { title: t('article.columns.id'), dataIndex: 'id', width: 70 },
    { title: t('article.columns.image'), slotName: 'image', width: 90 },
    { title: t('article.columns.title'), dataIndex: 'title' },
    { title: t('article.columns.cate'), dataIndex: 'cate_name', width: 120 },
    { title: t('article.columns.author'), dataIndex: 'author', width: 100 },
    { title: t('article.columns.clickNum'), dataIndex: 'click_num', width: 90 },
    { title: t('article.columns.sort'), dataIndex: 'sort', width: 80 },
    { title: t('article.columns.isShow'), slotName: 'is_show', width: 90 },
    {
      title: t('article.columns.createTime'),
      slotName: 'create_time',
      width: 170,
    },
    {
      title: t('article.columns.operations'),
      slotName: 'operations',
      width: 150,
    },
  ]);

  const fetchCateOptions = async () => {
    const { data } = await getArticleCateAll();
    cateOptions.value = data.map((c) => ({ label: c.name, value: c.id }));
  };
  fetchCateOptions();

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: ArticleListParams = {
        title: formModel.value.title || undefined,
        cate_id:
          formModel.value.cate_id === '' ? undefined : formModel.value.cate_id,
        is_show:
          formModel.value.is_show === '' ? undefined : formModel.value.is_show,
        pageNo: page,
        pageSize: pagination.pageSize,
      };
      const { data } = await getArticleList(params);
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

  const formRef = ref<FormInstance>();
  const modalVisible = ref(false);
  const generateForm = (): Partial<ArticleRecord> => ({
    id: 0,
    cate_id: undefined,
    title: '',
    intro: '',
    image: '',
    author: '',
    content: '',
    sort: 0,
    is_show: 1,
  });
  const form = ref<Partial<ArticleRecord>>(generateForm());

  const rules = {
    cate_id: [{ required: true, message: t('article.form.cate.placeholder') }],
    title: [{ required: true, message: t('article.form.title.placeholder') }],
  };

  const openAdd = () => {
    form.value = generateForm();
    modalVisible.value = true;
  };

  const openEdit = async (record: ArticleRecord) => {
    const { data } = await getArticleDetail(record.id);
    form.value = { ...data };
    modalVisible.value = true;
  };

  const onImageSuccess = (fileItem: FileItem) => {
    const res = fileItem.response as
      | { code: number; msg: string; data: { url: string } }
      | undefined;
    if (!res || res.code !== 20000) {
      Message.error(res?.msg || t('article.tip.uploadFail'));
      return;
    }
    form.value.image = res.data.url;
    Message.success(t('article.tip.uploadSuccess'));
  };
  const onImageError = () => {
    Message.error(t('article.tip.uploadFail'));
  };

  const onSubmit = async () => {
    const err = await formRef.value?.validate();
    if (err) return false;
    if (form.value.id) {
      await editArticle(form.value);
    } else {
      await addArticle(form.value);
    }
    Message.success(t('article.message.success'));
    fetchData(pagination.current);
    return true;
  };

  const onDelete = async (record: ArticleRecord) => {
    await deleteArticle(record.id);
    Message.success(t('article.message.success'));
    fetchData(pagination.current);
  };

  const onStatusChange = async (record: ArticleRecord, val: unknown) => {
    await updateArticleStatus(record.id, val ? 1 : 0);
    record.is_show = val ? 1 : 0;
    Message.success(t('article.message.success'));
  };
</script>

<script lang="ts">
  export default {
    name: 'ArticleList',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .img-uploader {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;

    img {
      max-width: 100%;
      max-height: 100%;
    }
  }
</style>
