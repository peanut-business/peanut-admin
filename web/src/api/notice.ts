import axios from 'axios';

// ─── 渠道配置 ─────────────────────────────────────────────────────────────────

export interface SmsAliyunConfig {
  access_key_id: string;
  access_key_secret: string;
  sign_name: string;
}

export interface SmsTencentConfig {
  secret_id: string;
  secret_key: string;
  sdk_app_id: string;
  sign_name: string;
  region: string;
}

export interface MailSmtpConfig {
  host: string;
  port: number;
  username: string;
  password: string;
  from_name: string;
  encryption: 'ssl' | 'tls' | 'none';
}

export interface NoticeChannelDetail {
  sms_default: 'aliyun' | 'tencent';
  sms_aliyun: SmsAliyunConfig;
  sms_tencent: SmsTencentConfig;
  mail_smtp: MailSmtpConfig;
  status: { sms: boolean; mail: boolean };
}

export type ChannelSection =
  | 'sms_default'
  | 'sms_aliyun'
  | 'sms_tencent'
  | 'mail_smtp';

export function getNoticeChannelDetail() {
  return axios.get<NoticeChannelDetail>('/api/admin/notice/channel/detail');
}

export function saveNoticeChannel(
  section: ChannelSection,
  data: Record<string, unknown>
) {
  return axios.post('/api/admin/notice/channel/save', { section, ...data });
}

// ─── 通知模板 ─────────────────────────────────────────────────────────────────

export interface NoticeTemplateRecord {
  id: number;
  name: string;
  code: string;
  channel: number; // 1短信 2邮件 3推送
  title: string;
  content: string;
  is_disable: number;
  remark: string;
  create_time: number;
  update_time: number;
}

export type NoticeTemplateForm = Partial<NoticeTemplateRecord> & {
  id?: number;
};

export function getNoticeTemplateList(params?: {
  name?: string;
  channel?: string;
  is_disable?: string;
  page?: number;
  limit?: number;
}) {
  return axios.get<NoticeTemplateRecord[]>('/api/admin/notice/template/lists', {
    params,
  });
}

export function addNoticeTemplate(data: NoticeTemplateForm) {
  return axios.post('/api/admin/notice/template/add', data);
}

export function editNoticeTemplate(data: NoticeTemplateForm) {
  return axios.post('/api/admin/notice/template/edit', data);
}

export function deleteNoticeTemplate(ids: number[]) {
  return axios.post('/api/admin/notice/template/delete', { ids });
}

// ─── 发送日志 ─────────────────────────────────────────────────────────────────

export interface NoticeLogRecord {
  id: number;
  template_id: number;
  template_name: string;
  template_code: string;
  channel: number;
  receiver: string;
  title: string;
  content: string;
  status: number; // 0待发 1成功 2失败
  error: string;
  send_time: number;
  create_time: number;
}

export function getNoticeLogList(params?: {
  receiver?: string;
  channel?: string;
  status?: string;
  start_time?: number;
  end_time?: number;
  page?: number;
  limit?: number;
}) {
  return axios.get<NoticeLogRecord[]>('/api/admin/notice/log/lists', {
    params,
  });
}

export function getNoticeLogDetail(id: number) {
  return axios.get<NoticeLogRecord>('/api/admin/notice/log/detail', {
    params: { id },
  });
}
