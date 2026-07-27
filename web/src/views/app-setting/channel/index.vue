<template>
  <div class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.channel']" />
    <a-card class="general-card" :title="$t('menu.appSetting.channel')">
      <a-spin :loading="loading" style="width: 100%">
        <a-tabs default-active-key="wechat_open">
          <!-- 微信开放平台 -->
          <a-tab-pane key="wechat_open" :title="$t('channel.tab.wechatOpen')">
            <a-form :model="form" layout="vertical" style="max-width: 560px; margin-top: 16px">
              <a-form-item :label="$t('channel.field.status')">
                <a-switch v-model="form.wechat_open_status" :checked-value="1" :unchecked-value="0" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.appid')">
                <a-input v-model="form.wechat_open_appid" :placeholder="$t('channel.field.appid.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.secret')">
                <a-input-password v-model="form.wechat_open_secret" :placeholder="$t('channel.field.secret.placeholder')" />
              </a-form-item>
              <a-form-item>
                <a-button type="primary" :loading="submitLoading" @click="handleSubmit">
                  {{ $t('channel.operation.save') }}
                </a-button>
              </a-form-item>
            </a-form>
          </a-tab-pane>

          <!-- 微信小程序 -->
          <a-tab-pane key="wechat_mini" :title="$t('channel.tab.wechatMini')">
            <a-form :model="form" layout="vertical" style="max-width: 560px; margin-top: 16px">
              <a-form-item :label="$t('channel.field.status')">
                <a-switch v-model="form.wechat_mini_status" :checked-value="1" :unchecked-value="0" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.appid')">
                <a-input v-model="form.wechat_mini_appid" :placeholder="$t('channel.field.appid.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.secret')">
                <a-input-password v-model="form.wechat_mini_secret" :placeholder="$t('channel.field.secret.placeholder')" />
              </a-form-item>
              <a-form-item>
                <a-button type="primary" :loading="submitLoading" @click="handleSubmit">
                  {{ $t('channel.operation.save') }}
                </a-button>
              </a-form-item>
            </a-form>
          </a-tab-pane>

          <!-- 微信公众号 -->
          <a-tab-pane key="wechat_oa" :title="$t('channel.tab.wechatOa')">
            <a-form :model="form" layout="vertical" style="max-width: 560px; margin-top: 16px">
              <a-form-item :label="$t('channel.field.status')">
                <a-switch v-model="form.wechat_oa_status" :checked-value="1" :unchecked-value="0" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.appid')">
                <a-input v-model="form.wechat_oa_appid" :placeholder="$t('channel.field.appid.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.secret')">
                <a-input-password v-model="form.wechat_oa_secret" :placeholder="$t('channel.field.secret.placeholder')" />
              </a-form-item>
              <a-form-item>
                <a-button type="primary" :loading="submitLoading" @click="handleSubmit">
                  {{ $t('channel.operation.save') }}
                </a-button>
              </a-form-item>
            </a-form>
          </a-tab-pane>

          <!-- QQ -->
          <a-tab-pane key="qq" :title="$t('channel.tab.qq')">
            <a-form :model="form" layout="vertical" style="max-width: 560px; margin-top: 16px">
              <a-form-item :label="$t('channel.field.status')">
                <a-switch v-model="form.qq_status" :checked-value="1" :unchecked-value="0" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.appid')">
                <a-input v-model="form.qq_appid" :placeholder="$t('channel.field.appid.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('channel.field.secret')">
                <a-input-password v-model="form.qq_secret" :placeholder="$t('channel.field.secret.placeholder')" />
              </a-form-item>
              <a-form-item>
                <a-button type="primary" :loading="submitLoading" @click="handleSubmit">
                  {{ $t('channel.operation.save') }}
                </a-button>
              </a-form-item>
            </a-form>
          </a-tab-pane>
        </a-tabs>
      </a-spin>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import useLoading from '@/hooks/loading';
  import { getChannelConfig, saveChannelConfig, type ChannelConfig } from '@/api/app';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const submitLoading = ref(false);

  const form = reactive<ChannelConfig>({
    wechat_open_status: 0, wechat_open_appid: '', wechat_open_secret: '',
    wechat_mini_status: 0, wechat_mini_appid: '', wechat_mini_secret: '',
    wechat_oa_status: 0,   wechat_oa_appid: '',   wechat_oa_secret: '',
    qq_status: 0,          qq_appid: '',           qq_secret: '',
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getChannelConfig();
      Object.assign(form, data);
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const handleSubmit = async () => {
    submitLoading.value = true;
    try {
      await saveChannelConfig({ ...form });
      Message.success(t('channel.tip.success'));
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default { name: 'AppSettingChannel' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
