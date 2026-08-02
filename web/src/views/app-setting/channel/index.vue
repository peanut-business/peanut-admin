<template>
  <div class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.channel']" />
    <a-card class="general-card" :title="$t('menu.appSetting.channel')">
      <a-spin :loading="loading" style="width: 100%">
        <a-tabs default-active-key="official-account">
          <a-tab-pane
            key="official-account"
            :title="$t('channel.tab.wechatOa')"
          >
            <OfficialAccountConfig />
          </a-tab-pane>
          <a-tab-pane
            key="official-account-menu"
            :title="$t('channel.tab.wechatOaMenu')"
          >
            <OfficialAccountMenu />
          </a-tab-pane>
          <a-tab-pane
            key="official-account-reply"
            :title="$t('channel.tab.wechatOaReply')"
          >
            <OfficialAccountReply />
          </a-tab-pane>
          <a-tab-pane
            key="open-platform"
            :title="$t('channel.tab.wechatOpen')"
          >
            <OpenPlatform />
          </a-tab-pane>

          <!-- 既有 H5 网页渠道 -->
          <a-tab-pane key="h5" :title="$t('channel.tab.h5')">
            <a-form
              ref="webPageFormRef"
              :model="webPageForm"
              :rules="webPageRules"
              layout="vertical"
              style="max-width: 640px; margin-top: 16px"
            >
              <a-form-item field="status" :label="$t('channel.h5.status')">
                <a-switch
                  v-model="webPageForm.status"
                  :checked-value="1"
                  :unchecked-value="0"
                />
              </a-form-item>
              <a-form-item :label="$t('channel.h5.url')">
                <a-input-group>
                  <a-input :model-value="webPageForm.url" readonly />
                  <a-button @click="copyH5Url">
                    {{ $t('channel.operation.copy') }}
                  </a-button>
                </a-input-group>
              </a-form-item>
              <template v-if="webPageForm.status === 0">
                <a-form-item
                  field="page_status"
                  :label="$t('channel.h5.closedMode')"
                >
                  <a-radio-group v-model="webPageForm.page_status">
                    <a-radio :value="0">
                      {{ $t('channel.h5.closedMode.blank') }}
                    </a-radio>
                    <a-radio :value="1">
                      {{ $t('channel.h5.closedMode.redirect') }}
                    </a-radio>
                  </a-radio-group>
                </a-form-item>
                <a-form-item
                  v-if="webPageForm.page_status === 1"
                  field="page_url"
                  :label="$t('channel.h5.redirectUrl')"
                >
                  <a-input
                    v-model="webPageForm.page_url"
                    :placeholder="$t('channel.h5.redirectUrl.placeholder')"
                  />
                </a-form-item>
              </template>
              <a-form-item>
                <a-button
                  v-permission="['setting/web-page/save']"
                  type="primary"
                  :loading="webPageSubmitLoading"
                  @click="handleWebPageSubmit"
                >
                  {{ $t('channel.operation.save') }}
                </a-button>
              </a-form-item>
            </a-form>
          </a-tab-pane>

          <!-- 既有微信小程序渠道 -->
          <a-tab-pane key="wechat_mini" :title="$t('channel.tab.wechatMini')">
            <a-form
              ref="miniProgramFormRef"
              :model="miniProgramForm"
              :rules="miniProgramRules"
              layout="vertical"
              style="max-width: 720px; margin-top: 16px"
            >
              <a-form-item field="name" :label="$t('channel.mini.name')">
                <a-input
                  v-model="miniProgramForm.name"
                  :placeholder="$t('channel.mini.name.placeholder')"
                />
              </a-form-item>
              <a-form-item
                field="original_id"
                :label="$t('channel.mini.originalId')"
              >
                <a-input
                  v-model="miniProgramForm.original_id"
                  :placeholder="$t('channel.mini.originalId.placeholder')"
                />
              </a-form-item>
              <a-form-item field="qr_code" :label="$t('channel.mini.qrCode')">
                <a-space align="end">
                  <a-image
                    v-if="miniProgramForm.qr_code"
                    :src="miniProgramForm.qr_code"
                    width="96"
                    height="96"
                    fit="cover"
                  />
                  <FilePicker
                    :type="10"
                    :limit="1"
                    :button-text="$t('channel.mini.qrCode.select')"
                    @select="onMiniQrSelected"
                  />
                  <a-button
                    v-if="miniProgramForm.qr_code"
                    status="danger"
                    @click="miniProgramForm.qr_code = ''"
                  >
                    {{ $t('channel.operation.clear') }}
                  </a-button>
                </a-space>
              </a-form-item>
              <a-form-item field="app_id" :label="$t('channel.field.appid')">
                <a-input
                  v-model="miniProgramForm.app_id"
                  :placeholder="$t('channel.field.appid.placeholder')"
                />
              </a-form-item>
              <a-form-item
                field="app_secret"
                :label="$t('channel.field.secret')"
              >
                <a-input-password
                  v-model="miniProgramForm.app_secret"
                  :placeholder="$t('channel.field.secret.placeholder')"
                />
              </a-form-item>
              <a-divider orientation="left">
                {{ $t('channel.mini.domains') }}
              </a-divider>
              <a-form-item
                v-for="field in miniDomainFields"
                :key="field.key"
                :label="$t(field.label)"
              >
                <a-input-group>
                  <a-input :model-value="miniProgramForm[field.key]" readonly />
                  <a-button @click="copyText(miniProgramForm[field.key])">
                    {{ $t('channel.operation.copy') }}
                  </a-button>
                </a-input-group>
              </a-form-item>
              <a-form-item>
                <a-button
                  v-permission="['setting/mini-program/save']"
                  type="primary"
                  :loading="miniProgramSubmitLoading"
                  @click="handleMiniProgramSubmit"
                >
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
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import useLoading from '@/hooks/loading';
  import FilePicker from '@/components/file-picker/index.vue';
  import {
    getWebPageConfig,
    saveWebPageConfig,
    getMiniProgramConfig,
    saveMiniProgramConfig,
    type MiniProgramConfig,
    type WebPageConfig,
  } from '@/api/app';
  import OfficialAccountConfig from './OfficialAccountConfig.vue';
  import OfficialAccountMenu from './OfficialAccountMenu.vue';
  import OfficialAccountReply from './OfficialAccountReply.vue';
  import OpenPlatform from './OpenPlatform.vue';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const webPageSubmitLoading = ref(false);
  const miniProgramSubmitLoading = ref(false);
  const webPageFormRef = ref<FormInstance>();
  const miniProgramFormRef = ref<FormInstance>();

  const webPageForm = reactive<WebPageConfig>({
    status: 1,
    page_status: 0,
    page_url: '',
    url: '',
  });
  const webPageRules = {
    status: [{ required: true }],
    page_status: [{ required: true }],
    page_url: [
      {
        validator: (value: string, callback: (error?: string) => void) => {
          if (webPageForm.status === 0 && webPageForm.page_status === 1) {
            try {
              const url = new URL(value);
              if (!['http:', 'https:'].includes(url.protocol)) throw new Error();
            } catch {
              callback(t('channel.h5.redirectUrl.invalid'));
              return;
            }
          }
          callback();
        },
      },
    ],
  };

  const miniProgramForm = reactive<MiniProgramConfig>({
    name: '',
    original_id: '',
    qr_code: '',
    app_id: '',
    app_secret: '',
    request_domain: '',
    socket_domain: '',
    upload_file_domain: '',
    download_file_domain: '',
    udp_domain: '',
    business_domain: '',
  });
  const miniProgramRules = {
    app_id: [
      { required: true, message: t('channel.field.appid.required') },
    ],
    app_secret: [
      { required: true, message: t('channel.field.secret.required') },
    ],
  };
  const miniDomainFields: Array<{
    key:
      | 'request_domain'
      | 'socket_domain'
      | 'upload_file_domain'
      | 'download_file_domain'
      | 'udp_domain'
      | 'business_domain';
    label: string;
  }> = [
    { key: 'request_domain', label: 'channel.mini.domain.request' },
    { key: 'socket_domain', label: 'channel.mini.domain.socket' },
    { key: 'upload_file_domain', label: 'channel.mini.domain.upload' },
    { key: 'download_file_domain', label: 'channel.mini.domain.download' },
    { key: 'udp_domain', label: 'channel.mini.domain.udp' },
    { key: 'business_domain', label: 'channel.mini.domain.business' },
  ];

  const fetchData = async () => {
    setLoading(true);
    try {
      const [webPageResult, miniProgramResult] = await Promise.all([
        getWebPageConfig(),
        getMiniProgramConfig(),
      ]);
      Object.assign(webPageForm, webPageResult.data);
      Object.assign(miniProgramForm, miniProgramResult.data);
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const handleWebPageSubmit = async () => {
    const errors = await webPageFormRef.value?.validate();
    if (errors) return;
    webPageSubmitLoading.value = true;
    try {
      await saveWebPageConfig({
        status: webPageForm.status,
        page_status: webPageForm.page_status,
        page_url: webPageForm.page_url.trim(),
      });
      const { data } = await getWebPageConfig();
      Object.assign(webPageForm, data);
      Message.success(t('channel.tip.success'));
    } finally {
      webPageSubmitLoading.value = false;
    }
  };

  const handleMiniProgramSubmit = async () => {
    const errors = await miniProgramFormRef.value?.validate();
    if (errors) return;
    miniProgramSubmitLoading.value = true;
    try {
      await saveMiniProgramConfig({
        name: miniProgramForm.name.trim(),
        original_id: miniProgramForm.original_id.trim(),
        qr_code: miniProgramForm.qr_code,
        app_id: miniProgramForm.app_id.trim(),
        app_secret: miniProgramForm.app_secret.trim(),
      });
      const { data } = await getMiniProgramConfig();
      Object.assign(miniProgramForm, data);
      Message.success(t('channel.tip.success'));
    } finally {
      miniProgramSubmitLoading.value = false;
    }
  };

  const onMiniQrSelected = (urls: string[]) => {
    miniProgramForm.qr_code = urls[0] || '';
  };

  const copyText = async (value: string) => {
    if (!value) return;
    await navigator.clipboard.writeText(value);
    Message.success(t('channel.tip.copied'));
  };

  const copyH5Url = async () => copyText(webPageForm.url);
</script>

<script lang="ts">
  export default { name: 'AppSettingChannel' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
