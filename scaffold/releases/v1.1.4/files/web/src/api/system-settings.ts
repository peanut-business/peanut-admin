import axios from 'axios';

export interface PayConfig {
  wx_pay_status: number;
  wx_pay_appid: string;
  wx_pay_mch_id: string;
  wx_pay_secret: string;
  wx_pay_secret_configured?: boolean;
  wx_pay_cert_path: string;
  wx_pay_cert_key_path: string;
  wx_pay_platform_cert_path: string;
  ali_pay_status: number;
  ali_pay_app_id: string;
  ali_pay_private_key: string;
  ali_pay_private_key_configured?: boolean;
  ali_pay_public_key: string;
  ali_pay_seller_id: string;
}

export interface RechargeScene {
  terminal: number;
  pay_way: number;
  status: number;
  is_default: number;
}

export interface RechargeSetting {
  status: number;
  min_amount: string;
  max_amount: string;
  scenes: RechargeScene[];
}

export interface UserSetting {
  default_avatar: string;
}

export interface LoginSetting {
  login_way: number[];
  coerce_mobile: number;
  login_agreement: number;
  third_auth: number;
  wechat_auth: number;
}

export const getPayConfig = () =>
  axios.get<PayConfig>('/api/admin/setting/pay/config');
export const savePayConfig = (data: PayConfig) =>
  axios.post('/api/admin/setting/pay/save', data);
export const getRechargeSetting = () =>
  axios.get<RechargeSetting>('/api/admin/setting/recharge/config');
export const saveRechargeSetting = (data: RechargeSetting) =>
  axios.post('/api/admin/setting/recharge/save', data);
export const getUserSetting = () =>
  axios.get<UserSetting>('/api/admin/config/user');
export const saveUserSetting = (data: UserSetting) =>
  axios.post('/api/admin/config/user/save', data);
export const getLoginSetting = () =>
  axios.get<LoginSetting>('/api/admin/config/login');
export const saveLoginSetting = (data: LoginSetting) =>
  axios.post('/api/admin/config/login/save', data);
