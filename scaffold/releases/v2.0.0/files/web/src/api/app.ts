import axios from 'axios';

// ─── 热门搜索 ────────────────────────────────────────────────────────────────
export interface HotSearchItem {
  id?: number;
  name: string;
  sort: number;
}

export interface HotSearchConfig {
  status: number; // 0 关 1 开
  data: HotSearchItem[];
}

export function getHotSearchConfig() {
  return axios.get<HotSearchConfig>('/api/admin/setting/hot-search/config');
}

export function saveHotSearchConfig(data: HotSearchConfig) {
  return axios.post('/api/admin/setting/hot-search/save', data);
}

// ─── 渠道配置 ────────────────────────────────────────────────────────────────
export interface WebPageConfig {
  status: 0 | 1;
  page_status: 0 | 1;
  page_url: string;
  url: string;
}

export function getWebPageConfig() {
  return axios.get<WebPageConfig>('/api/admin/setting/web-page/config');
}

export function saveWebPageConfig(data: Omit<WebPageConfig, 'url'>) {
  return axios.post('/api/admin/setting/web-page/save', data);
}

export interface MiniProgramConfig {
  name: string;
  original_id: string;
  qr_code: string;
  app_id: string;
  app_secret: string;
  request_domain: string;
  socket_domain: string;
  upload_file_domain: string;
  download_file_domain: string;
  udp_domain: string;
  business_domain: string;
}

export function getMiniProgramConfig() {
  return axios.get<MiniProgramConfig>('/api/admin/setting/mini-program/config');
}

export function saveMiniProgramConfig(
  data: Pick<
    MiniProgramConfig,
    'name' | 'original_id' | 'qr_code' | 'app_id' | 'app_secret'
  >
) {
  return axios.post('/api/admin/setting/mini-program/save', data);
}

// ─── 交易设置 ────────────────────────────────────────────────────────────────
export interface TransactionConfig {
  cancel_unpaid_orders: number;
  cancel_unpaid_orders_times: number;
  verification_orders: number;
  verification_orders_times: number;
}

export function getTransactionConfig() {
  return axios.get<TransactionConfig>('/api/admin/setting/transaction/config');
}

export function saveTransactionConfig(data: TransactionConfig) {
  return axios.post('/api/admin/setting/transaction/save', data);
}
