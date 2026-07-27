import axios from 'axios';

export interface AccountLogRecord {
  id: number;
  member_id: number;
  member_nickname: string;
  member_sn: string;
  change_amount: string;
  after_amount: string;
  source_type: number;
  direction: number; // 1 收入 2 支出
  remark: string;
  admin_id: number;
  create_time: number;
}

export interface AccountLogParams {
  keyword?: string;
  source_type?: string;
  direction?: string;
  start_time?: number;
  end_time?: number;
  page?: number;
  limit?: number;
}

export interface AccountLogListRes {
  lists: AccountLogRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

export function getAccountLogList(params: AccountLogParams) {
  return axios.get<AccountLogListRes>('/api/admin/finance/account-log/lists', {
    params,
  });
}

// ─── 充值订单 ────────────────────────────────────────────────────────────────
export interface RechargeRecord {
  id: number;
  member_id: number;
  member_nickname: string;
  member_sn: string;
  order_sn: string;
  amount: string;
  pay_way: number; // 1微信 2支付宝
  status: number; // 0待支付 1已支付 2已关闭
  pay_time: number | string;
  create_time: number | string;
}

export interface RechargeParams {
  keyword?: string;
  status?: string;
  pay_way?: string;
  start_time?: number;
  end_time?: number;
  page?: number;
  limit?: number;
}

export interface RechargeListRes {
  lists: RechargeRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

export function getRechargeList(params: RechargeParams) {
  return axios.get<RechargeListRes>('/api/admin/finance/recharge/lists', {
    params,
  });
}

// ─── 退款模块 ────────────────────────────────────────────────────────────────
export interface RefundStat {
  total: number;
  ing: number;
  success: number;
  error: number;
}

export interface RefundRecord {
  id: number;
  sn: string;
  user_id: number;
  nickname: string;
  avatar: string;
  order_id: number;
  order_sn: string;
  order_type: string;
  order_amount: string;
  refund_amount: string;
  transaction_id: string;
  refund_way: number;
  refund_type: number;
  refund_type_text: string;
  refund_status: number;
  refund_status_text: string;
  create_time: number;
}

export interface RefundListExtend {
  total: number;
  ing: number;
  success: number;
  error: number;
}

export interface RefundListRes {
  lists: RefundRecord[];
  count: number;
  page: number;
  limit: number;
  extend: RefundListExtend;
}

export interface RefundParams {
  sn?: string;
  order_sn?: string;
  user_info?: string;
  refund_type?: string | number;
  refund_status?: string | number;
  start_time?: number | string;
  end_time?: number | string;
  page?: number;
  limit?: number;
}

export interface RefundLogRecord {
  id: number;
  sn: string;
  record_id: number;
  user_id: number;
  handle_id: number;
  order_amount: string;
  refund_amount: string;
  refund_status: number;
  refund_status_text: string;
  handler: string;
  create_time: number;
}

export function getRefundStat() {
  return axios.get('/api/admin/finance/refund/stat');
}

export function getRefundRecords(params: RefundParams) {
  return axios.get<RefundListRes>('/api/admin/finance/refund/record', {
    params,
  });
}

export function getRefundLog(recordId: number) {
  return axios.get<RefundLogRecord[]>('/api/admin/finance/refund/log', {
    params: { record_id: recordId },
  });
}
