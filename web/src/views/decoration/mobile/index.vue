<template>
  <div class="container">
    <Breadcrumb :items="['装修管理', '移动端装修']" />
    <div v-loading="loading">
      <el-card class="general-card">
        <el-tabs
          v-model="activeType"
          @tab-change="loadPageByType(Number($event))"
        >
          <el-tab-pane
            v-for="item in pageTypes"
            :key="item.type"
            :name="item.type"
            :label="item.label"
          />
        </el-tabs>

        <el-empty v-if="!page.id" description="暂无可编辑的装修页面" />
        <template v-else>
          <el-alert
            v-if="activeType === 5"
            type="info"
            :closable="false"
            style="margin-bottom: 16px"
          >
            预设主题的颜色由主题编号决定；需要自定义颜色时选择编号 7。
          </el-alert>

          <el-form
            v-if="activeType === 5"
            :model="theme"
            label-position="top"
            class="form-width"
          >
            <el-form-item label="主题编号">
              <el-select v-model="theme.themeColorId"
                ><el-option
                  v-for="item in themeOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
              /></el-select>
            </el-form-item>
            <el-form-item label="主题色 1"
              ><el-input v-model="theme.themeColor1" placeholder="#RRGGBB"
            /></el-form-item>
            <el-form-item label="主题色 2"
              ><el-input v-model="theme.themeColor2" placeholder="#RRGGBB"
            /></el-form-item>
            <el-form-item label="导航栏颜色"
              ><el-input
                v-model="theme.navigationBarColor"
                placeholder="#RRGGBB"
            /></el-form-item>
            <el-form-item label="顶部文字颜色">
              <el-select v-model="theme.topTextColor"
                ><el-option
                  v-for="item in textColorOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
              /></el-select>
            </el-form-item>
            <el-form-item label="按钮文字颜色">
              <el-select v-model="theme.buttonColor"
                ><el-option
                  v-for="item in textColorOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
              /></el-select>
            </el-form-item>
          </el-form>

          <template v-else>
            <el-alert type="info" :closable="false" style="margin-bottom: 16px">
              标准组件集合固定，只能调整顺序、启用状态和组件内容；固定组件不可隐藏或删除。
            </el-alert>
            <el-card v-if="hasMeta" class="component-card" header="页面设置">
              <el-form
                :model="metaContent"
                label-position="top"
                class="form-width"
              >
                <el-form-item label="页面标题"
                  ><el-input v-model="metaContent.title" :maxlength="8"
                /></el-form-item>
                <el-form-item label="背景颜色"
                  ><el-input
                    v-model="metaContent.bg_color"
                    placeholder="#RRGGBB"
                /></el-form-item>
                <el-form-item label="标题样式"
                  ><el-select v-model="metaContent.title_type"
                    ><el-option
                      v-for="item in binaryOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value" /></el-select
                ></el-form-item>
                <el-form-item label="背景样式"
                  ><el-select v-model="metaContent.bg_type"
                    ><el-option
                      v-for="item in binaryOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value" /></el-select
                ></el-form-item>
                <el-form-item label="文字颜色"
                  ><el-select v-model="metaContent.text_color"
                    ><el-option
                      v-for="item in binaryOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value" /></el-select
                ></el-form-item>
              </el-form>
            </el-card>
            <el-card
              v-for="(component, index) in components"
              :key="component.name"
              class="component-card"
            >
              <template #header
                ><div class="component-header"
                  ><span>{{ component.title }}</span>
                  <el-space>
                    <el-button
                      size="small"
                      :disabled="index === 0"
                      @click="moveComponent(index, -1)"
                    >
                      上移
                    </el-button>
                    <el-button
                      size="small"
                      :disabled="index === components.length - 1"
                      @click="moveComponent(index, 1)"
                    >
                      下移
                    </el-button>
                    <el-tag v-if="isFixed(component)" type="primary"
                      >固定</el-tag
                    >
                    <el-switch
                      v-else-if="hasEnabled(component)"
                      v-model="component.content.enabled"
                      :active-value="1"
                      :inactive-value="0"
                    /> </el-space
                ></div>
              </template>

              <el-form :model="content(component)" label-position="top">
                <template v-if="isMetaComponent(component)">
                  <el-form-item label="页面标题"
                    ><el-input v-model="metaContent.title" :maxlength="8"
                  /></el-form-item>
                  <el-form-item label="背景颜色"
                    ><el-input
                      v-model="metaContent.bg_color"
                      placeholder="#RRGGBB"
                  /></el-form-item>
                  <el-form-item label="标题样式"
                    ><el-select v-model="metaContent.title_type"
                      ><el-option
                        v-for="item in binaryOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value" /></el-select
                  ></el-form-item>
                  <el-form-item label="背景样式"
                    ><el-select v-model="metaContent.bg_type"
                      ><el-option
                        v-for="item in binaryOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value" /></el-select
                  ></el-form-item>
                  <el-form-item label="文字颜色"
                    ><el-select v-model="metaContent.text_color"
                      ><el-option
                        v-for="item in binaryOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value" /></el-select
                  ></el-form-item>
                </template>

                <template v-else-if="component.name === 'customer-service'">
                  <el-form-item label="客服标题"
                    ><el-input
                      v-model="content(component).title"
                      :maxlength="20"
                  /></el-form-item>
                  <el-form-item label="服务时间"
                    ><el-input
                      v-model="content(component).time"
                      :maxlength="20"
                  /></el-form-item>
                  <el-form-item label="联系电话"
                    ><el-input
                      v-model="content(component).mobile"
                      :maxlength="20"
                  /></el-form-item>
                  <el-form-item label="客服二维码">
                    <div class="image-field">
                      <img
                        v-if="content(component).qrcode"
                        :src="String(content(component).qrcode)"
                        alt="客服二维码"
                      />
                      <FilePicker
                        :type="10"
                        :limit="1"
                        button-text="选择二维码"
                        @select="
                          (urls) => setContentImage(component, 'qrcode', urls)
                        "
                      />
                    </div>
                  </el-form-item>
                  <el-form-item label="说明"
                    ><el-input
                      v-model="content(component).remark"
                      type="textarea"
                      :maxlength="20"
                  /></el-form-item>
                </template>

                <template v-else-if="component.name === 'my-service'">
                  <el-form-item label="服务标题"
                    ><el-input
                      v-model="content(component).title"
                      :maxlength="20"
                  /></el-form-item>
                  <el-form-item label="服务样式"
                    ><el-select v-model="content(component).style"
                      ><el-option
                        v-for="item in styleOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value" /></el-select
                  ></el-form-item>
                  <ItemEditor
                    :items="items(component)"
                    :show-enabled="true"
                    @image="(item, urls) => setItemImage(item, urls)"
                  />
                </template>

                <template
                  v-else-if="
                    ['banner', 'middle-banner', 'user-banner', 'nav'].includes(
                      component.name
                    )
                  "
                >
                  <el-form-item
                    v-if="
                      component.name === 'banner' || component.name === 'nav'
                    "
                    label="样式"
                  >
                    <el-select v-model="content(component).style"
                      ><el-option
                        v-for="item in styleOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                    /></el-select>
                  </el-form-item>
                  <el-form-item v-if="component.name === 'nav'" label="每行数量"
                    ><el-input-number
                      v-model="content(component).per_line"
                      :min="1"
                      :max="5"
                  /></el-form-item>
                  <el-form-item v-if="component.name === 'nav'" label="显示行数"
                    ><el-input-number
                      v-model="content(component).show_line"
                      :min="1"
                      :max="2"
                  /></el-form-item>
                  <ItemEditor
                    :items="items(component)"
                    :show-enabled="component.name !== 'banner'"
                    @image="(item, urls) => setItemImage(item, urls)"
                  />
                </template>
              </el-form>
            </el-card>
          </template>

          <el-space class="actions">
            <el-button
              v-permission="['decoration/mobile/page/save']"
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
              >保存</el-button
            >
            <el-button @click="loadPageByType(activeType)">重置</el-button>
          </el-space>
        </template>
      </el-card>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import { computed, defineComponent, reactive, ref, type PropType } from 'vue';
  import { ElMessage } from 'element-plus';
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
          <el-input v-model="item.name" placeholder="名称" />
              <el-select v-model="item.link.target_type" style="width: 130px" @change="ensureQuery(item)">
            <el-option label="站内页面" value="shop" />
            <el-option label="文章" value="article" />
            <el-option label="自定义链接" value="custom" />
            <el-option label="小程序" value="mini_program" />
              </el-select>
              <el-input v-model="item.link.target" placeholder="目标" />
              <template v-if="item.link.target_type === 'mini_program' && item.link.query">
                <el-input v-model="item.link.query.app_id" placeholder="小程序 AppID" />
                <el-select v-model="item.link.query.env_version" style="width: 130px">
                  <el-option label="开发版" value="develop" />
                  <el-option label="体验版" value="trial" />
                  <el-option label="正式版" value="release" />
                </el-select>
              </template>
          <el-switch v-if="showEnabled" v-model="item.is_show" :active-value="1" :inactive-value="0" />
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
  const themeOptions = [1, 2, 3, 4, 5, 6, 7].map((value) => ({
    label: `主题 ${value}`,
    value,
  }));
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
  const page = reactive<DecorationPage>({
    id: 0,
    type: 1,
    name: '',
    data: [],
    meta: [],
  });
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
    const meta = list.find(
      (item) => (item as DecorationComponent).name === 'page-meta'
    ) as MutableComponent | undefined;
    if (meta) return meta.content;
    return {};
  });
  const hasMeta = computed(
    () =>
      Array.isArray(page.meta) &&
      page.meta.some(
        (item) => (item as DecorationComponent).name === 'page-meta'
      )
  );

  const content = (component: MutableComponent) => component.content;
  const items = (component: MutableComponent) =>
    Array.isArray(component.content.data) ? component.content.data : [];
  const isFixed = (component: MutableComponent) =>
    ['search', 'news', 'user-info'].includes(component.name);
  const isMetaComponent = (component: MutableComponent) =>
    component.name === 'page-meta';
  const hasEnabled = (component: MutableComponent) =>
    ['banner', 'middle-banner', 'user-banner', 'nav'].includes(component.name);
  const ensureQueries = () => {
    components.value.forEach((component) => {
      items(component).forEach((item) => {
        if (item.link.target_type === 'mini_program' && !item.link.query)
          item.link.query = {};
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
      if (type === 5 && !Array.isArray(data.data))
        Object.assign(theme, data.data);
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

  const setContentImage = (
    component: MutableComponent,
    key: string,
    urls: string[]
  ) => {
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
      await saveMobileDecoration({
        id: page.id,
        type: activeType.value,
        data,
        meta: activeType.value === 5 ? [] : page.meta,
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
  .component-card {
    margin: 14px 0;
  }
  .actions {
    margin-top: 18px;
  }
  .image-field {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .image-field img {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 4px;
  }
  .item-editor {
    display: grid;
    gap: 10px;
  }
  .item-row {
    display: grid;
    grid-template-columns: 100px 72px minmax(120px, 1fr) 130px minmax(
        120px,
        1fr
      ) auto;
    align-items: center;
    gap: 8px;
  }
  .item-row img {
    width: 68px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
  }
  .component-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
</style>
