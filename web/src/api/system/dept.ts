import axios from 'axios';

export interface DeptRecord {
  id: number;
  pid: number;
  name: string;
  leader: string;
  mobile: string;
  sort: number;
  status: number;
  status_desc?: string;
  is_disable: number;
  create_time?: number | string;
  update_time?: number | string;
  children?: DeptRecord[];
}

/** 新增/编辑提交体：id 仅编辑时带 */
export type DeptForm = Partial<DeptRecord> & { id?: number };

export interface DeptListParams {
  name?: string;
  status?: number | string;
}

/** 树形全量列表（含禁用项，供后台管理） */
export function getDeptList(params?: DeptListParams) {
  return axios.get<DeptRecord[]>('/api/admin/dept/lists', { params });
}

/** 正常部门树，供上级部门选择器 */
export function getDeptAll() {
  return axios.get<DeptRecord[]>('/api/admin/dept/all');
}

/** 正常部门扁平列表，供管理员所属部门选择器 */
export function getLeaderDept() {
  return axios.get<Pick<DeptRecord, 'id' | 'name'>[]>(
    '/api/admin/dept/leaderDept'
  );
}

export function getDeptDetail(id: number) {
  return axios.get<DeptRecord>('/api/admin/dept/detail', { params: { id } });
}

export function addDept(data: DeptForm) {
  return axios.post('/api/admin/dept/add', data);
}

export function editDept(data: DeptForm) {
  return axios.post('/api/admin/dept/edit', data);
}

export function deleteDept(id: number) {
  return axios.post('/api/admin/dept/delete', { id });
}

export function updateDeptStatus(id: number, status: number) {
  return axios.post('/api/admin/dept/status', { id, status });
}
