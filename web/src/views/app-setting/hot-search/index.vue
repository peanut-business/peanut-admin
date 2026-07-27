<template>
  <div class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.hotSearch']" />
    <a-card class="general-card" :title="$t('menu.appSetting.hotSearch')">
      <a-spin :loading="loading" style="width: 100%">
        <a-form :model="form" layout="vertical" style="max-width: 640px">
          <a-form-item :label="$t('hotSearch.field.status')">
            <a-switch
              v-model="form.status"
              :checked-value="1"
              :unchecked-value="0"
            />
            <span class="tip">{{ $t('hotSearch.field.status.tip') }}</span>
          </a-form-item>

          <a-form-item :label="$t('hotSearch.field.words')">
            <div style="width: 100%">
              <div
                v-for="(item, index) in form.data"
                :key="index"
                class="word-row"
              >
                <a-input
                  v-model="item.name"
                  :placeholder="$t('hotSearch.field.word.placeholder')"
                  style="width: 260px"
                  allow-clear
                />
                <a-input-number
                  v-model="item.sort"
                  :min="0"
                  :placeholder="$t('hotSearch.field.sort')"
                  style="width: 140px"
                />
                <a-button status="danger" type="text" @click="removeRow(index)">
                  <template #icon><icon-delete /></template>
                </a-button>
              </div>
              <a-button type="outline" size="small" @click="addRow">
                <template #icon><icon-plus /></template>
                {{ $t('hotSearch.operation.addWord') }}
              </a-button>
            </div>
          </a-form-item>

          <a-form-item>
            <a-button
              type="primary"
              :loading="submitLoading"
              @click="handleSubmit"
            >
              {{ $t('hotSearch.operation.save') }}
            </a-button>
          </a-form-item>
        </a-form>
      </a-spin>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import useLoading from '@/hooks/loading';
  import {
    getHotSearchConfig,
    saveHotSearchConfig,
    type HotSearchConfig,
  } from '@/api/app';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const submitLoading = ref(false);

  const form = reactive<HotSearchConfig>({
    status: 0,
    data: [],
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getHotSearchConfig();
      form.status = data.status;
      form.data = data.data ?? [];
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const addRow = () => {
    form.data.push({ name: '', sort: 0 });
  };
  const removeRow = (index: number) => {
    form.data.splice(index, 1);
  };

  const handleSubmit = async () => {
    submitLoading.value = true;
    try {
      const payload: HotSearchConfig = {
        status: form.status,
        data: form.data.filter((item) => item.name.trim() !== ''),
      };
      await saveHotSearchConfig(payload);
      Message.success(t('hotSearch.tip.success'));
      await fetchData();
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default {
    name: 'AppSettingHotSearch',
  };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }

  .tip {
    margin-left: 12px;
    color: var(--color-text-3);
    font-size: 12px;
  }

  .word-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
  }
</style>
