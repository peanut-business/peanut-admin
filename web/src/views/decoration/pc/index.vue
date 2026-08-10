<template>
  <div class="container">
    <Breadcrumb :items="['装修管理', 'PC 装修']" />
    <div v-loading="loading">
      <el-card class="general-card" header="PC 首页 Banner">
        <el-alert type="info" :closable="false" style="margin-bottom: 16px">
          PC 首页只有一个标准 Banner
          组件；可调整启用状态、条目内容和展示样式，保存后客户端即时读取。
        </el-alert>
        <el-empty
          v-if="!page.id || !banner"
          description="暂无 PC Banner 装修页面"
        />
        <template v-else>
          <el-form
            :model="banner.content"
            label-position="top"
            class="form-width"
          >
            <el-form-item label="启用状态">
              <el-switch
                v-model="banner.content.enabled"
                :active-value="1"
                :inactive-value="0"
              />
            </el-form-item>
          </el-form>

          <el-divider>Banner 条目</el-divider>
          <el-card
            v-for="(item, index) in items"
            :key="`${index}-${item.name}-${item.image}`"
            class="item-card"
          >
            <template #header>
              <div class="card-header"
                ><span>第 {{ index + 1 }} 项</span>
                <el-button
                  v-if="items.length > 1"
                  type="danger"
                  size="small"
                  @click="removeItem(index)"
                  >删除</el-button
                ></div
              >
            </template>
            <el-form :model="item" label-position="top">
              <el-form-item label="图片">
                <div class="image-field">
                  <img v-if="item.image" :src="item.image" alt="Banner" />
                  <FilePicker
                    :type="10"
                    :limit="1"
                    button-text="选择图片"
                    @select="(urls) => setImage(item, urls)"
                  />
                </div>
              </el-form-item>
              <el-form-item label="名称"
                ><el-input v-model="item.name"
              /></el-form-item>
              <el-form-item label="业务链接">
                <el-space fill wrap>
                  <el-select
                    v-model="item.link.target_type"
                    style="width: 150px"
                    @change="ensureQuery(item)"
                  >
                    <el-option label="站内页面" value="shop" />
                    <el-option label="文章" value="article" />
                    <el-option label="自定义链接" value="custom" />
                    <el-option label="小程序" value="mini_program" />
                  </el-select>
                  <el-input v-model="item.link.target" placeholder="目标" />
                  <template
                    v-if="
                      item.link.target_type === 'mini_program' &&
                      item.link.query
                    "
                  >
                    <el-input
                      v-model="item.link.query.app_id"
                      placeholder="小程序 AppID"
                    />
                    <el-select
                      v-model="item.link.query.env_version"
                      style="width: 130px"
                    >
                      <el-option label="开发版" value="develop" />
                      <el-option label="体验版" value="trial" />
                      <el-option label="正式版" value="release" />
                    </el-select>
                  </template>
                </el-space>
              </el-form-item>
            </el-form>
          </el-card>
          <el-button v-if="items.length < 10" @click="addItem"
            >添加条目</el-button
          >

          <el-divider>展示样式</el-divider>
          <el-form :model="banner.styles" inline>
            <el-form-item label="位置"
              ><el-input v-model="banner.styles.position"
            /></el-form-item>
            <el-form-item label="左偏移"
              ><el-input v-model="banner.styles.left"
            /></el-form-item>
            <el-form-item label="上偏移"
              ><el-input v-model="banner.styles.top"
            /></el-form-item>
            <el-form-item label="宽度"
              ><el-input v-model="banner.styles.width"
            /></el-form-item>
            <el-form-item label="高度"
              ><el-input v-model="banner.styles.height"
            /></el-form-item>
          </el-form>
          <el-space class="actions">
            <el-button
              v-permission="['decoration/pc/page/save']"
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
              >保存</el-button
            >
            <el-button @click="load">重置</el-button>
          </el-space>
        </template>
      </el-card>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { ElMessage } from 'element-plus';
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
  interface MutableComponent
    extends Omit<DecorationComponent, 'content' | 'styles'> {
    content: MutableContent;
    styles: Record<string, string>;
  }

  const loading = ref(true);
  const submitLoading = ref(false);
  const page = reactive<DecorationPage>({
    id: 0,
    type: 4,
    name: '',
    data: [],
    meta: [],
  });
  const banner = computed<MutableComponent | undefined>(() => {
    const list = Array.isArray(page.data)
      ? (page.data as unknown as MutableComponent[])
      : [];
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
      if (item.link.target_type === 'mini_program' && !item.link.query)
        item.link.query = {};
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
    items.value.push({
      image: '',
      name: '',
      link: { target_type: 'shop', target: 'home' },
    });
  };
  const removeItem = (index: number) => {
    if (items.value.length <= 1) return;
    items.value.splice(index, 1);
  };
  const setImage = (item: DecorationItem, urls: string[]) => {
    item.image = urls[0] || '';
  };
  const handleSubmit = async () => {
    if (
      !page.id ||
      !banner.value ||
      items.value.length < 1 ||
      items.value.length > 10
    )
      return;
    submitLoading.value = true;
    try {
      await savePcDecoration({
        id: page.id,
        type: 4,
        data: page.data,
        meta: [],
      });
      ElMessage.success('保存成功');
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px;
  }
  .form-width {
    max-width: 560px;
  }
  .item-card {
    margin-bottom: 14px;
  }
  .image-field {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .image-field img {
    width: 180px;
    height: 90px;
    object-fit: cover;
    border-radius: 4px;
  }
  .actions {
    margin-top: 20px;
  }
  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
</style>
