import axios from 'axios';

export interface OperationLogRecord {
  id: number;
  admin_id: number;
  username: string;
  ip: string;
  uri: string;
  method: string;
  params: string;
  create_time: number;
}

export interface OperationLogParams {
  username?: string;
  uri?: string;
  method?: string;
  ip?: string;
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

export interface OperationLogListRes {
  lists: OperationLogRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

export interface OperationLogExportInfo {
  count: number;
  page_size: number;
  sum_page: number;
  max_page: number;
  all_max_size: number;
  page_start: number;
  page_end: number;
  file_name: string;
}

export interface OperationLogExportResult {
  url: string;
  file_name: string;
}

export function getOperationLogList(params: OperationLogParams) {
  return axios.get<OperationLogListRes>('/api/admin/log/lists', { params });
}

export function getOperationLogExportInfo(params: OperationLogParams) {
  return axios.get<OperationLogExportInfo>('/api/admin/log/lists', {
    params: { ...params, export: 1 },
  });
}

export function exportOperationLog(params: OperationLogParams) {
  return axios.get<OperationLogExportResult>('/api/admin/log/lists', {
    params: { ...params, export: 2 },
  });
}

export function clearOperationLog() {
  return axios.post('/api/admin/log/clear', {});
}
