import axios from 'axios';

/** 微信公众号配置（敏感 AppSecret 由服务端以 ****** 掩码返回）。 */
export interface OfficialAccountConfig {
  name: string;
  original_id: string;
  qr_code: string;
  app_id: string;
  app_secret: string;
  app_secret_configured: boolean;
  url: string;
  token: string;
  encoding_aes_key: string;
  encryption_type: 1 | 2 | 3;
  business_domain: string;
  js_secure_domain: string;
  web_auth_domain: string;
  /** 当前 Peanut 回调处理能力固定为明文。 */
  callback_mode: 'plaintext' | string;
}

export type OfficialAccountConfigForm = Pick<
  OfficialAccountConfig,
  | 'name'
  | 'original_id'
  | 'qr_code'
  | 'app_id'
  | 'app_secret'
  | 'token'
  | 'encoding_aes_key'
  | 'encryption_type'
>;

export function getOfficialAccountConfig() {
  return axios.get<OfficialAccountConfig>(
    '/api/admin/setting/official-account/config'
  );
}

export function saveOfficialAccountConfig(data: OfficialAccountConfigForm) {
  return axios.post('/api/admin/setting/official-account/save', data);
}

export type OfficialAccountMenuType = 'click' | 'view' | 'miniprogram';

export interface OfficialAccountMenuItem {
  name: string;
  type?: OfficialAccountMenuType;
  key?: string;
  url?: string;
  appid?: string;
  pagepath?: string;
  sub_button?: OfficialAccountMenuItem[];
}

export interface OfficialAccountMenuResponse {
  menu: OfficialAccountMenuItem[];
}

export function getOfficialAccountMenu() {
  return axios.get<OfficialAccountMenuResponse>(
    '/api/admin/setting/official-account/menu'
  );
}

export function saveOfficialAccountMenu(menu: OfficialAccountMenuItem[]) {
  return axios.post('/api/admin/setting/official-account/menu/save', { menu });
}

export function publishOfficialAccountMenu(menu: OfficialAccountMenuItem[]) {
  return axios.post('/api/admin/setting/official-account/menu/publish', {
    menu,
  });
}

export type OfficialAccountReplyType = 1 | 2 | 3;
export type OfficialAccountMatchingType = 1 | 2;

export interface OfficialAccountReplyRecord {
  id: number;
  name: string;
  keyword: string;
  reply_type: OfficialAccountReplyType;
  matching_type: OfficialAccountMatchingType;
  content_type: 1;
  content: string;
  status: 0 | 1;
  sort: number;
  create_time?: number;
  update_time?: number;
}

export interface OfficialAccountReplyListParams {
  reply_type?: OfficialAccountReplyType;
  page_no?: number;
  page_size?: number;
}

export interface OfficialAccountReplyListResponse {
  list: OfficialAccountReplyRecord[];
  total: number;
  page_no: number;
  page_size: number;
}

export type OfficialAccountReplyForm = Pick<
  OfficialAccountReplyRecord,
  | 'name'
  | 'keyword'
  | 'reply_type'
  | 'matching_type'
  | 'content_type'
  | 'content'
  | 'status'
  | 'sort'
> & { id?: number };

export function getOfficialAccountReplyList(
  params: OfficialAccountReplyListParams
) {
  return axios.get<OfficialAccountReplyListResponse>(
    '/api/admin/setting/official-account/reply/lists',
    { params }
  );
}

export function getOfficialAccountReplyDetail(id: number) {
  return axios.get<OfficialAccountReplyRecord>(
    '/api/admin/setting/official-account/reply/detail',
    { params: { id } }
  );
}

export function addOfficialAccountReply(data: OfficialAccountReplyForm) {
  return axios.post('/api/admin/setting/official-account/reply/add', data);
}

export function editOfficialAccountReply(data: OfficialAccountReplyForm) {
  return axios.post('/api/admin/setting/official-account/reply/edit', data);
}

export function deleteOfficialAccountReply(id: number) {
  return axios.post('/api/admin/setting/official-account/reply/delete', {
    id,
  });
}

export function updateOfficialAccountReplyStatus(id: number, status: 0 | 1) {
  return axios.post('/api/admin/setting/official-account/reply/status', {
    id,
    status,
  });
}

export interface OpenPlatformConfig {
  app_id: string;
  app_secret: string;
  app_secret_configured: boolean;
}

export type OpenPlatformConfigForm = Pick<
  OpenPlatformConfig,
  'app_id' | 'app_secret'
>;

export function getOpenPlatformConfig() {
  return axios.get<OpenPlatformConfig>(
    '/api/admin/setting/open-platform/config'
  );
}

export function saveOpenPlatformConfig(data: OpenPlatformConfigForm) {
  return axios.post('/api/admin/setting/open-platform/save', data);
}
