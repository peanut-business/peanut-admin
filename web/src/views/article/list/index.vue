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
                <a-form-item field="cid" :label="$t('article.form.cate')">
                  <a-select
                    v-model="formModel.cid"
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
          <a-button
            v-permission="['article.article/add']"
            type="primary"
            @click="openAdd"
          >
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
            v-permission="['article.article/updateStatus']"
            :model-value="record.is_show === 1"
            @change="(value) => onStatusChange(record, value)"
          />
        </template>
        <template #create_time="{ record }">
          {{ formatTime(record.create_time) }}
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button
              v-permission="['article.article/edit']"
              type="text"
              size="small"
              @click="openEdit(record)"
            >
              {{ $t('article.button.edit') }}
            </a-button>
            <a-popconfirm
              v-permission="['article.article/delete']"
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
        :width="900"
        @before-ok="onSubmit"
        @cancel="closeModal"
      >
        <a-form ref="formRef" :model="form" :rules="rules" auto-label-width>
          <a-form-item field="cid" :label="$t('article.field.cate')">
            <a-select
              v-model="form.cid"
              :options="cateOptions"
              :placeholder="$t('article.form.cate.placeholder')"
            />
          </a-form-item>
          <a-form-item field="title" :label="$t('article.field.title')">
            <a-input
              v-model="form.title"
              :max-length="64"
              show-word-limit
              :placeholder="$t('article.form.title.placeholder')"
            />
          </a-form-item>
          <a-form-item field="desc" :label="$t('article.field.desc')">
            <a-textarea
              v-model="form.desc"
              :max-length="200"
              show-word-limit
              :auto-size="{ minRows: 2, maxRows: 4 }"
            />
          </a-form-item>
          <a-form-item field="abstract" :label="$t('article.field.abstract')">
            <a-textarea
              v-model="form.abstract"
              :max-length="200"
              show-word-limit
              :auto-size="{ minRows: 2, maxRows: 4 }"
            />
          </a-form-item>
          <a-form-item field="image" :label="$t('article.field.image')">
            <a-space align="end">
              <a-upload
                :action="uploadUrl[10]"
                :headers="uploadHeaders"
                :show-file-list="false"
                list-type="picture-card"
                accept="image/*"
                @success="onCoverSuccess"
                @error="onUploadError"
              >
                <template #upload-button>
                  <div class="img-uploader">
                    <img v-if="form.image" :src="form.image" alt="cover" />
                    <icon-plus v-else />
                  </div>
                </template>
              </a-upload>
              <file-picker
                :type="10"
                :limit="1"
                button-text="从素材库选择"
                @select="onCoverSelected"
              />
            </a-space>
          </a-form-item>
          <a-form-item field="author" :label="$t('article.field.author')">
            <a-input v-model="form.author" />
          </a-form-item>
          <a-form-item field="sort" :label="$t('article.field.sort')">
            <a-input-number v-model="form.sort" :min="0" :max="9999" />
          </a-form-item>
          <a-form-item
            field="click_virtual"
            :label="$t('article.field.clickVirtual')"
          >
            <a-input-number v-model="form.click_virtual" :min="0" />
          </a-form-item>
          <a-form-item field="is_show" :label="$t('article.field.isShow')">
            <a-switch
              v-model="form.is_show"
              :checked-value="1"
              :unchecked-value="0"
            />
          </a-form-item>
          <a-form-item field="content" :label="$t('article.field.content')">
            <div class="rich-editor">
              <div class="rich-editor__toolbar">
                <a-tooltip content="粗体">
                  <a-button size="mini" @mousedown.prevent="formatContent('bold')">
                    <icon-bold />
                  </a-button>
                </a-tooltip>
                <a-tooltip content="斜体">
                  <a-button
                    size="mini"
                    @mousedown.prevent="formatContent('italic')"
                  >
                    <icon-italic />
                  </a-button>
                </a-tooltip>
                <a-tooltip content="无序列表">
                  <a-button
                    size="mini"
                    @mousedown.prevent="formatContent('insertUnorderedList')"
                  >
                    <icon-unordered-list />
                  </a-button>
                </a-tooltip>
                <a-upload
                  :action="uploadUrl[10]"
                  :headers="uploadHeaders"
                  :show-file-list="false"
                  accept="image/*"
                  @before-upload="beforeContentUpload"
                  @success="(file) => onContentMediaSuccess(file, 'image')"
                  @error="onUploadError"
                >
                  <template #upload-button>
                    <a-tooltip content="插入图片">
                      <a-button size="mini" aria-label="插入图片">
                        <icon-image />
                      </a-button>
                    </a-tooltip>
                  </template>
                </a-upload>
                <file-picker
                  :type="10"
                  :limit="20"
                  size="mini"
                  button-text="素材图片"
                  @open="saveContentRange"
                  @select="(urls) => onContentMaterialSelected(urls, 'image')"
                />
                <a-upload
                  :action="uploadUrl[20]"
                  :headers="uploadHeaders"
                  :show-file-list="false"
                  accept="video/*"
                  @before-upload="beforeContentUpload"
                  @success="(file) => onContentMediaSuccess(file, 'video')"
                  @error="onUploadError"
                >
                  <template #upload-button>
                    <a-tooltip content="插入视频">
                      <a-button size="mini" aria-label="插入视频">
                        <icon-video-camera />
                      </a-button>
                    </a-tooltip>
                  </template>
                </a-upload>
                <file-picker
                  :type="20"
                  :limit="10"
                  size="mini"
                  button-text="素材视频"
                  @open="saveContentRange"
                  @select="(urls) => onContentMaterialSelected(urls, 'video')"
                />
              </div>
              <div
                ref="contentEditorRef"
                class="rich-editor__content"
                contenteditable="true"
                @input="onContentInput"
                @keyup="saveContentRange"
                @mouseup="saveContentRange"
              ></div>
            </div>
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
  import type { SelectOptionData } from '@arco-design/web-vue/es/select/interface';
  import type { FileItem } from '@arco-design/web-vue/es/upload/interfaces';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import { getToken } from '@/utils/auth';
  import FilePicker from '@/components/file-picker/index.vue';
  import { uploadUrl } from '@/api/system/file';
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

  interface ArticleForm {
    id: number;
    cid: number | undefined;
    title: string;
    desc: string;
    abstract: string;
    image: string;
    author: string;
    content: string;
    click_virtual: number;
    sort: number;
    is_show: number;
  }

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const renderData = ref<ArticleRecord[]>([]);
  const cateOptions = ref<SelectOptionData[]>([]);

  const uploadHeaders = computed(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });

  const generateFormModel = () => ({
    title: '',
    cid: '' as number | string,
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

  const formatTime = (value?: number | string) => {
    if (!value) return '-';
    if (typeof value === 'number') {
      return new Date(value * 1000).toLocaleString();
    }
    return value;
  };

  const columns = computed<TableColumnData[]>(() => [
    { title: t('article.columns.id'), dataIndex: 'id', width: 70 },
    { title: t('article.columns.image'), slotName: 'image', width: 90 },
    { title: t('article.columns.title'), dataIndex: 'title' },
    { title: t('article.columns.cate'), dataIndex: 'cate_name', width: 120 },
    { title: t('article.columns.author'), dataIndex: 'author', width: 100 },
    { title: t('article.columns.click'), dataIndex: 'click', width: 100 },
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
    cateOptions.value = data.map((item) => ({
      label: item.name,
      value: item.id,
    }));
  };
  fetchCateOptions();

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params: ArticleListParams = {
        title: formModel.value.title || undefined,
        cid: formModel.value.cid === '' ? undefined : formModel.value.cid,
        is_show:
          formModel.value.is_show === '' ? undefined : formModel.value.is_show,
        page_no: page,
        page_size: pagination.pageSize,
        page_type: 1,
      };
      const { data } = await getArticleList(params);
      renderData.value = data.lists;
      pagination.current = data.page_no;
      pagination.pageSize = data.page_size;
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
  const contentEditorRef = ref<HTMLDivElement>();
  const contentRange = ref<Range>();
  const modalVisible = ref(false);
  const generateForm = (): ArticleForm => ({
    id: 0,
    cid: undefined,
    title: '',
    desc: '',
    abstract: '',
    image: '',
    author: '',
    content: '',
    click_virtual: 0,
    sort: 0,
    is_show: 1,
  });
  const form = ref<ArticleForm>(generateForm());

  const validateRange =
    (min: number, max?: number) =>
    (value: number, callback: (message?: string) => void) => {
      const number = Number(value);
      if (
        !Number.isInteger(number) ||
        number < min ||
        (max !== undefined && number > max)
      ) {
        callback(
          max === undefined
            ? t('article.validation.nonNegative')
            : t('article.validation.sort')
        );
        return;
      }
      callback();
    };

  const rules = {
    cid: [{ required: true, message: t('article.form.cate.placeholder') }],
    title: [
      { required: true, message: t('article.form.title.placeholder') },
      {
        maxLength: 64,
        message: t('article.validation.titleLength'),
      },
    ],
    desc: [
      { maxLength: 200, message: t('article.validation.textLength') },
    ],
    abstract: [
      { maxLength: 200, message: t('article.validation.textLength') },
    ],
    sort: [{ validator: validateRange(0, 9999) }],
    click_virtual: [{ validator: validateRange(0) }],
  };

  const syncEditor = async () => {
    await nextTick();
    if (contentEditorRef.value) {
      contentEditorRef.value.innerHTML = form.value.content || '';
    }
    formRef.value?.clearValidate();
  };

  const openAdd = async () => {
    contentRange.value = undefined;
    form.value = generateForm();
    modalVisible.value = true;
    await syncEditor();
  };

  const openEdit = async (record: ArticleRecord) => {
    contentRange.value = undefined;
    const { data } = await getArticleDetail(record.id);
    form.value = {
      id: data.id,
      cid: data.cid,
      title: data.title,
      desc: data.desc || '',
      abstract: data.abstract || '',
      image: data.image || '',
      author: data.author || '',
      content: data.content || '',
      click_virtual: data.click_virtual || 0,
      sort: data.sort || 0,
      is_show: data.is_show,
    };
    modalVisible.value = true;
    await syncEditor();
  };

  const onContentInput = (event: Event) => {
    form.value.content = (event.target as HTMLDivElement).innerHTML;
    saveContentRange();
  };

  const saveContentRange = () => {
    const editor = contentEditorRef.value;
    const selection = window.getSelection();
    if (!editor || !selection?.rangeCount) return;
    const range = selection.getRangeAt(0);
    if (editor.contains(range.commonAncestorContainer)) {
      contentRange.value = range.cloneRange();
    }
  };

  const beforeContentUpload = () => {
    saveContentRange();
    return true;
  };

  const formatContent = (command: string) => {
    contentEditorRef.value?.focus();
    document.execCommand(command);
    if (contentEditorRef.value) {
      form.value.content = contentEditorRef.value.innerHTML;
    }
  };

  type UploadResponse = {
    code: number;
    msg: string;
    data: { url?: string; uri?: string };
  };

  const uploadResult = (fileItem: FileItem): string => {
    const response = fileItem.response as
      | UploadResponse
      | undefined;
    const url = response?.data?.url || response?.data?.uri || '';
    if (!response || response.code !== 20000 || !url) {
      Message.error(response?.msg || t('article.tip.uploadFail'));
      return '';
    }
    return url;
  };

  const onCoverSuccess = (fileItem: FileItem) => {
    const url = uploadResult(fileItem);
    if (!url) return;
    form.value.image = url;
    Message.success(t('article.tip.uploadSuccess'));
  };

  const onCoverSelected = (urls: string[]) => {
    form.value.image = urls[0] || '';
  };

  const insertContentMedia = (url: string, type: 'image' | 'video') => {
    const editor = contentEditorRef.value;
    if (!url || !editor) return;

    const media = document.createElement(type === 'image' ? 'img' : 'video');
    media.setAttribute('src', url);
    if (type === 'video') {
      media.setAttribute('controls', 'controls');
      media.setAttribute('preload', 'metadata');
    }

    const range = contentRange.value;
    if (range && editor.contains(range.commonAncestorContainer)) {
      range.deleteContents();
      range.insertNode(media);
      range.setStartAfter(media);
      range.collapse(true);
      contentRange.value = range;
    } else {
      editor.appendChild(media);
    }
    form.value.content = editor.innerHTML;
  };

  const onContentMaterialSelected = (
    urls: string[],
    type: 'image' | 'video'
  ) => {
    urls.forEach((url) => insertContentMedia(url, type));
  };

  const onContentMediaSuccess = (
    fileItem: FileItem,
    type: 'image' | 'video'
  ) => {
    const url = uploadResult(fileItem);
    if (!url) return;
    insertContentMedia(url, type);
    Message.success(t('article.tip.uploadSuccess'));
  };

  const onUploadError = () => {
    Message.error(t('article.tip.uploadFail'));
  };

  const closeModal = () => {
    contentRange.value = undefined;
    modalVisible.value = false;
  };

  const onSubmit = async () => {
    if (contentEditorRef.value) {
      form.value.content = contentEditorRef.value.innerHTML;
    }
    const error = await formRef.value?.validate();
    if (error) return false;
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

  const onStatusChange = async (record: ArticleRecord, value: unknown) => {
    const isShow = value ? 1 : 0;
    await updateArticleStatus(record.id, isShow);
    record.is_show = isShow;
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

  .rich-editor {
    width: 100%;
    overflow: hidden;
    border: 1px solid var(--color-neutral-3);
    border-radius: var(--border-radius-small);

    &__toolbar {
      display: flex;
      gap: 8px;
      padding: 8px;
      background: var(--color-fill-1);
      border-bottom: 1px solid var(--color-neutral-3);
    }

    &__content {
      min-height: 220px;
      max-height: 420px;
      padding: 12px;
      overflow-y: auto;
      line-height: 1.6;
      outline: none;

      :deep(img),
      :deep(video) {
        max-width: 100%;
        height: auto;
      }
    }
  }
</style>
