<template>
  <a-spin :loading="loading" style="width: 100%">
    <a-alert v-if="!canView" type="warning">
      {{ $t('channel.officialAccount.permissionDenied') }}
    </a-alert>
    <a-form
      v-else
      ref="formRef"
      :model="form"
      :rules="rules"
      layout="vertical"
      style="max-width: 720px; margin-top: 16px"
    >
      <a-form-item field="name" :label="$t('channel.officialAccount.name')">
        <a-input
          v-model="form.name"
          :max-length="100"
          :placeholder="$t('channel.officialAccount.namePlaceholder')"
        />
      </a-form-item>
      <a-form-item
        field="original_id"
        :label="$t('channel.officialAccount.originalId')"
      >
        <a-input
          v-model="form.original_id"
          :max-length="100"
          :placeholder="$t('channel.officialAccount.originalIdPlaceholder')"
        />
      </a-form-item>
      <a-form-item
        field="qr_code"
        :label="$t('channel.officialAccount.qrCode')"
      >
        <a-space align="end">
          <a-image
            v-if="form.qr_code"
            :src="form.qr_code"
            width="96"
            height="96"
            fit="cover"
          />
          <FilePicker
            :type="10"
            :limit="1"
            :button-text="$t('channel.officialAccount.selectQrCode')"
            @select="onQrSelected"
          />
          <a-button
            v-if="form.qr_code"
            status="danger"
            @click="form.qr_code = ''"
          >
            {{ $t('channel.operation.clear') }}
          </a-button>
        </a-space>
      </a-form-item>
      <a-form-item field="app_id" label="AppID">
        <a-input
          v-model="form.app_id"
          :max-length="128"
          :placeholder="$t('channel.field.appid.placeholder')"
        />
      </a-form-item>
      <a-form-item field="app_secret" label="AppSecret">
        <a-input-password
          v-model="form.app_secret"
          :max-length="255"
          :placeholder="
            form.app_secret_configured
              ? $t('channel.officialAccount.secretMaskedPlaceholder')
              : $t('channel.field.secret.placeholder')
          "
        />
      </a-form-item>
      <a-form-item
        field="token"
        :label="$t('channel.officialAccount.token')"
      >
        <a-input v-model="form.token" :max-length="255" />
      </a-form-item>
      <a-form-item
        field="encoding_aes_key"
        :label="$t('channel.officialAccount.encodingAesKey')"
      >
        <a-input v-model="form.encoding_aes_key" :max-length="43" />
      </a-form-item>
      <a-form-item
        field="encryption_type"
        :label="$t('channel.officialAccount.encryptionType')"
      >
        <a-radio-group v-model="form.encryption_type">
          <a-radio :value="1">{{ $t('channel.officialAccount.plaintext') }}</a-radio>
          <a-radio :value="2">{{ $t('channel.officialAccount.compatible') }}</a-radio>
          <a-radio :value="3">{{ $t('channel.officialAccount.safe') }}</a-radio>
        </a-radio-group>
      </a-form-item>

      <a-divider orientation="left">
        {{ $t('channel.officialAccount.callback') }}
      </a-divider>
      <a-alert type="info" :show-icon="true">
        {{ $t('channel.officialAccount.plaintextNotice') }}
      </a-alert>
      <a-form-item :label="$t('channel.officialAccount.callbackUrl')">
        <a-input-group>
          <a-input :model-value="form.url" readonly />
          <a-button @click="copyText(form.url)">
            {{ $t('channel.operation.copy') }}
          </a-button>
        </a-input-group>
      </a-form-item>
      <a-form-item
        v-for="field in domainFields"
        :key="field.key"
        :label="$t(field.label)"
      >
        <a-input :model-value="form[field.key]" readonly />
      </a-form-item>
      <a-form-item>
        <a-button
          v-permission="['setting/official-account/save']"
          type="primary"
          :loading="submitLoading"
          @click="handleSubmit"
        >
          {{ $t('channel.operation.save') }}
        </a-button>
      </a-form-item>
    </a-form>
  </a-spin>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { Message } from '@arco-design/web-vue';
  import type { FormInstance } from '@arco-design/web-vue/es/form';
  import FilePicker from '@/components/file-picker/index.vue';
  import { hasPermission } from '@/hooks/permission';
  import {
    getOfficialAccountConfig,
    saveOfficialAccountConfig,
    type OfficialAccountConfig,
    type OfficialAccountConfigForm,
  } from '@/api/official-account';

  const { t } = useI18n();
  const canView = computed(() => hasPermission('setting/official-account/config'));
  const loading = ref(false);
  const submitLoading = ref(false);
  const formRef = ref<FormInstance>();
  const form = reactive<OfficialAccountConfig>({
    name: '',
    original_id: '',
    qr_code: '',
    app_id: '',
    app_secret: '',
    app_secret_configured: false,
    url: '',
    token: '',
    encoding_aes_key: '',
    encryption_type: 1,
    business_domain: '',
    js_secure_domain: '',
    web_auth_domain: '',
    callback_mode: 'plaintext',
  });

  const rules = {
    app_id: [
      { required: true, message: t('channel.field.appid.required') },
    ],
    app_secret: [
      { required: true, message: t('channel.field.secret.required') },
    ],
    encryption_type: [{ required: true }],
  };

  const domainFields: Array<{
    key: 'business_domain' | 'js_secure_domain' | 'web_auth_domain';
    label: string;
  }> = [
    {
      key: 'business_domain',
      label: 'channel.officialAccount.businessDomain',
    },
    {
      key: 'js_secure_domain',
      label: 'channel.officialAccount.jsSecureDomain',
    },
    {
      key: 'web_auth_domain',
      label: 'channel.officialAccount.webAuthDomain',
    },
  ];

  const fetchData = async () => {
    if (!canView.value) return;
    loading.value = true;
    try {
      const { data } = await getOfficialAccountConfig();
      Object.assign(form, data);
    } finally {
      loading.value = false;
    }
  };

  onMounted(fetchData);

  const handleSubmit = async () => {
    const errors = await formRef.value?.validate();
    if (errors) return;
    submitLoading.value = true;
    try {
      const data: OfficialAccountConfigForm = {
        name: form.name.trim(),
        original_id: form.original_id.trim(),
        qr_code: form.qr_code,
        app_id: form.app_id.trim(),
        app_secret: form.app_secret.trim(),
        token: form.token.trim(),
        encoding_aes_key: form.encoding_aes_key.trim(),
        encryption_type: form.encryption_type,
      };
      await saveOfficialAccountConfig(data);
      await fetchData();
      Message.success(t('channel.tip.success'));
    } finally {
      submitLoading.value = false;
    }
  };

  const onQrSelected = (urls: string[]) => {
    form.qr_code = urls[0] || '';
  };

  const copyText = async (value: string) => {
    if (!value) return;
    await navigator.clipboard.writeText(value);
    Message.success(t('channel.tip.copied'));
  };
</script>

<script lang="ts">
  export default { name: 'OfficialAccountConfig' };
</script>
