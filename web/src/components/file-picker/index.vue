<template>
  <span v-if="canBrowse" class="file-picker-trigger">
    <a-button :size="size" @click="open">
      <template #icon><icon-folder /></template>
      {{ buttonText }}
    </a-button>
  </span>

  <a-modal
    v-model:visible="visible"
    :title="title"
    width="860px"
    :ok-button-props="{ disabled: selected.length === 0 }"
    @ok="confirm"
    @cancel="close"
  >
    <a-space direction="vertical" fill>
      <a-space wrap>
        <a-select v-model="cid" style="width: 190px" @change="refresh">
          <a-option value="">全部分类</a-option>
          <a-option :value="0">未分组</a-option>
          <a-option
            v-for="item in flatCategories"
            :key="item.id"
            :value="item.id"
          >
            {{ `${'  '.repeat(item.depth)}${item.name}` }}
          </a-option>
        </a-select>
        <a-select v-model="source" style="width: 140px" @change="refresh">
          <a-option value="">全部来源</a-option>
          <a-option :value="0">后台上传</a-option>
          <a-option :value="1">用户上传</a-option>
        </a-select>
        <a-input-search
          v-model="name"
          allow-clear
          style="width: 220px"
          placeholder="搜索素材名称"
          @search="refresh"
          @clear="refresh"
        />
        <a-upload
          v-if="canUpload"
          :action="uploadUrl[type]"
          :headers="uploadHeaders"
          :data="{ cid: String(cid === '' ? 0 : cid) }"
          :accept="acceptMap[type]"
          :show-file-list="false"
          @success="onUploadSuccess"
          @error="onUploadError"
        >
          <template #upload-button>
            <a-button type="primary">
              <template #icon><icon-upload /></template>
              上传
            </a-button>
          </template>
        </a-upload>
      </a-space>

      <a-spin :loading="loading" style="width: 100%">
        <div v-if="files.length" class="picker-grid">
          <button
            v-for="item in files"
            :key="item.id"
            type="button"
            class="picker-item"
            :class="{ selected: isSelected(item.id) }"
            @click="toggle(item)"
          >
            <img v-if="type === 10" :src="item.url" :alt="item.name" />
            <span v-else class="file-icon">
              <icon-play-circle v-if="type === 20" :size="42" />
              <icon-file v-else :size="42" />
            </span>
            <span class="file-name" :title="item.name">{{ item.name }}</span>
            <icon-check-circle-fill v-if="isSelected(item.id)" class="check" />
          </button>
        </div>
        <a-empty v-else />
      </a-spin>

      <div class="picker-footer">
        <span>已选择 {{ selected.length }} / {{ effectiveLimit }}</span>
        <a-pagination
          :current="page.current"
          :page-size="page.pageSize"
          :total="page.total"
          @change="fetchFiles"
        />
      </div>
    </a-space>
  </a-modal>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import { getToken } from '@/utils/auth';
  import { hasPermission } from '@/hooks/permission';
  import {
    getFileCateList,
    getFileList,
    uploadUrl,
    type FileCateRecord,
    type FileRecord,
    type FileType,
  } from '@/api/system/file';

  const props = withDefaults(
    defineProps<{
      type: FileType;
      limit?: number;
      title?: string;
      buttonText?: string;
      size?: 'mini' | 'small' | 'medium' | 'large';
    }>(),
    {
      limit: 1,
      title: '选择素材',
      buttonText: '素材库',
      size: 'medium',
    }
  );

  const emit = defineEmits<{
    (event: 'open'): void;
    (event: 'select', urls: string[], files: FileRecord[]): void;
  }>();

  const visible = ref(false);
  const canBrowse = computed(
    () => hasPermission('file/lists') && hasPermission('file/cate/lists')
  );
  const uploadPermissionMap: Record<FileType, string> = {
    10: 'upload/image',
    20: 'upload/video',
    30: 'upload/file',
  };
  const canUpload = computed(() =>
    hasPermission(uploadPermissionMap[props.type])
  );
  const loading = ref(false);
  const categories = ref<FileCateRecord[]>([]);
  const files = ref<FileRecord[]>([]);
  const selected = ref<FileRecord[]>([]);
  const cid = ref<number | ''>('');
  const source = ref<number | ''>('');
  const name = ref('');
  const page = reactive({ current: 1, pageSize: 15, total: 0 });
  const effectiveLimit = computed(() => Math.max(1, props.limit));
  const acceptMap: Record<FileType, string> = {
    10: 'image/*',
    20: 'video/*',
    30: '.zip,.rar,.txt,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.7z,.gz',
  };
  const uploadHeaders = computed<Record<string, string>>(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });
  const flatCategories = computed(() => {
    const result: Array<FileCateRecord & { depth: number }> = [];
    const walk = (items: FileCateRecord[], depth: number) => {
      items.forEach((item) => {
        result.push({ ...item, depth });
        if (item.children?.length) walk(item.children, depth + 1);
      });
    };
    walk(categories.value, 0);
    return result;
  });

  const fetchCategories = async () => {
    const { data } = await getFileCateList(props.type);
    categories.value = data;
  };
  const fetchFiles = async (current = 1) => {
    loading.value = true;
    try {
      const { data } = await getFileList({
        type: props.type,
        cid: cid.value,
        source: source.value,
        name: name.value,
        page_no: current,
        page_size: page.pageSize,
      });
      files.value = data.lists;
      page.current = data.pageNo;
      page.total = data.count;
    } finally {
      loading.value = false;
    }
  };
  const refresh = () => fetchFiles(1);
  const open = async () => {
    emit('open');
    selected.value = [];
    cid.value = '';
    source.value = '';
    name.value = '';
    visible.value = true;
    await Promise.all([fetchCategories(), fetchFiles(1)]);
  };
  const close = () => {
    visible.value = false;
    selected.value = [];
  };
  const isSelected = (id: number) =>
    selected.value.some((item) => item.id === id);
  const toggle = (item: FileRecord) => {
    const index = selected.value.findIndex(
      (selectedItem) => selectedItem.id === item.id
    );
    if (index >= 0) {
      selected.value.splice(index, 1);
      return;
    }
    if (effectiveLimit.value === 1) {
      selected.value = [item];
      return;
    }
    if (selected.value.length >= effectiveLimit.value) {
      Message.warning(`最多选择 ${effectiveLimit.value} 个素材`);
      return;
    }
    selected.value.push(item);
  };
  const confirm = () => {
    emit(
      'select',
      selected.value.map((item) => item.url),
      [...selected.value]
    );
    close();
  };
  const onUploadSuccess = async (fileItem: any) => {
    const response = fileItem?.response;
    if (!response || response.code !== 20000) {
      Message.error(response?.msg || '上传失败');
      return;
    }
    Message.success('上传成功');
    await fetchFiles(1);
  };
  const onUploadError = () => Message.error('上传失败');
</script>

<style scoped lang="less">
  .file-picker-trigger {
    display: inline-flex;
  }
  .picker-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    min-height: 330px;
  }
  .picker-item {
    position: relative;
    display: flex;
    min-width: 0;
    height: 150px;
    padding: 8px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid var(--color-border-2);
    border-radius: 6px;
    background: var(--color-bg-2);
    cursor: pointer;
  }
  .picker-item.selected {
    border-color: rgb(var(--primary-6));
  }
  .picker-item img {
    width: 100%;
    height: 105px;
    object-fit: contain;
  }
  .file-icon {
    display: flex;
    height: 105px;
    align-items: center;
  }
  .file-name {
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .check {
    position: absolute;
    top: 7px;
    right: 7px;
    color: rgb(var(--primary-6));
  }
  .picker-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
</style>
