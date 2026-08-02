<template>
  <div class="container">
    <Breadcrumb :items="['装修管理', '移动端装修']" />
    <a-spin :loading="loading" style="width: 100%">
      <a-card class="general-card">
        <a-tabs v-model:active-key="activeType" @change="loadPageByType(Number($event))">
          <a-tab-pane v-for="item in pageTypes" :key="item.type" :title="item.label" />
        </a-tabs>

        <a-empty v-if="!page.id" description="暂无可编辑的装修页面" />
        <template v-else>
          <a-alert v-if="activeType === 5" type="info" style="margin-bottom: 16px">
            预设主题的颜色由主题编号决定；需要自定义颜色时选择编号 7。
          </a-alert>

          <a-form v-if="activeType === 5" :model="theme" layout="vertical" class="form-width">
            <a-form-item label="主题编号">
              <a-select v-model="theme.themeColorId" :options="themeOptions" />
            </a-form-item>
            <a-form-item label="主题色 1">
              <a-input v-model="theme.themeColor1" placeholder="#RRGGBB" />
            </a-form-item>
            <a-form-item label="主题色 2">
              <a-input v-model="theme.themeColor2" placeholder="#RRGGBB" />
            </a-form-item>
            <a-form-item label="导航栏颜色">
              <a-input v-model="theme.navigationBarColor" placeholder="#RRGGBB" />
            </a-form-item>
            <a-form-item label="顶部文字颜色">
              <a-select v-model="theme.topTextColor" :options="textColorOptions" />
            </a-form-item>
            <a-form-item label="按钮文字颜色">
              <a-select v-model="theme.buttonColor" :options="textColorOptions" />
            </a-form-item>
          </a-form>

          <template v-else>
            <a-alert type="info" style="margin-bottom: 16px">
              标准组件集合固定，只能调整顺序、启用状态和组件内容；固定组件不可隐藏或删除。
            </a-alert>
            <a-card v-if="hasMeta" class="component-card" title="页面设置">
              <a-form :model="metaContent" layout="vertical" class="form-width">
                <a-form-item label="页面标题"><a-input v-model="metaContent.title" :max-length="8" /></a-form-item>
                <a-form-item label="背景颜色"><a-input v-model="metaContent.bg_color" placeholder="#RRGGBB" /></a-form-item>
                <a-form-item label="标题样式"><a-select v-model="metaContent.title_type" :options="binaryOptions" /></a-form-item>
                <a-form-item label="背景样式"><a-select v-model="metaContent.bg_type" :options="binaryOptions" /></a-form-item>
                <a-form-item label="文字颜色"><a-select v-model="metaContent.text_color" :options="binaryOptions" /></a-form-item>
              </a-form>
            </a-card>
            <a-card
              v-for="(component, index) in components"
              :key="component.name"
              class="component-card"
              :title="component.title"
            >
              <template #extra>
                <a-space>
                  <a-button size="mini" :disabled="index === 0" @click="moveComponent(index, -1)">
                    上移
                  </a-button>
                  <a-button size="mini" :disabled="index === components.length - 1" @click="moveComponent(index, 1)">
                    下移
                  </a-button>
                  <a-tag v-if="isFixed(component)" color="arcoblue">固定</a-tag>
                  <a-switch
                    v-else-if="hasEnabled(component)"
                    v-model="component.content.enabled"
                    :checked-value="1"
                    :unchecked-value="0"
                  />
                </a-space>
              </template>

              <a-form :model="content(component)" layout="vertical">
                <template v-if="isMetaComponent(component)">
                  <a-form-item label="页面标题">
                    <a-input v-model="metaContent.title" :max-length="8" />
                  </a-form-item>
                  <a-form-item label="背景颜色">
                    <a-input v-model="metaContent.bg_color" placeholder="#RRGGBB" />
                  </a-form-item>
                  <a-form-item label="标题样式">
                    <a-select v-model="metaContent.title_type" :options="binaryOptions" />
                  </a-form-item>
                  <a-form-item label="背景样式">
                    <a-select v-model="metaContent.bg_type" :options="binaryOptions" />
                  </a-form-item>
                  <a-form-item label="文字颜色">
                    <a-select v-model="metaContent.text_color" :options="binaryOptions" />
                  </a-form-item>
                </template>

                <template v-else-if="component.name === 'customer-service'">
                  <a-form-item label="客服标题"><a-input v-model="content(component).title" :max-length="20" /></a-form-item>
                  <a-form-item label="服务时间"><a-input v-model="content(component).time" :max-length="20" /></a-form-item>
                  <a-form-item label="联系电话"><a-input v-model="content(component).mobile" :max-length="20" /></a-form-item>
                  <a-form-item label="客服二维码">
                    <div class="image-field">
                      <img v-if="content(component).qrcode" :src="String(content(component).qrcode)" alt="客服二维码" />
                      <FilePicker :type="10" :limit="1" button-text="选择二维码" @select="(urls) => setContentImage(component, 'qrcode', urls)" />
                    </div>
                  </a-form-item>
                  <a-form-item label="说明"><a-textarea v-model="content(component).remark" :max-length="20" /></a-form-item>
                </template>

                <template v-else-if="component.name === 'my-service'">
                  <a-form-item label="服务标题"><a-input v-model="content(component).title" :max-length="20" /></a-form-item>
                  <a-form-item label="服务样式"><a-select v-model="content(component).style" :options="styleOptions" /></a-form-item>
                  <ItemEditor :items="items(component)" :show-enabled="true" @image="(item, urls) => setItemImage(item, urls)" />
                </template>

                <template v-else-if="['banner', 'middle-banner', 'user-banner', 'nav'].includes(component.name)">
                  <a-form-item v-if="component.name === 'banner' || component.name === 'nav'" label="样式">
                    <a-select v-model="content(component).style" :options="styleOptions" />
                  </a-form-item>
                  <a-form-item v-if="component.name === 'nav'" label="每行数量">
                    <a-input-number v-model="content(component).per_line" :min="1" :max="5" />
                  </a-form-item>
                  <a-form-item v-if="component.name === 'nav'" label="显示行数">
                    <a-input-number v-model="content(component).show_line" :min="1" :max="2" />
                  </a-form-item>
                  <ItemEditor :items="items(component)" :show-enabled="component.name !== 'banner'" @image="(item, urls) => setItemImage(item, urls)" />
                </template>
              </a-form>
            </a-card>
          </template>

          <a-space class="actions">
            <a-button
              v-permission="['decoration/mobile/page/save']"
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
            >保存</a-button>
            <a-button @click="loadPageByType(activeType)">重置</a-button>
          </a-space>
        </template>
      </a-card>
    </a-spin>
  </div>
</template>

<script lang="ts" setup>
  import { computed, defineComponent, reactive, ref, type PropType } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import FilePicker from '@/components/file-picker/index.vue';
  import {
    getMobileDecorationLists,
    getMobileDecorationDetail,
    saveMobileDecoration,
    type DecorationComponent,
    type DecorationItem,
    type DecorationPage,
  } from '@/api/decoration';

  interface MutableContent {
    [key: string]: unknown;
    title?: string;
    bg_color?: string;
    bg_image?: string;
    title_type?: number;
    bg_type?: number;
    text_color?: number;
    time?: string;
    mobile?: string;
    qrcode?: string;
    remark?: string;
    style?: number;
    per_line?: number;
    show_line?: number;
    enabled?: number;
    data?: DecorationItem[];
  }
  interface MutableComponent extends Omit<DecorationComponent, 'content'> {
    content: MutableContent;
  }
  interface ThemeValue {
    themeColorId: number;
    topTextColor: 'white' | 'black';
    navigationBarColor: string;
    themeColor1: string;
    themeColor2: string;
    buttonColor: 'white' | 'black';
  }

  const ItemEditor = defineComponent({
    components: { FilePicker },
    props: {
      items: { type: Array as PropType<DecorationItem[]>, required: true },
      showEnabled: { type: Boolean, default: false },
    },
    emits: ['image'],
    template: `
      <div class="item-editor">
        <div v-for="item in items" :key="item.name + String(item.image)" class="item-row">
          <FilePicker :type="10" :limit="1" button-text="选择图片" @select="(urls) => $emit('image', item, urls)" />
          <img v-if="item.image" :src="item.image" alt="" />
          <a-input v-model="item.name" placeholder="名称" />
              <a-select v-model="item.link.target_type" style="width: 130px" @change="ensureQuery(item)">
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
          <a-switch v-if="showEnabled" v-model="item.is_show" :checked-value="1" :unchecked-value="0" />
        </div>
      </div>
        `,
    methods: {
      ensureQuery(item: DecorationItem) {
        item.link.query ||= {};
      },
    },
  });

  const pageTypes = [
    { type: 1, label: '移动首页' },
    { type: 2, label: '个人中心' },
    { type: 3, label: '客服页面' },
    { type: 5, label: '系统风格' },
  ];
  const themeOptions = [1, 2, 3, 4, 5, 6, 7].map((value) => ({ label: `主题 ${value}`, value }));
  const textColorOptions = [
    { label: '白色', value: 'white' },
    { label: '黑色', value: 'black' },
  ];
  const binaryOptions = [
    { label: '样式 1', value: 1 },
    { label: '样式 2', value: 2 },
  ];
  const styleOptions = binaryOptions;
  const loading = ref(false);
  const submitLoading = ref(false);
  const activeType = ref(1);
  const page = reactive<DecorationPage>({ id: 0, type: 1, name: '', data: [], meta: [] });
  const theme = reactive<ThemeValue>({
    themeColorId: 3,
    topTextColor: 'white',
    navigationBarColor: '#A74BFD',
    themeColor1: '#A74BFD',
    themeColor2: '#CB60FF',
    buttonColor: 'white',
  });

  const components = computed<MutableComponent[]>(() =>
    Array.isArray(page.data) ? (page.data as unknown as MutableComponent[]) : []
  );
  const metaContent = computed<MutableContent>(() => {
    const list = Array.isArray(page.meta) ? page.meta : [];
    const meta = list.find((item) => (item as DecorationComponent).name === 'page-meta') as MutableComponent | undefined;
    if (meta) return meta.content;
    return {};
  });
  const hasMeta = computed(() =>
    Array.isArray(page.meta) && page.meta.some((item) => (item as DecorationComponent).name === 'page-meta')
  );

  const content = (component: MutableComponent) => component.content;
  const items = (component: MutableComponent) =>
    Array.isArray(component.content.data) ? component.content.data : [];
  const isFixed = (component: MutableComponent) => ['search', 'news', 'user-info'].includes(component.name);
  const isMetaComponent = (component: MutableComponent) => component.name === 'page-meta';
  const hasEnabled = (component: MutableComponent) =>
    ['banner', 'middle-banner', 'user-banner', 'nav'].includes(component.name);
  const ensureQueries = () => {
    components.value.forEach((component) => {
      items(component).forEach((item) => {
        if (item.link.target_type === 'mini_program' && !item.link.query) item.link.query = {};
      });
    });
  };

  const loadPageByType = async (type: number) => {
    activeType.value = type;
    loading.value = true;
    try {
      const { data: list } = await getMobileDecorationLists();
      const summary = list.find((item) => item.type === type) || list[0];
      if (!summary) {
        page.id = 0;
        return;
      }
      const { data } = await getMobileDecorationDetail(summary.id);
      Object.assign(page, data);
      ensureQueries();
      if (type === 5 && !Array.isArray(data.data)) Object.assign(theme, data.data);
    } finally {
      loading.value = false;
    }
  };
  loadPageByType(activeType.value);

  const moveComponent = (index: number, offset: number) => {
    const list = components.value;
    const next = index + offset;
    if (next < 0 || next >= list.length) return;
    [list[index], list[next]] = [list[next], list[index]];
  };

  const setContentImage = (component: MutableComponent, key: string, urls: string[]) => {
    component.content[key] = urls[0] || '';
  };
  const setItemImage = (item: DecorationItem, urls: string[]) => {
    item.image = urls[0] || '';
  };

  const handleSubmit = async () => {
    if (!page.id) return;
    submitLoading.value = true;
    try {
      const data = activeType.value === 5 ? { ...theme } : page.data;
      await saveMobileDecoration({ id: page.id, type: activeType.value, data, meta: activeType.value === 5 ? [] : page.meta });
      Message.success('保存成功');
    } finally {
      submitLoading.value = false;
    }
  };
</script>


<style scoped lang="less">
  .container { padding: 0 20px 20px; }
  .form-width { max-width: 560px; }
  .component-card { margin: 14px 0; }
  .actions { margin-top: 18px; }
  .image-field { display: flex; align-items: center; gap: 12px; }
  .image-field img { width: 72px; height: 72px; object-fit: cover; border-radius: 4px; }
  .item-editor { display: grid; gap: 10px; }
  .item-row { display: grid; grid-template-columns: 100px 72px minmax(120px, 1fr) 130px minmax(120px, 1fr) auto; align-items: center; gap: 8px; }
  .item-row img { width: 68px; height: 50px; object-fit: cover; border-radius: 4px; }
</style>
