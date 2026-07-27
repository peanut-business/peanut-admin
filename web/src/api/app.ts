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

// ─── 客服设置 ────────────────────────────────────────────────────────────────
export interface CustomerServiceConfig {
  qr_code: string;
  wechat: string;
  phone: string;
  service_time: string;
}

export function getCustomerServiceConfig() {
  return axios.get<CustomerServiceConfig>(
    '/api/admin/setting/customer-service/config'
  );
}

export function saveCustomerServiceConfig(data: CustomerServiceConfig) {
  return axios.post('/api/admin/setting/customer-service/save', data);
}
