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
