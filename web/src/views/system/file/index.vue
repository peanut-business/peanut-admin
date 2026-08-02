<template>
  <div class="container">
    <Breadcrumb :items="['menu.system', 'menu.system.file']" />
    <a-card class="general-card" :title="$t('menu.system.file')">
      <a-tabs
        v-model:active-key="activeType"
        type="rounded"
        @change="onTypeChange"
      >
        <a-tab-pane :key="10" :title="$t('systemFile.tab.image')" />
        <a-tab-pane :key="20" :title="$t('systemFile.tab.video')" />
        <a-tab-pane :key="30" :title="$t('systemFile.tab.file')" />
      </a-tabs>

      <a-row :gutter="16" class="body">
        <!-- 左侧分类 -->
        <a-col :flex="'220px'">
          <div class="cate-panel">
            <div class="cate-head">
              <span>{{ $t('systemFile.cate.title') }}</span>
              <a-button
                v-permission="['file/cate/add']"
                type="text"
                size="mini"
                @click="handleCateAdd()"
              >
                <template #icon><icon-plus /></template>
              </a-button>
            </div>
            <ul class="cate-list">
              <li
                :class="{ active: currentCid === '' }"
                @click="selectCate('')"
              >
                <span class="cate-name">{{ $t('systemFile.cate.all') }}</span>
              </li>
              <li
                v-for="c in flatCateList"
                :key="c.id"
                :class="{ active: currentCid === c.id }"
                @click="selectCate(c.id)"
              >
                <span class="cate-name">
                  {{ `${'  '.repeat(c.depth)}${c.name}` }}
                </span>
                <span class="cate-ops" @click.stop>
                  <span v-permission="['file/cate/add']">
                    <icon-plus @click="handleCateAdd(c.id)" />
                  </span>
                  <span v-permission="['file/cate/edit']">
                    <icon-edit @click="handleCateEdit(c)" />
                  </span>
                  <a-popconfirm
                    v-permission="['file/cate/delete']"
                    :content="$t('systemFile.cate.delete.confirm')"
                    @ok="handleCateDelete(c)"
                  >
                    <icon-delete />
                  </a-popconfirm>
                </span>
              </li>
            </ul>
          </div>
        </a-col>
        <!-- 右侧文件区 -->
        <a-col :flex="1">
          <div class="toolbar">
            <a-space>
              <a-upload
                v-permission="[uploadPermission]"
                :action="uploadUrl[activeType]"
                :headers="uploadHeaders"
                :data="{ cid: String(currentCid === '' ? 0 : currentCid) }"
                :show-file-list="false"
                :accept="acceptMap[activeType]"
                @success="onUploadSuccess"
                @error="onUploadError"
              >
                <template #upload-button>
                  <a-button type="primary">
                    <template #icon><icon-upload /></template>
                    {{ $t('systemFile.op.upload') }}
                  </a-button>
                </template>
              </a-upload>
              <a-input
                v-model="searchName"
                allow-clear
                style="width: 200px"
                :placeholder="$t('systemFile.search.placeholder')"
                @press-enter="() => fetchFiles(1)"
                @clear="() => fetchFiles(1)"
              />
              <a-select
                v-model="searchSource"
                allow-clear
                style="width: 140px"
                :placeholder="$t('systemFile.search.source')"
                @change="() => fetchFiles(1)"
                @clear="() => fetchFiles(1)"
              >
                <a-option :value="0">
                  {{ $t('systemFile.source.admin') }}
                </a-option>
                <a-option :value="1">
                  {{ $t('systemFile.source.user') }}
                </a-option>
              </a-select>
              <a-button @click="() => fetchFiles(1)">
                <template #icon><icon-search /></template>
              </a-button>
            </a-space>
            <a-space v-if="checkedIds.length">
              <span class="selected-tip">
                {{ $t('systemFile.op.selected', { n: checkedIds.length }) }}
              </span>
              <a-button
                v-permission="['file/move']"
                size="small"
                @click="openMove"
              >
                {{ $t('systemFile.op.move') }}
              </a-button>
              <a-popconfirm
                v-permission="['file/delete']"
                :content="$t('systemFile.op.batchDelete.confirm')"
                @ok="handleBatchDelete"
              >
                <a-button size="small" status="danger">
                  {{ $t('systemFile.op.delete') }}
                </a-button>
              </a-popconfirm>
            </a-space>
          </div>

          <a-spin :loading="loading" style="width: 100%">
            <div v-if="renderData.length" class="grid">
              <div
                v-for="item in renderData"
                :key="item.id"
                class="grid-item"
                :class="{ checked: checkedIds.includes(item.id) }"
              >
                <div class="thumb" @click="toggleCheck(item.id)">
                  <a-checkbox
                    class="thumb-check"
                    :model-value="checkedIds.includes(item.id)"
                    @click.stop
                    @change="() => toggleCheck(item.id)"
                  />
                  <img
                    v-if="activeType === 10"
                    :src="item.url"
                    :alt="item.name"
                  />
                  <div v-else class="thumb-icon">
                    <icon-play-circle v-if="activeType === 20" :size="40" />
                    <icon-file v-else :size="40" />
                  </div>
                </div>
                <div class="name" :title="item.name">{{ item.name }}</div>
                <div class="ops">
                  <a-button type="text" size="mini" @click="previewFile(item)">
                    {{ $t('systemFile.op.preview') }}
                  </a-button>
                  <a-button type="text" size="mini" @click="copyUrl(item)">
                    {{ $t('systemFile.op.copy') }}
                  </a-button>
                  <a-button
                    v-permission="['file/rename']"
                    type="text"
                    size="mini"
                    @click="handleRename(item)"
                  >
                    {{ $t('systemFile.op.rename') }}
                  </a-button>
                  <a-popconfirm
                    v-permission="['file/delete']"
                    :content="$t('systemFile.op.delete.confirm')"
                    @ok="handleDelete(item)"
                  >
                    <a-button type="text" size="mini" status="danger">
                      {{ $t('systemFile.op.delete') }}
                    </a-button>
                  </a-popconfirm>
                </div>
              </div>
            </div>
            <a-empty v-else />
          </a-spin>

          <div class="pager">
            <a-pagination
              :current="pagination.current"
              :page-size="pagination.pageSize"
              :total="pagination.total"
              show-total
              @change="fetchFiles"
            />
          </div>
        </a-col>
      </a-row>
    </a-card>
    <!-- 分类新增/编辑 -->
    <a-modal
      v-model:visible="cateModalVisible"
      :title="
        cateIsEdit
          ? $t('systemFile.cate.editTitle')
          : $t('systemFile.cate.addTitle')
      "
      :ok-loading="cateSubmitting"
      :mask-closable="false"
      @ok="submitCate"
      @cancel="cateModalVisible = false"
    >
      <a-form
        ref="cateFormRef"
        :model="cateForm"
        :rules="cateRules"
        layout="vertical"
      >
        <a-form-item field="name" :label="$t('systemFile.cate.field.name')">
          <a-input
            v-model="cateForm.name"
            :max-length="20"
            show-word-limit
            :placeholder="$t('systemFile.cate.field.name.placeholder')"
          />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- 文件重命名 -->
    <a-modal
      v-model:visible="renameModalVisible"
      :title="$t('systemFile.op.rename')"
      :ok-loading="renameSubmitting"
      :mask-closable="false"
      @ok="submitRename"
      @cancel="renameModalVisible = false"
    >
      <a-form
        ref="renameFormRef"
        :model="renameForm"
        :rules="renameRules"
        layout="vertical"
      >
        <a-form-item field="name" :label="$t('systemFile.rename.field')">
          <a-input
            v-model="renameForm.name"
            :placeholder="$t('systemFile.rename.placeholder')"
          />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- 移动分类 -->
    <a-modal
      v-model:visible="moveModalVisible"
      :title="$t('systemFile.op.move')"
      :ok-loading="moveSubmitting"
      :mask-closable="false"
      @ok="submitMove"
      @cancel="moveModalVisible = false"
    >
      <a-form :model="{ moveTarget }" layout="vertical">
        <a-form-item :label="$t('systemFile.move.target')">
          <a-select
            v-model="moveTarget"
            :placeholder="$t('systemFile.move.placeholder')"
          >
            <a-option :value="0">{{
              $t('systemFile.cate.uncategorized')
            }}</a-option>
            <a-option v-for="c in flatCateList" :key="c.id" :value="c.id">
              {{ `${'  '.repeat(c.depth)}${c.name}` }}
            </a-option>
          </a-select>
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import { getToken } from '@/utils/auth';
  import useLoading from '@/hooks/loading';
  import {
    getFileCateList,
    addFileCate,
    editFileCate,
    deleteFileCate,
    getFileList,
    moveFile,
    renameFile,
    deleteFile,
    uploadUrl,
    type FileType,
    type FileCateRecord,
    type FileRecord,
  } from '@/api/system/file';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(false);

  const activeType = ref<FileType>(10);
  const uploadHeaders = computed(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });
  const acceptMap: Record<FileType, string> = {
    10: 'image/*',
    20: 'video/*',
    30: '',
  };
  const uploadPermissionMap: Record<FileType, string> = {
    10: 'upload/image',
    20: 'upload/video',
    30: 'upload/file',
  };
  const uploadPermission = computed(
    () => uploadPermissionMap[activeType.value]
  );

  // ---- 分类 ----
  const cateList = ref<FileCateRecord[]>([]);
  const flatCateList = computed(() => {
    const result: Array<FileCateRecord & { depth: number }> = [];
    const walk = (items: FileCateRecord[], depth: number) => {
      items.forEach((item) => {
        result.push({ ...item, depth });
        if (item.children?.length) walk(item.children, depth + 1);
      });
    };
    walk(cateList.value, 0);
    return result;
  });
  const currentCid = ref<number | ''>('');

  const fetchCate = async () => {
    const { data } = await getFileCateList(activeType.value);
    cateList.value = data;
  };

  // ---- 文件 ----
  const renderData = ref<FileRecord[]>([]);
  const searchName = ref('');
  const searchSource = ref<number | ''>('');
  const checkedIds = ref<number[]>([]);
  const pagination = reactive({ current: 1, pageSize: 24, total: 0 });

  const fetchFiles = async (page = 1) => {
    setLoading(true);
    try {
      const { data } = await getFileList({
        type: activeType.value,
        cid: currentCid.value,
        name: searchName.value,
        source: searchSource.value,
        page_no: page,
        page_size: pagination.pageSize,
      });
      renderData.value = data.lists;
      pagination.current = data.pageNo;
      pagination.total = data.count;
      checkedIds.value = [];
    } finally {
      setLoading(false);
    }
  };

  const init = async () => {
    await fetchCate();
    await fetchFiles(1);
  };
  init();

  const onTypeChange = async () => {
    currentCid.value = '';
    searchName.value = '';
    searchSource.value = '';
    await fetchCate();
    await fetchFiles(1);
  };

  const selectCate = (cid: number | '') => {
    currentCid.value = cid;
    fetchFiles(1);
  };

  const toggleCheck = (id: number) => {
    const i = checkedIds.value.indexOf(id);
    if (i >= 0) checkedIds.value.splice(i, 1);
    else checkedIds.value.push(id);
  };
  // ---- 上传回调 ----
  const onUploadSuccess = (fileItem: any) => {
    const res = fileItem?.response;
    if (res && res.code !== 20000) {
      Message.error(res.msg || t('systemFile.tip.uploadFail'));
      return;
    }
    Message.success(t('systemFile.tip.uploadOk'));
    fetchFiles(pagination.current);
  };
  const onUploadError = () => {
    Message.error(t('systemFile.tip.uploadFail'));
  };

  // ---- 分类弹窗 ----
  const cateModalVisible = ref(false);
  const cateIsEdit = ref(false);
  const cateSubmitting = ref(false);
  const cateFormRef = ref<FormInstance>();
  const cateForm = reactive({ id: 0, pid: 0, name: '' });
  const cateRules = {
    name: [
      { required: true, message: t('systemFile.cate.field.name.required') },
      { maxLength: 20, message: t('systemFile.cate.field.name.max') },
    ],
  };

  const handleCateAdd = (pid = 0) => {
    cateIsEdit.value = false;
    cateForm.id = 0;
    cateForm.pid = pid;
    cateForm.name = '';
    cateModalVisible.value = true;
  };
  const handleCateEdit = (c: FileCateRecord) => {
    cateIsEdit.value = true;
    cateForm.id = c.id;
    cateForm.name = c.name;
    cateModalVisible.value = true;
  };
  const submitCate = async () => {
    const err = await cateFormRef.value?.validate();
    if (err) return;
    cateSubmitting.value = true;
    try {
      if (cateIsEdit.value) {
        await editFileCate({ id: cateForm.id, name: cateForm.name });
      } else {
        await addFileCate({
          type: activeType.value,
          pid: cateForm.pid,
          name: cateForm.name,
        });
      }
      Message.success(t('systemFile.tip.ok'));
      cateModalVisible.value = false;
      await fetchCate();
    } finally {
      cateSubmitting.value = false;
    }
  };
  const handleCateDelete = async (c: FileCateRecord) => {
    await deleteFileCate(c.id);
    Message.success(t('systemFile.tip.ok'));
    if (currentCid.value === c.id) currentCid.value = '';
    await fetchCate();
    await fetchFiles(1);
  };

  // ---- 重命名 ----
  const renameModalVisible = ref(false);
  const renameSubmitting = ref(false);
  const renameFormRef = ref<FormInstance>();
  const renameForm = reactive({ id: 0, name: '' });
  const renameRules = {
    name: [{ required: true, message: t('systemFile.rename.required') }],
  };
  const handleRename = (item: FileRecord) => {
    renameForm.id = item.id;
    renameForm.name = item.name;
    renameModalVisible.value = true;
  };
  const submitRename = async () => {
    const err = await renameFormRef.value?.validate();
    if (err) return;
    renameSubmitting.value = true;
    try {
      await renameFile(renameForm.id, renameForm.name);
      Message.success(t('systemFile.tip.ok'));
      renameModalVisible.value = false;
      await fetchFiles(pagination.current);
    } finally {
      renameSubmitting.value = false;
    }
  };

  // ---- 删除 ----
  const handleDelete = async (item: FileRecord) => {
    await deleteFile([item.id]);
    Message.success(t('systemFile.tip.ok'));
    await fetchFiles(pagination.current);
  };
  const handleBatchDelete = async () => {
    await deleteFile([...checkedIds.value]);
    Message.success(t('systemFile.tip.ok'));
    await fetchFiles(pagination.current);
  };

  const previewFile = (item: FileRecord) => {
    window.open(item.url, '_blank', 'noopener,noreferrer');
  };
  const copyUrl = async (item: FileRecord) => {
    await navigator.clipboard.writeText(item.url);
    Message.success(t('systemFile.tip.copied'));
  };

  // ---- 移动 ----
  const moveModalVisible = ref(false);
  const moveSubmitting = ref(false);
  const moveTarget = ref<number>(0);
  const openMove = () => {
    moveTarget.value = 0;
    moveModalVisible.value = true;
  };
  const submitMove = async () => {
    moveSubmitting.value = true;
    try {
      await moveFile([...checkedIds.value], moveTarget.value);
      Message.success(t('systemFile.tip.ok'));
      moveModalVisible.value = false;
      await fetchFiles(pagination.current);
    } finally {
      moveSubmitting.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'SystemFile',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .body {
    margin-top: 8px;
  }

  .cate-panel {
    border: 1px solid var(--color-neutral-3);
    border-radius: 4px;
    overflow: hidden;
  }

  .cate-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    font-weight: 500;
    border-bottom: 1px solid var(--color-neutral-3);
  }

  .cate-list {
    margin: 0;
    padding: 4px 0;
    list-style: none;
    max-height: 520px;
    overflow-y: auto;

    li {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 12px;
      cursor: pointer;

      &:hover {
        background: var(--color-fill-2);
      }

      &.active {
        background: var(--color-primary-light-1);
        color: rgb(var(--primary-6));
      }
    }

    .cate-name {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .cate-ops {
      display: none;
      gap: 8px;

      :deep(svg) {
        cursor: pointer;
      }
    }

    li:hover .cate-ops {
      display: flex;
    }
  }

  .toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  .selected-tip {
    color: var(--color-text-3);
    font-size: 12px;
  }

  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 16px;
  }

  .grid-item {
    border: 1px solid var(--color-neutral-3);
    border-radius: 4px;
    overflow: hidden;

    &.checked {
      border-color: rgb(var(--primary-6));
    }
  }

  .thumb {
    position: relative;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-fill-2);
    cursor: pointer;

    img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
  }

  .thumb-check {
    position: absolute;
    top: 6px;
    left: 6px;
  }

  .thumb-icon {
    color: var(--color-text-3);
  }

  .name {
    padding: 6px 8px;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .ops {
    display: flex;
    justify-content: space-between;
    padding: 0 4px 4px;
    border-top: 1px solid var(--color-neutral-3);
  }

  .pager {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
  }
</style>
