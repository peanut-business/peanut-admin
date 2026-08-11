<template>
  <div v-loading="loading" class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.decorate']" />

    <el-card
      class="general-card"
      :header="$t('decorate.section.banners')"
      style="margin-bottom: 16px"
    >
      <div
        v-for="(banner, index) in form.banners"
        :key="index"
        class="banner-row"
      >
        <el-upload
          class="banner-upload"
          :action="uploadAction"
          :headers="uploadHeaders"
          :show-file-list="false"
          accept="image/*"
          :on-success="bannerSuccess(index)"
          :on-error="onUploadError"
        >
          <div class="banner-thumb">
            <img v-if="banner.image" :src="banner.image" alt="banner" />
            <el-icon v-else><Plus /></el-icon>
          </div>
        </el-upload>
        <div class="banner-fields">
          <el-input
            v-model="banner.link"
            :placeholder="$t('decorate.banner.link.placeholder')"
            style="margin-bottom: 8px"
          >
            <template #prepend>{{ $t('decorate.banner.link') }}</template>
          </el-input>
          <div class="sort-field">
            <el-text>{{ $t('decorate.banner.sort') }}</el-text>
            <el-input-number
              v-model="banner.sort"
              :min="0"
              :max="9999"
              style="width: 140px"
            />
          </div>
        </div>
        <el-button
          type="danger"
          circle
          size="small"
          class="banner-del"
          :icon="Delete"
          @click="removeBanner(index)"
        />
      </div>
      <el-button :icon="Plus" style="margin-top: 8px" @click="addBanner">
        {{ $t('decorate.banner.add') }}
      </el-button>
    </el-card>

    <el-card
      class="general-card"
      :header="$t('decorate.section.notice')"
      style="margin-bottom: 16px"
    >
      <el-form :model="form" label-position="top" style="max-width: 560px">
        <el-form-item :label="$t('decorate.field.noticeShow')">
          <el-switch
            v-model="form.notice_show"
            :active-value="1"
            :inactive-value="0"
          />
        </el-form-item>
        <el-form-item :label="$t('decorate.field.noticeText')">
          <el-input
            v-model="form.notice"
            :placeholder="$t('decorate.field.noticeText.placeholder')"
          />
        </el-form-item>
      </el-form>
    </el-card>

    <el-card
      class="general-card"
      :header="$t('decorate.section.blocks')"
      style="margin-bottom: 16px"
    >
      <el-form :model="form" label-position="top" style="max-width: 560px">
        <el-form-item :label="$t('decorate.field.hotShow')">
          <el-switch
            v-model="form.hot_show"
            :active-value="1"
            :inactive-value="0"
          />
        </el-form-item>
        <el-form-item :label="$t('decorate.field.newsShow')">
          <el-switch
            v-model="form.news_show"
            :active-value="1"
            :inactive-value="0"
          />
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="general-card">
      <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
        {{ $t('decorate.operation.save') }}
      </el-button>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { ElMessage, type UploadProps } from 'element-plus';
  import { Delete, Plus } from '@element-plus/icons-vue';
  import useLoading from '@/hooks/loading';
  import { getToken } from '@/utils/auth';
  import {
    getDecorateConfig,
    saveDecorateConfig,
    type DecorateConfig,
  } from '@/api/app';

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

  const bannerSuccess = (index: number): UploadProps['onSuccess'] =>
    (response) => {
      const result = response as
        | { code: number; msg: string; data: { url: string } }
        | undefined;
      if (!result || result.code !== 20000) {
        ElMessage.error(result?.msg || t('decorate.tip.uploadFail'));
        return;
      }
      form.banners[index].image = result.data.url;
      ElMessage.success(t('decorate.tip.uploadSuccess'));
    };

  const onUploadError: UploadProps['onError'] = () => {
    ElMessage.error(t('decorate.tip.uploadFail'));
  };

  const handleSubmit = async () => {
    submitLoading.value = true;
    try {
      await saveDecorateConfig({ ...form });
      ElMessage.success(t('decorate.tip.success'));
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
    border: 1px solid var(--el-border-color);
    border-radius: 4px;
  }

  .banner-thumb {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 148px;
    height: 96px;
    overflow: hidden;
    border: 1px dashed var(--el-border-color);
    border-radius: 6px;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  .banner-fields {
    flex: 1;
  }

  .sort-field {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .banner-del {
    flex-shrink: 0;
    margin-top: 4px;
  }
</style>
