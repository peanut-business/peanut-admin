<template>
  <div class="container">
    <Breadcrumb :items="['装修管理', 'Tabbar 装修']" />
    <a-spin :loading="loading" style="width: 100%">
      <a-card class="general-card" title="Tabbar">
      <a-alert type="info" style="margin-bottom: 16px">
        首项固定显示并指向首页；其余项目可调整名称、图标、链接和显示状态。总项数为 2～5，至少保留 2 个可见项。
      </a-alert>
      <a-form :model="form.style" layout="vertical" class="style-form">
        <a-form-item label="默认颜色">
          <a-input v-model="form.style.default_color" placeholder="#666666" />
        </a-form-item>
        <a-form-item label="选中颜色">
          <a-input v-model="form.style.selected_color" placeholder="#2F80ED" />
        </a-form-item>
      </a-form>

      <a-divider>Tabbar 项</a-divider>
      <a-card v-for="(item, index) in form.list" :key="item.position ?? index" class="item-card">
        <template #title>第 {{ index + 1 }} 项{{ index === 0 ? '（固定首页）' : '' }}</template>
        <template #extra>
          <a-tag v-if="index === 0" color="arcoblue">固定</a-tag>
          <a-button v-else status="danger" size="mini" @click="removeItem(index)">删除</a-button>
        </template>
        <a-form :model="item" layout="vertical">
          <a-form-item label="名称">
            <a-input v-model="item.name" :max-length="20" />
          </a-form-item>
          <a-space wrap>
            <a-form-item label="未选中图标">
              <div class="icon-field">
                <img v-if="item.unselected" :src="item.unselected" alt="" />
                <FilePicker :type="10" :limit="1" button-text="选择图标" @select="(urls) => setImage(item, 'unselected', urls)" />
              </div>
            </a-form-item>
            <a-form-item label="选中图标">
              <div class="icon-field">
                <img v-if="item.selected" :src="item.selected" alt="" />
                <FilePicker :type="10" :limit="1" button-text="选择图标" @select="(urls) => setImage(item, 'selected', urls)" />
              </div>
            </a-form-item>
          </a-space>
          <a-form-item label="业务链接">
            <a-space fill>
              <a-select v-model="item.link.target_type" style="width: 150px" :disabled="index === 0" @change="ensureQuery(item)">
                <a-option value="shop">站内页面</a-option>
                <a-option value="article">文章</a-option>
                <a-option value="custom">自定义链接</a-option>
                <a-option value="mini_program">小程序</a-option>
              </a-select>
              <a-input v-model="item.link.target" placeholder="目标" :disabled="index === 0" />
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
          <a-form-item label="显示状态">
            <a-switch v-model="item.is_show" :checked-value="1" :unchecked-value="0" :disabled="index === 0" />
          </a-form-item>
        </a-form>
      </a-card>
      <a-button v-if="form.list.length < 5" @click="addItem">添加 Tabbar 项</a-button>
      <div class="actions">
        <a-button
          v-permission="['decoration/tabbar/save']"
          type="primary"
          :loading="submitLoading"
          @click="handleSubmit"
        >保存</a-button>
      </div>
      </a-card>
    </a-spin>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import FilePicker from '@/components/file-picker/index.vue';
  import {
    getDecorationTabbar,
    saveDecorationTabbar,
    type DecorationLink,
    type DecorationTabbar,
    type DecorationTabbarItem,
  } from '@/api/decoration';

  const emptyLink = (): DecorationLink => ({ target_type: 'shop', target: 'home' });
  const newItem = (position: number): DecorationTabbarItem => ({
    position,
    name: `菜单 ${position + 1}`,
    selected: '',
    unselected: '',
    link: position === 0 ? emptyLink() : { target_type: 'shop', target: 'news' },
    is_show: 1,
  });
  const form = reactive<DecorationTabbar>({
    style: { default_color: '#666666', selected_color: '#2F80ED' },
    list: [newItem(0), newItem(1)],
  });
  const loading = ref(true);
  const submitLoading = ref(false);

  const load = async () => {
    loading.value = true;
    try {
      const { data } = await getDecorationTabbar();
      Object.assign(form.style, data.style);
      form.list.splice(0, form.list.length, ...data.list.map((item, index) => {
        if (item.link.target_type === 'mini_program' && !item.link.query) item.link.query = {};
        return { ...item, position: index };
      }));
    } finally {
      loading.value = false;
    }
  };
  load();

  const addItem = () => {
    if (form.list.length >= 5) return;
    form.list.push(newItem(form.list.length));
  };
  const removeItem = (index: number) => {
    if (index === 0 || form.list.length <= 2) return;
    form.list.splice(index, 1);
    form.list.forEach((item, position) => { item.position = position; });
  };
  const setImage = (item: DecorationTabbarItem, field: 'selected' | 'unselected', urls: string[]) => {
    item[field] = urls[0] || '';
  };
  const ensureQuery = (item: DecorationTabbarItem) => {
    item.link.query ||= {};
  };

  const validColor = (value: string) => /^#[0-9a-f]{6}$/i.test(value);
  const handleSubmit = async () => {
    if (!validColor(form.style.default_color) || !validColor(form.style.selected_color)) {
      Message.error('颜色必须为 #RRGGBB 格式');
      return;
    }
    if (form.list.length < 2 || form.list.length > 5) {
      Message.error('Tabbar 总项数必须为 2～5 项');
      return;
    }
    const visible = form.list.filter((item) => item.is_show === 1).length;
    if (visible < 2 || form.list[0].is_show !== 1 || form.list[0].link.target_type !== 'shop' || form.list[0].link.target !== 'home') {
      Message.error('首项必须显示并固定指向首页，且至少保留 2 个可见项');
      return;
    }
    submitLoading.value = true;
    try {
      await saveDecorationTabbar({ style: { ...form.style }, list: form.list.map((item, position) => ({ ...item, position })) });
      Message.success('保存成功');
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<style scoped lang="less">
  .container { padding: 0 20px 20px; }
  .style-form { max-width: 560px; }
  .item-card { margin-bottom: 14px; }
  .icon-field { display: flex; align-items: center; gap: 10px; }
  .icon-field img { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; }
  .actions { margin-top: 20px; }
</style>
