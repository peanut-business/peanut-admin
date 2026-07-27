<template>
  <div class="container">
    <a-card :bordered="false">
      <!-- 短信渠道 -->
      <a-typography-title :heading="6" style="margin-bottom: 16px">
        {{ $t('notice.channel.sms') }}
        <a-tag
          :color="detail.status?.sms ? 'green' : 'red'"
          style="margin-left: 8px; vertical-align: middle"
        >
          {{
            detail.status?.sms
              ? $t('notice.channel.status.enabled')
              : $t('notice.channel.status.disabled')
          }}
        </a-tag>
      </a-typography-title>

      <a-form :model="smsDefaultForm" layout="inline" style="margin-bottom: 16px">
        <a-form-item :label="$t('notice.channel.sms.default')">
          <a-radio-group v-model="smsDefaultForm.value" @change="saveSmsDefault">
            <a-radio value="aliyun">{{ $t('notice.channel.aliyun') }}</a-radio>
            <a-radio value="tencent">{{ $t('notice.channel.tencent') }}</a-radio>
          </a-radio-group>
        </a-form-item>
      </a-form>

      <a-tabs v-model:active-key="smsTab">
        <a-tab-pane key="aliyun" :title="$t('notice.channel.aliyun')">
          <a-form :model="aliyunForm" layout="vertical" style="max-width: 520px">
            <a-form-item :label="$t('notice.channel.access_key_id')">
              <a-input v-model="aliyunForm.access_key_id" allow-clear />
            </a-form-item>
            <a-form-item :label="$t('notice.channel.access_key_secret')">
              <a-input-password v-model="aliyunForm.access_key_secret" allow-clear />
            </a-form-item>
            <a-form-item :label="$t('notice.channel.sign_name')">
              <a-input v-model="aliyunForm.sign_name" allow-clear />
            </a-form-item>
            <a-form-item>
              <a-button type="primary" :loading="saving.aliyun" @click="saveSection('sms_aliyun', aliyunForm)">
                {{ $t('notice.channel.save') }}
              </a-button>
            </a-form-item>
          </a-form>
        </a-tab-pane>
        <a-tab-pane key="tencent" :title="$t('notice.channel.tencent')">
          <a-form :model="tencentForm" layout="vertical" style="max-width: 520px">
            <a-form-item :label="$t('notice.channel.secret_id')">
              <a-input v-model="tencentForm.secret_id" allow-clear />
            </a-form-item>
            <a-form-item :label="$t('notice.channel.secret_key')">
              <a-input-password v-model="tencentForm.secret_key" allow-clear />
            </a-form-item>
            <a-form-item :label="$t('notice.channel.sdk_app_id')">
              <a-input v-model="tencentForm.sdk_app_id" allow-clear />
            </a-form-item>
            <a-form-item :label="$t('notice.channel.sign_name')">
              <a-input v-model="tencentForm.sign_name" allow-clear />
            </a-form-item>
            <a-form-item :label="$t('notice.channel.region')">
              <a-input v-model="tencentForm.region" allow-clear />
            </a-form-item>
            <a-form-item>
              <a-button type="primary" :loading="saving.tencent" @click="saveSection('sms_tencent', tencentForm)">
                {{ $t('notice.channel.save') }}
              </a-button>
            </a-form-item>
          </a-form>
        </a-tab-pane>
      </a-tabs>

      <a-divider />

      <!-- 邮件渠道 -->
      <a-typography-title :heading="6" style="margin-bottom: 16px">
        {{ $t('notice.channel.mail') }}
        <a-tag
          :color="detail.status?.mail ? 'green' : 'red'"
          style="margin-left: 8px; vertical-align: middle"
        >
          {{
            detail.status?.mail
              ? $t('notice.channel.status.enabled')
              : $t('notice.channel.status.disabled')
          }}
        </a-tag>
      </a-typography-title>

      <a-form :model="mailForm" layout="vertical" style="max-width: 520px">
        <a-form-item :label="$t('notice.channel.host')">
          <a-input v-model="mailForm.host" allow-clear />
        </a-form-item>
        <a-form-item :label="$t('notice.channel.port')">
          <a-input-number v-model="mailForm.port" :min="1" :max="65535" style="width: 100%" />
        </a-form-item>
        <a-form-item :label="$t('notice.channel.username')">
          <a-input v-model="mailForm.username" allow-clear />
        </a-form-item>
        <a-form-item :label="$t('notice.channel.password')">
          <a-input-password v-model="mailForm.password" allow-clear />
        </a-form-item>
        <a-form-item :label="$t('notice.channel.from_name')">
          <a-input v-model="mailForm.from_name" allow-clear />
        </a-form-item>
        <a-form-item :label="$t('notice.channel.encryption')">
          <a-select v-model="mailForm.encryption" style="width: 180px">
            <a-option value="ssl">SSL</a-option>
            <a-option value="tls">TLS (STARTTLS)</a-option>
            <a-option value="none">None</a-option>
          </a-select>
        </a-form-item>
        <a-form-item>
          <a-button type="primary" :loading="saving.mail" @click="saveSection('mail_smtp', mailForm)">
            {{ $t('notice.channel.save') }}
          </a-button>
        </a-form-item>
      </a-form>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Message } from '@arco-design/web-vue';
import {
  getNoticeChannelDetail,
  saveNoticeChannel,
  NoticeChannelDetail,
  SmsAliyunConfig,
  SmsTencentConfig,
  MailSmtpConfig,
  ChannelSection,
} from '@/api/notice';

const { t } = useI18n();

const detail = ref<Partial<NoticeChannelDetail>>({});
const smsTab = ref('aliyun');

const smsDefaultForm = reactive({ value: 'aliyun' });
const aliyunForm = reactive<SmsAliyunConfig>({
  access_key_id: '',
  access_key_secret: '',
  sign_name: '',
});
const tencentForm = reactive<SmsTencentConfig>({
  secret_id: '',
  secret_key: '',
  sdk_app_id: '',
  sign_name: '',
  region: 'ap-guangzhou',
});
const mailForm = reactive<MailSmtpConfig>({
  host: '',
  port: 465,
  username: '',
  password: '',
  from_name: '',
  encryption: 'ssl',
});
const saving = reactive({ aliyun: false, tencent: false, mail: false });

const fetchDetail = async () => {
  const res = await getNoticeChannelDetail();
  const data = res.data as unknown as { data: NoticeChannelDetail };
  detail.value = data.data;
  const d = data.data;
  smsDefaultForm.value = d.sms_default ?? 'aliyun';
  Object.assign(aliyunForm, d.sms_aliyun ?? {});
  Object.assign(tencentForm, d.sms_tencent ?? {});
  Object.assign(mailForm, d.mail_smtp ?? {});
};

const saveSmsDefault = async () => {
  await saveNoticeChannel('sms_default', { value: smsDefaultForm.value });
  Message.success(t('notice.channel.tip.success'));
  fetchDetail();
};

const saveSection = async (section: ChannelSection, form: Record<string, unknown>) => {
  const loadingKey = section === 'sms_aliyun' ? 'aliyun'
    : section === 'sms_tencent' ? 'tencent' : 'mail';
  saving[loadingKey] = true;
  try {
    await saveNoticeChannel(section, { ...form });
    Message.success(t('notice.channel.tip.success'));
    fetchDetail();
  } finally {
    saving[loadingKey] = false;
  }
};

onMounted(fetchDetail);
</script>
