import axios from 'axios';

export interface OperationLogRecord {
  id: number;
  admin_id: number;
  username: string;
  ip: string;
  uri: string;
  method: string;
  params: string;
  create_time: string;
}

export interface OperationLogParams {
  username?: string;
  uri?: string;
  method?: string;
  page_no?: number;
  page_size?: number;
}

export interface OperationLogListRes {
  lists: OperationLogRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

export function getOperationLogList(params: OperationLogParams) {
  return axios.get<OperationLogListRes>('/api/admin/log/lists', { params });
}

export function clearOperationLog() {
  return axios.post('/api/admin/log/clear', {});
}
