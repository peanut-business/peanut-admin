import axios from 'axios';

export interface JobsRecord {
  id: number;
  name: string;
  code: string;
  sort: number;
  is_disable: number;
  remark: string;
  create_time?: string;
  update_time?: string;
}

/** 新增/编辑提交体：id 仅编辑时带 */
export type JobsForm = Partial<JobsRecord> & { id?: number };

export interface JobsListParams {
  name?: string;
  code?: string;
  is_disable?: number | '';
  page_no?: number;
  page_size?: number;
}

export interface JobsListRes {
  lists: JobsRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

/** 分页列表（含禁用项，供后台管理） */
export function getJobsList(params: JobsListParams) {
  return axios.get<JobsListRes>('/api/admin/jobs/lists', { params });
}

/** 全量启用岗位（供下拉选择） */
export function getJobsAll() {
  return axios.get<JobsRecord[]>('/api/admin/jobs/all');
}

export function getJobsDetail(id: number) {
  return axios.get<JobsRecord>('/api/admin/jobs/detail', { params: { id } });
}

export function addJobs(data: JobsForm) {
  return axios.post('/api/admin/jobs/add', data);
}

export function editJobs(data: JobsForm) {
  return axios.post('/api/admin/jobs/edit', data);
}

export function deleteJobs(id: number) {
  return axios.post('/api/admin/jobs/delete', { id });
}

export function updateJobsStatus(id: number, isDisable: number) {
  return axios.post('/api/admin/jobs/status', { id, is_disable: isDisable });
}
