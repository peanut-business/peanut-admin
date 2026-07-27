<template>
  <div class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.decorate']" />
    <a-spin :loading="loading" style="width: 100%">
      <!-- 轮播图 -->
      <a-card class="general-card" :title="$t('decorate.section.banners')" style="margin-bottom: 16px">
        <div v-for="(banner, index) in form.banners" :key="index" class="banner-row">
          <a-upload
            :action="uploadAction"
            :headers="uploadHeaders"
            :show-file-list="false"
            list-type="picture-card"
            accept="image/*"
            @success="(f: any) => onBannerImageSuccess(f, index)"
            @error="onUploadError"
          >
            <template #upload-button>
              <div class="banner-thumb">
                <img v-if="banner.image" :src="banner.image" alt="banner" />
                <icon-plus v-else />
              </div>
            </template>
          </a-upload>
          <div class="banner-fields">
            <a-input
              v-model="banner.link"
              :placeholder="$t('decorate.banner.link.placeholder')"
              style="margin-bottom: 8px"
            >
              <template #prepend>{{ $t('decorate.banner.link') }}</template>
            </a-input>
            <a-input-number
              v-model="banner.sort"
              :min="0"
              :max="9999"
              style="width: 140px"
            >
              <template #prepend>{{ $t('decorate.banner.sort') }}</template>
            </a-input-number>
          </div>
          <a-button
            status="danger"
            shape="circle"
            size="small"
            class="banner-del"
            @click="removeBanner(index)"
          >
            <template #icon><icon-delete /></template>
          </a-button>
        </div>
        <a-button @click="addBanner" style="margin-top: 8px">
          <template #icon><icon-plus /></template>
          {{ $t('decorate.banner.add') }}
        </a-button>
      </a-card>

      <!-- 公告设置 -->
      <a-card class="general-card" :title="$t('decorate.section.notice')" style="margin-bottom: 16px">
        <a-form :model="form" layout="vertical" style="max-width: 560px">
          <a-form-item :label="$t('decorate.field.noticeShow')">
            <a-switch v-model="form.notice_show" :checked-value="1" :unchecked-value="0" />
          </a-form-item>
          <a-form-item :label="$t('decorate.field.noticeText')">
            <a-input
              v-model="form.notice"
              :placeholder="$t('decorate.field.noticeText.placeholder')"
            />
          </a-form-item>
        </a-form>
      </a-card>

      <!-- 区块显示 -->
      <a-card class="general-card" :title="$t('decorate.section.blocks')" style="margin-bottom: 16px">
        <a-form :model="form" layout="vertical" style="max-width: 560px">
          <a-form-item :label="$t('decorate.field.hotShow')">
            <a-switch v-model="form.hot_show" :checked-value="1" :unchecked-value="0" />
          </a-form-item>
          <a-form-item :label="$t('decorate.field.newsShow')">
            <a-switch v-model="form.news_show" :checked-value="1" :unchecked-value="0" />
          </a-form-item>
        </a-form>
      </a-card>

      <a-card class="general-card">
        <a-button type="primary" :loading="submitLoading" @click="handleSubmit">
          {{ $t('decorate.operation.save') }}
        </a-button>
      </a-card>
    </a-spin>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FileItem } from '@arco-design/web-vue/es/upload/interfaces';
  import useLoading from '@/hooks/loading';
  import { getToken } from '@/utils/auth';
  import { getDecorateConfig, saveDecorateConfig, type DecorateConfig, type BannerItem } from '@/api/app';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const submitLoading = ref(false);

  const uploadAction = '/api/admin/upload/image';
  const uploadHeaders = computed(() => {
    const token = getToken();
    const headers: Record<string, string> = {};
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
  });

  const form = reactive<DecorateConfig>({
    banners: [],
    notice: '',
    notice_show: 0,
    hot_show: 1,
    news_show: 1,
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getDecorateConfig();
      Object.assign(form, data);
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const addBanner = () => {
    form.banners.push({ image: '', link: '', sort: 0 });
  };

  const removeBanner = (index: number) => {
    form.banners.splice(index, 1);
  };

  const onBannerImageSuccess = (fileItem: FileItem, index: number) => {
    const res = fileItem.response as
      | { code: number; msg: string; data: { url: string } }
      | undefined;
    if (!res || res.code !== 20000) {
      Message.error(res?.msg || t('decorate.tip.uploadFail'));
      return;
    }
    form.banners[index].image = res.data.url;
    Message.success(t('decorate.tip.uploadSuccess'));
  };

  const onUploadError = () => {
    Message.error(t('decorate.tip.uploadFail'));
  };

  const handleSubmit = async () => {
    submitLoading.value = true;
    try {
      await saveDecorateConfig({ ...form });
      Message.success(t('decorate.tip.success'));
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default { name: 'AppSettingDecorate' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .banner-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    padding: 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
  }

  .banner-thumb {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;

    img {
      max-width: 100%;
      max-height: 100%;
      object-fit: cover;
    }
  }

  .banner-fields {
    flex: 1;
  }

  .banner-del {
    flex-shrink: 0;
    margin-top: 4px;
  }
</style>
