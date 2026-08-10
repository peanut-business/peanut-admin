<template>
  <div class="container">
    <Breadcrumb :items="['装修管理', 'Tabbar 装修']" />
    <div v-loading="loading">
      <el-card class="general-card" header="Tabbar">
        <el-alert type="info" :closable="false" style="margin-bottom: 16px">
          首项固定显示并指向首页；其余项目可调整名称、图标、链接和显示状态。总项数为
          2～5，至少保留 2 个可见项。
        </el-alert>
        <el-form :model="form.style" label-position="top" class="style-form">
          <el-form-item label="默认颜色">
            <el-input
              v-model="form.style.default_color"
              placeholder="#666666"
            />
          </el-form-item>
          <el-form-item label="选中颜色">
            <el-input
              v-model="form.style.selected_color"
              placeholder="#2F80ED"
            />
          </el-form-item>
        </el-form>

        <el-divider>Tabbar 项</el-divider>
        <el-card
          v-for="(item, index) in form.list"
          :key="item.position ?? index"
          class="item-card"
        >
          <template #header>
            <div class="card-header"
              ><span
                >第 {{ index + 1 }} 项{{
                  index === 0 ? '（固定首页）' : ''
                }}</span
              >
              <el-tag v-if="index === 0" type="primary">固定</el-tag>
              <el-button
                v-else
                type="danger"
                size="small"
                @click="removeItem(index)"
                >删除</el-button
              ></div
            >
          </template>
          <el-form :model="item" label-position="top">
            <el-form-item label="名称">
              <el-input v-model="item.name" :maxlength="20" />
            </el-form-item>
            <el-space wrap>
              <el-form-item label="未选中图标">
                <div class="icon-field">
                  <img v-if="item.unselected" :src="item.unselected" alt="" />
                  <FilePicker
                    :type="10"
                    :limit="1"
                    button-text="选择图标"
                    @select="(urls) => setImage(item, 'unselected', urls)"
                  />
                </div>
              </el-form-item>
              <el-form-item label="选中图标">
                <div class="icon-field">
                  <img v-if="item.selected" :src="item.selected" alt="" />
                  <FilePicker
                    :type="10"
                    :limit="1"
                    button-text="选择图标"
                    @select="(urls) => setImage(item, 'selected', urls)"
                  />
                </div>
              </el-form-item>
            </el-space>
            <el-form-item label="业务链接">
              <el-space fill>
                <el-select
                  v-model="item.link.target_type"
                  style="width: 150px"
                  :disabled="index === 0"
                  @change="ensureQuery(item)"
                >
                  <el-option label="站内页面" value="shop" />
                  <el-option label="文章" value="article" />
                  <el-option label="自定义链接" value="custom" />
                  <el-option label="小程序" value="mini_program" />
                </el-select>
                <el-input
                  v-model="item.link.target"
                  placeholder="目标"
                  :disabled="index === 0"
                />
                <template
                  v-if="
                    item.link.target_type === 'mini_program' && item.link.query
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
            <el-form-item label="显示状态">
              <el-switch
                v-model="item.is_show"
                :active-value="1"
                :inactive-value="0"
                :disabled="index === 0"
              />
            </el-form-item>
          </el-form>
        </el-card>
        <el-button v-if="form.list.length < 5" @click="addItem"
          >添加 Tabbar 项</el-button
        >
        <div class="actions">
          <el-button
            v-permission="['decoration/tabbar/save']"
            type="primary"
            :loading="submitLoading"
            @click="handleSubmit"
            >保存</el-button
          >
        </div>
      </el-card>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { ElMessage } from 'element-plus';
  import FilePicker from '@/components/file-picker/index.vue';
  import {
    getDecorationTabbar,
    saveDecorationTabbar,
    type DecorationLink,
    type DecorationTabbar,
    type DecorationTabbarItem,
  } from '@/api/decoration';

  const emptyLink = (): DecorationLink => ({
    target_type: 'shop',
    target: 'home',
  });
  const newItem = (position: number): DecorationTabbarItem => ({
    position,
    name: `菜单 ${position + 1}`,
    selected: '',
    unselected: '',
    link:
      position === 0 ? emptyLink() : { target_type: 'shop', target: 'news' },
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
      form.list.splice(
        0,
        form.list.length,
        ...data.list.map((item, index) => {
          if (item.link.target_type === 'mini_program' && !item.link.query)
            item.link.query = {};
          return { ...item, position: index };
        })
      );
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
    form.list.forEach((item, position) => {
      item.position = position;
    });
  };
  const setImage = (
    item: DecorationTabbarItem,
    field: 'selected' | 'unselected',
    urls: string[]
  ) => {
    item[field] = urls[0] || '';
  };
  const ensureQuery = (item: DecorationTabbarItem) => {
    item.link.query ||= {};
  };

  const validColor = (value: string) => /^#[0-9a-f]{6}$/i.test(value);
  const handleSubmit = async () => {
    if (
      !validColor(form.style.default_color) ||
      !validColor(form.style.selected_color)
    ) {
      ElMessage.error('颜色必须为 #RRGGBB 格式');
      return;
    }
    if (form.list.length < 2 || form.list.length > 5) {
      ElMessage.error('Tabbar 总项数必须为 2～5 项');
      return;
    }
    const visible = form.list.filter((item) => item.is_show === 1).length;
    if (
      visible < 2 ||
      form.list[0].is_show !== 1 ||
      form.list[0].link.target_type !== 'shop' ||
      form.list[0].link.target !== 'home'
    ) {
      ElMessage.error('首项必须显示并固定指向首页，且至少保留 2 个可见项');
      return;
    }
    submitLoading.value = true;
    try {
      await saveDecorationTabbar({
        style: { ...form.style },
        list: form.list.map((item, position) => ({ ...item, position })),
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
  .style-form {
    max-width: 560px;
  }
  .item-card {
    margin-bottom: 14px;
  }
  .icon-field {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .icon-field img {
    width: 48px;
    height: 48px;
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
