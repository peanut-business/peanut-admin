import axios from 'axios';

export interface DeptRecord {
  id: number;
  pid: number;
  name: string;
  leader: string;
  mobile: string;
  sort: number;
  is_disable: number;
  create_time?: string;
  update_time?: string;
  children?: DeptRecord[];
}

/** 新增/编辑提交体：id 仅编辑时带 */
export type DeptForm = Partial<DeptRecord> & { id?: number };

/** 树形全量列表（含禁用项，供后台管理） */
export function getDeptList() {
  return axios.get<DeptRecord[]>('/api/admin/dept/lists');
}

/** 精简树（id/pid/name），供上级部门选择器 */
export function getDeptAll() {
  return axios.get<DeptRecord[]>('/api/admin/dept/all');
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

export function updateDeptStatus(id: number, isDisable: number) {
  return axios.post('/api/admin/dept/status', { id, is_disable: isDisable });
}
