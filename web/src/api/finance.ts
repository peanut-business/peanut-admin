import axios from 'axios';

export interface AccountLogRecord {
  account: string;
  nickname: string;
  sn: string;
  avatar: string;
  mobile: string;
  action: 1 | 2;
  change_amount: string;
  left_amount: string;
  change_type: number;
  change_type_desc: string;
  source_sn: string;
  create_time: string;
}

export interface AccountLogParams {
  user_info?: string;
  change_type?: string | number;
  start_time?: string;
  end_time?: string;
  page_no?: number;
  page_size?: number;
}

export interface AccountLogListRes {
  lists: AccountLogRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

export function getAccountLogList(params: AccountLogParams) {
  return axios.get<AccountLogListRes>('/api/admin/finance.account_log/lists', {
    params,
  });
}

export type AccountLogChangeTypeMap = Record<string, string>;

export function getUmChangeType() {
  return axios.get<AccountLogChangeTypeMap>(
    '/api/admin/finance.account_log/getUmChangeType'
  );
}

// ─── 充值订单 ────────────────────────────────────────────────────────────────
export interface RechargeRecord {
  id: number;
  sn: string;
  order_amount: string;
  pay_way: 1 | 2 | 3;
  pay_time: string;
  pay_status: 0 | 1;
  create_time: string;
  refund_status: 0 | 1;
  refunded_amount: string;
  refundable_amount: string;
  avatar: string;
  nickname: string;
  account: string;
  pay_status_text: string;
  pay_way_text: string;
}

export interface RechargeParams {
  sn?: string;
  user_info?: string;
  pay_way?: string | number;
  pay_status?: string | number;
  start_time?: string;
  end_time?: string;
  page_no?: number;
  page_size?: number;
  export?: 1 | 2;
  page_type?: 0 | 1;
  page_start?: number;
  page_end?: number;
  file_name?: string;
}

export interface RechargeListRes {
  lists: RechargeRecord[];
  count: number;
  extend: [];
  page_no?: number;
  page_size?: number;
  pageNo?: number;
  pageSize?: number;
}

export interface RechargeExportInfo {
  count: number;
  page_size: number;
  sum_page: number;
  max_page: number;
  all_max_size: number;
  page_start: number;
  page_end: number;
  file_name: string;
}

export interface RechargeExportResult {
  url: string;
  file_name?: string;
}

export function getRechargeList(params: RechargeParams) {
  return axios.get<RechargeListRes>('/api/admin/recharge.recharge/lists', {
    params,
  });
}

export function getRechargeExportInfo(params: RechargeParams) {
  return axios.get<RechargeExportInfo>('/api/admin/recharge.recharge/lists', {
    params: { ...params, export: 1 },
  });
}

export function exportRecharge(params: RechargeParams) {
  return axios.get<RechargeExportResult>(
    '/api/admin/recharge.recharge/lists',
    { params: { ...params, export: 2 } }
  );
}

export function refundRecharge(
  rechargeId: number,
  refundAmount?: number | string,
  idempotencyKey = crypto.randomUUID(),
) {
  return axios.post(
    '/api/admin/recharge.recharge/refund',
    {
      recharge_id: rechargeId,
      ...(refundAmount === undefined ? {} : { refund_amount: refundAmount }),
    },
    { headers: { 'Idempotency-Key': idempotencyKey } },
  );
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
  refund_way_text: string;
  refund_status: number;
  refund_status_text: string;
  create_time: string;
  update_time?: number | string | null;
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
  page_no?: number;
  page_size?: number;
  pageNo?: number;
  pageSize?: number;
  extend: RefundListExtend;
}

export interface RefundParams {
  sn?: string;
  order_sn?: string;
  user_info?: string;
  refund_type?: string | number;
  refund_status?: string | number;
  start_time?: string;
  end_time?: string;
  page_no?: number;
  page_size?: number;
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
  create_time: string;
  update_time?: number | string | null;
}

export function getRefundStat() {
  return axios.get<RefundStat>('/api/admin/finance.refund/stat');
}

export function getRefundRecords(params: RefundParams) {
  return axios.get<RefundListRes>('/api/admin/finance.refund/record', {
    params,
  });
}

export function getRefundLog(recordId: number) {
  return axios.get<RefundLogRecord[]>('/api/admin/finance.refund/log', {
    params: { record_id: recordId },
  });
}

export function refundAgain(recordId: number) {
  return axios.post('/api/admin/recharge.recharge/refundAgain', {
    record_id: recordId,
  });
}
