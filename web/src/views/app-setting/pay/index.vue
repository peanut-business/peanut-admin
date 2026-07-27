<template>
  <div class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.pay']" />
    <a-card class="general-card" :title="$t('menu.appSetting.pay')">
      <a-spin :loading="loading" style="width: 100%">
        <a-tabs default-active-key="wechat">
          <!-- 微信支付 -->
          <a-tab-pane key="wechat" :title="$t('pay.tab.wechat')">
            <a-form :model="form" layout="vertical" style="max-width: 560px; margin-top: 16px">
              <a-form-item :label="$t('pay.field.status')">
                <a-switch v-model="form.wx_pay_status" :checked-value="1" :unchecked-value="0" />
              </a-form-item>
              <a-form-item :label="$t('pay.field.appid')">
                <a-input v-model="form.wx_pay_appid" :placeholder="$t('pay.field.appid.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('pay.field.mchId')">
                <a-input v-model="form.wx_pay_mch_id" :placeholder="$t('pay.field.mchId.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('pay.field.secret')">
                <a-input-password v-model="form.wx_pay_secret" :placeholder="$t('pay.field.secret.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('pay.field.certPath')">
                <a-input v-model="form.wx_pay_cert_path" :placeholder="$t('pay.field.certPath.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('pay.field.certKeyPath')">
                <a-input v-model="form.wx_pay_cert_key_path" :placeholder="$t('pay.field.certKeyPath.placeholder')" />
              </a-form-item>
              <a-form-item>
                <a-button type="primary" :loading="submitLoading" @click="handleSubmit">
                  {{ $t('pay.operation.save') }}
                </a-button>
              </a-form-item>
            </a-form>
          </a-tab-pane>

          <!-- 支付宝 -->
          <a-tab-pane key="alipay" :title="$t('pay.tab.alipay')">
            <a-form :model="form" layout="vertical" style="max-width: 560px; margin-top: 16px">
              <a-form-item :label="$t('pay.field.status')">
                <a-switch v-model="form.ali_pay_status" :checked-value="1" :unchecked-value="0" />
              </a-form-item>
              <a-form-item :label="$t('pay.field.aliAppId')">
                <a-input v-model="form.ali_pay_app_id" :placeholder="$t('pay.field.aliAppId.placeholder')" />
              </a-form-item>
              <a-form-item :label="$t('pay.field.privateKey')">
                <a-textarea
                  v-model="form.ali_pay_private_key"
                  :placeholder="$t('pay.field.privateKey.placeholder')"
                  :auto-size="{ minRows: 4, maxRows: 8 }"
                />
              </a-form-item>
              <a-form-item :label="$t('pay.field.publicKey')">
                <a-textarea
                  v-model="form.ali_pay_public_key"
                  :placeholder="$t('pay.field.publicKey.placeholder')"
                  :auto-size="{ minRows: 4, maxRows: 8 }"
                />
              </a-form-item>
              <a-form-item>
                <a-button type="primary" :loading="submitLoading" @click="handleSubmit">
                  {{ $t('pay.operation.save') }}
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
  import { getPayConfig, savePayConfig, type PayConfig } from '@/api/app';

  const { t } = useI18n();
  const { loading, setLoading } = useLoading(true);
  const submitLoading = ref(false);

  const form = reactive<PayConfig>({
    wx_pay_status: 0,
    wx_pay_appid: '',
    wx_pay_mch_id: '',
    wx_pay_secret: '',
    wx_pay_cert_path: '',
    wx_pay_cert_key_path: '',
    ali_pay_status: 0,
    ali_pay_app_id: '',
    ali_pay_private_key: '',
    ali_pay_public_key: '',
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await getPayConfig();
      Object.assign(form, data);
    } finally {
      setLoading(false);
    }
  };
  fetchData();

  const handleSubmit = async () => {
    submitLoading.value = true;
    try {
      await savePayConfig({ ...form });
      Message.success(t('pay.tip.success'));
    } finally {
      submitLoading.value = false;
    }
  };
</script>

<script lang="ts">
  export default { name: 'AppSettingPay' };
</script>

<style scoped lang="less">
  .container {
    padding: 0 20px 20px 20px;
  }
</style>
