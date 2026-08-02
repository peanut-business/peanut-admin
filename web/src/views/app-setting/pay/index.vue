<template>
  <div class="container">
    <Breadcrumb :items="['menu.appSetting', 'menu.appSetting.pay']" />
    <a-card class="general-card" :title="$t('menu.appSetting.pay')">
      <a-spin :loading="loading" style="width: 100%">
        <a-tabs default-active-key="wechat">
          <a-tab-pane key="wechat" title="微信支付">
            <a-form :model="pay" layout="vertical" class="form-width">
              <a-form-item label="启用微信支付"><a-switch v-model="pay.wx_pay_status" :checked-value="1" :unchecked-value="0" /></a-form-item>
              <a-form-item label="AppID"><a-input v-model="pay.wx_pay_appid" /></a-form-item>
              <a-form-item label="商户号"><a-input v-model="pay.wx_pay_mch_id" /></a-form-item>
              <a-form-item label="APIv3 密钥"><a-input-password v-model="pay.wx_pay_secret" :placeholder="pay.wx_pay_secret_configured ? '已配置，保留 ****** 即不覆盖' : '32 字节 APIv3 密钥'" /></a-form-item>
              <a-form-item label="商户证书路径"><a-input v-model="pay.wx_pay_cert_path" /></a-form-item>
              <a-form-item label="商户私钥路径"><a-input v-model="pay.wx_pay_cert_key_path" /></a-form-item>
              <a-form-item label="微信平台证书路径"><a-input v-model="pay.wx_pay_platform_cert_path" /></a-form-item>
              <a-button v-permission="['setting/pay/save']" type="primary" :loading="savingPay" @click="savePay">保存支付配置</a-button>
            </a-form>
          </a-tab-pane>
          <a-tab-pane key="alipay" title="支付宝">
            <a-form :model="pay" layout="vertical" class="form-width">
              <a-form-item label="启用支付宝"><a-switch v-model="pay.ali_pay_status" :checked-value="1" :unchecked-value="0" /></a-form-item>
              <a-form-item label="应用 AppID"><a-input v-model="pay.ali_pay_app_id" /></a-form-item>
              <a-form-item label="商户 Seller ID"><a-input v-model="pay.ali_pay_seller_id" /></a-form-item>
              <a-form-item label="应用私钥"><a-textarea v-model="pay.ali_pay_private_key" :auto-size="{ minRows: 4, maxRows: 8 }" :placeholder="pay.ali_pay_private_key_configured ? '已配置，保留 ****** 即不覆盖' : ''" /></a-form-item>
              <a-form-item label="支付宝公钥"><a-textarea v-model="pay.ali_pay_public_key" :auto-size="{ minRows: 4, maxRows: 8 }" /></a-form-item>
              <a-button v-permission="['setting/pay/save']" type="primary" :loading="savingPay" @click="savePay">保存支付配置</a-button>
            </a-form>
          </a-tab-pane>
          <a-tab-pane key="recharge" title="充值配置">
            <a-form :model="recharge" layout="vertical" class="form-width">
              <a-form-item label="启用充值"><a-switch v-model="recharge.status" :checked-value="1" :unchecked-value="0" /></a-form-item>
              <a-form-item label="最低金额"><a-input-number v-model="recharge.min_amount" :min="0.01" :precision="2" /></a-form-item>
              <a-form-item label="最高金额"><a-input-number v-model="recharge.max_amount" :min="0.01" :precision="2" /></a-form-item>
            </a-form>
            <a-table :data="recharge.scenes" :pagination="false" row-key="sceneKey" style="margin-top: 12px">
              <template #columns>
                <a-table-column title="终端"><template #cell="{ record }">{{ terminalName(record.terminal) }}</template></a-table-column>
                <a-table-column title="支付方式"><template #cell="{ record }">{{ record.pay_way === 2 ? '微信支付' : '支付宝' }}</template></a-table-column>
                <a-table-column title="启用"><template #cell="{ record }"><a-switch v-model="record.status" :checked-value="1" :unchecked-value="0" @change="onSceneStatus(record)" /></template></a-table-column>
                <a-table-column title="默认"><template #cell="{ record }"><a-radio :model-value="record.is_default === 1" :disabled="record.status !== 1" @change="() => setDefault(record)">默认</a-radio></template></a-table-column>
              </template>
            </a-table>
            <a-button v-permission="['setting/recharge/save']" type="primary" :loading="savingRecharge" style="margin-top: 16px" @click="saveRecharge">保存充值配置</a-button>
          </a-tab-pane>
        </a-tabs>
      </a-spin>
    </a-card>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { Message } from '@arco-design/web-vue';
import useLoading from '@/hooks/loading';
import {
  getPayConfig,
  getRechargeSetting,
  savePayConfig,
  saveRechargeSetting,
  type PayConfig,
  type RechargeScene,
  type RechargeSetting,
} from '@/api/system-settings';

const { loading, setLoading } = useLoading(true);
const savingPay = ref(false);
const savingRecharge = ref(false);
const pay = reactive<PayConfig>({
  wx_pay_status: 0, wx_pay_appid: '', wx_pay_mch_id: '', wx_pay_secret: '',
  wx_pay_cert_path: '', wx_pay_cert_key_path: '', wx_pay_platform_cert_path: '',
  ali_pay_status: 0, ali_pay_app_id: '', ali_pay_private_key: '',
  ali_pay_public_key: '', ali_pay_seller_id: '',
});
type RechargeForm = Omit<RechargeSetting, 'min_amount' | 'max_amount'> & {
  min_amount: number;
  max_amount: number;
};
const recharge = reactive<RechargeForm>({ status: 0, min_amount: 0.01, max_amount: 99999, scenes: [] });
const terminals: Record<number, string> = { 1: '小程序', 2: '公众号', 3: 'H5', 4: 'PC', 5: 'iOS', 6: 'Android' };
const terminalName = (terminal: number) => terminals[terminal] || String(terminal);

async function load() {
  setLoading(true);
  try {
    const [payResult, rechargeResult] = await Promise.all([getPayConfig(), getRechargeSetting()]);
    Object.assign(pay, payResult.data);
    Object.assign(recharge, rechargeResult.data, {
      min_amount: Number(rechargeResult.data.min_amount),
      max_amount: Number(rechargeResult.data.max_amount),
      scenes: rechargeResult.data.scenes.map((scene) => ({ ...scene, sceneKey: `${scene.terminal}-${scene.pay_way}` })),
    });
  } finally { setLoading(false); }
}
load();

function setDefault(scene: RechargeScene) {
  recharge.scenes.forEach((item) => { if (item.terminal === scene.terminal) item.is_default = item === scene ? 1 : 0; });
}
function onSceneStatus(scene: RechargeScene) {
  if (scene.status !== 1) scene.is_default = 0;
  const enabled = recharge.scenes.filter((item) => item.terminal === scene.terminal && item.status === 1);
  if (enabled.length && !enabled.some((item) => item.is_default === 1)) enabled[0].is_default = 1;
}
async function savePay() {
  savingPay.value = true;
  try { await savePayConfig({ ...pay }); Message.success('支付配置已保存'); await load(); }
  finally { savingPay.value = false; }
}
async function saveRecharge() {
  savingRecharge.value = true;
  try {
    await saveRechargeSetting({
      ...recharge,
      min_amount: String(recharge.min_amount),
      max_amount: String(recharge.max_amount),
      scenes: recharge.scenes.map((scene) => ({
        terminal: scene.terminal,
        pay_way: scene.pay_way,
        status: scene.status,
        is_default: scene.is_default,
      })),
    });
    Message.success('充值配置已保存');
    await load();
  } finally { savingRecharge.value = false; }
}
</script>

<style scoped lang="less">
.container { padding: 0 20px 20px; }
.form-width { max-width: 620px; margin-top: 16px; }
</style>
