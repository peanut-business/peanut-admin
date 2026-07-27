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

// ─── 支付配置 ────────────────────────────────────────────────────────────────
export interface PayConfig {
  wx_pay_status: number;
  wx_pay_appid: string;
  wx_pay_mch_id: string;
  wx_pay_secret: string;
  wx_pay_cert_path: string;
  wx_pay_cert_key_path: string;
  ali_pay_status: number;
  ali_pay_app_id: string;
  ali_pay_private_key: string;
  ali_pay_public_key: string;
}

export function getPayConfig() {
  return axios.get<PayConfig>('/api/admin/setting/pay/config');
}

export function savePayConfig(data: PayConfig) {
  return axios.post('/api/admin/setting/pay/save', data);
}

// ─── 渠道配置 ────────────────────────────────────────────────────────────────
export interface ChannelConfig {
  wechat_open_status: number;
  wechat_open_appid: string;
  wechat_open_secret: string;
  wechat_mini_status: number;
  wechat_mini_appid: string;
  wechat_mini_secret: string;
  wechat_oa_status: number;
  wechat_oa_appid: string;
  wechat_oa_secret: string;
  qq_status: number;
  qq_appid: string;
  qq_secret: string;
}

export function getChannelConfig() {
  return axios.get<ChannelConfig>('/api/admin/setting/channel/config');
}

export function saveChannelConfig(data: ChannelConfig) {
  return axios.post('/api/admin/setting/channel/save', data);
}

// ─── 页面装修 ────────────────────────────────────────────────────────────────
export interface BannerItem {
  image: string;
  link: string;
  sort: number;
}

export interface DecorateConfig {
  banners: BannerItem[];
  notice: string;
  notice_show: number;
  hot_show: number;
  news_show: number;
}

export function getDecorateConfig() {
  return axios.get<DecorateConfig>('/api/admin/setting/decorate/config');
}

export function saveDecorateConfig(data: DecorateConfig) {
  return axios.post('/api/admin/setting/decorate/save', data);
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
