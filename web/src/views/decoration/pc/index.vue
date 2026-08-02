<template>
  <div class="container">
    <Breadcrumb :items="['装修管理', 'PC 装修']" />
    <a-spin :loading="loading" style="width: 100%">
      <a-card class="general-card" title="PC 首页 Banner">
        <a-alert type="info" style="margin-bottom: 16px">
          PC 首页只有一个标准 Banner 组件；可调整启用状态、条目内容和展示样式，保存后客户端即时读取。
        </a-alert>
        <a-empty v-if="!page.id || !banner" description="暂无 PC Banner 装修页面" />
        <template v-else>
          <a-form :model="banner.content" layout="vertical" class="form-width">
            <a-form-item label="启用状态">
              <a-switch v-model="banner.content.enabled" :checked-value="1" :unchecked-value="0" />
            </a-form-item>
          </a-form>

          <a-divider>Banner 条目</a-divider>
          <a-card v-for="(item, index) in items" :key="`${index}-${item.name}-${item.image}`" class="item-card">
            <template #title>第 {{ index + 1 }} 项</template>
            <template #extra>
              <a-button v-if="items.length > 1" status="danger" size="mini" @click="removeItem(index)">删除</a-button>
            </template>
            <a-form :model="item" layout="vertical">
              <a-form-item label="图片">
                <div class="image-field">
                  <img v-if="item.image" :src="item.image" alt="Banner" />
                  <FilePicker :type="10" :limit="1" button-text="选择图片" @select="(urls) => setImage(item, urls)" />
                </div>
              </a-form-item>
              <a-form-item label="名称"><a-input v-model="item.name" /></a-form-item>
              <a-form-item label="业务链接">
                <a-space fill wrap>
                  <a-select v-model="item.link.target_type" style="width: 150px" @change="ensureQuery(item)">
                    <a-option value="shop">站内页面</a-option>
                    <a-option value="article">文章</a-option>
                    <a-option value="custom">自定义链接</a-option>
                    <a-option value="mini_program">小程序</a-option>
                  </a-select>
                  <a-input v-model="item.link.target" placeholder="目标" />
                  <template v-if="item.link.target_type === 'mini_program' && item.link.query">
                    <a-input v-model="item.link.query.app_id" placeholder="小程序 AppID" />
                    <a-select v-model="item.link.query.env_version" style="width: 130px">
                      <a-option value="develop">开发版</a-option>
                      <a-option value="trial">体验版</a-option>
                      <a-option value="release">正式版</a-option>
                    </a-select>
                  </template>
                </a-space>
              </a-form-item>
            </a-form>
          </a-card>
          <a-button v-if="items.length < 10" @click="addItem">添加条目</a-button>

          <a-divider>展示样式</a-divider>
          <a-form :model="banner.styles" layout="inline">
            <a-form-item label="位置"><a-input v-model="banner.styles.position" /></a-form-item>
            <a-form-item label="左偏移"><a-input v-model="banner.styles.left" /></a-form-item>
            <a-form-item label="上偏移"><a-input v-model="banner.styles.top" /></a-form-item>
            <a-form-item label="宽度"><a-input v-model="banner.styles.width" /></a-form-item>
            <a-form-item label="高度"><a-input v-model="banner.styles.height" /></a-form-item>
          </a-form>
          <a-space class="actions">
            <a-button
              v-permission="['decoration/pc/page/save']"
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
            >保存</a-button>
            <a-button @click="load">重置</a-button>
          </a-space>
        </template>
      </a-card>
    </a-spin>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import FilePicker from '@/components/file-picker/index.vue';
  import {
    getPcDecorationLists,
    getPcDecorationDetail,
    savePcDecoration,
    type DecorationComponent,
    type DecorationItem,
    type DecorationPage,
  } from '@/api/decoration';

  interface MutableContent extends Record<string, unknown> {
    enabled?: number;
    data?: DecorationItem[];
  }
  interface MutableComponent extends Omit<DecorationComponent, 'content' | 'styles'> {
    content: MutableContent;
    styles: Record<string, string>;
  }

  const loading = ref(true);
  const submitLoading = ref(false);
  const page = reactive<DecorationPage>({ id: 0, type: 4, name: '', data: [], meta: [] });
  const banner = computed<MutableComponent | undefined>(() => {
    const list = Array.isArray(page.data) ? (page.data as unknown as MutableComponent[]) : [];
    return list.find((component) => component.name === 'pc-banner');
  });
  const items = computed<DecorationItem[]>(() => {
    const value = banner.value?.content.data;
    return Array.isArray(value) ? value : [];
  });

  const ensureQuery = (item: DecorationItem) => {
    item.link.query ||= {};
  };
  const normalizeItems = () => {
    items.value.forEach((item) => {
      if (item.link.target_type === 'mini_program' && !item.link.query) item.link.query = {};
    });
  };
  const load = async () => {
    loading.value = true;
    try {
      const { data: list } = await getPcDecorationLists();
      const summary = list[0];
      if (!summary) {
        page.id = 0;
        return;
      }
      const { data } = await getPcDecorationDetail(summary.id);
      Object.assign(page, data);
      normalizeItems();
    } finally {
      loading.value = false;
    }
  };
  load();

  const addItem = () => {
    if (!banner.value || items.value.length >= 10) return;
    items.value.push({ image: '', name: '', link: { target_type: 'shop', target: 'home' } });
  };
  const removeItem = (index: number) => {
    if (items.value.length <= 1) return;
    items.value.splice(index, 1);
  };
  const setImage = (item: DecorationItem, urls: string[]) => {
    item.image = urls[0] || '';
  };
  const handleSubmit = async () => {
    if (!page.id || !banner.value || items.value.length < 1 || items.value.length > 10) return;
    submitLoading.value = true;
    try {
      await savePcDecoration({ id: page.id, type: 4, data: page.data, meta: [] });
      Message.success('保存成功');
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<style scoped lang="less">
  .container { padding: 0 20px 20px; }
  .form-width { max-width: 560px; }
  .item-card { margin-bottom: 14px; }
  .image-field { display: flex; align-items: center; gap: 12px; }
  .image-field img { width: 180px; height: 90px; object-fit: cover; border-radius: 4px; }
  .actions { margin-top: 20px; }
</style>
