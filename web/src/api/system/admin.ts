import axios from 'axios';

/** 管理员所属角色（列表接口内联返回） */
export interface AdminRoleBrief {
  id: number;
  name: string;
}

/** 管理员记录 */
export interface AdminRecord {
  id: number;
  username: string;
  nickname: string;
  avatar: string;
  root: number;
  disable: number;
  create_time?: string;
  update_time?: string;
  roles?: AdminRoleBrief[];
}

/** 管理员详情：额外带已分配角色 id 列表 */
export interface AdminDetail extends AdminRecord {
  role_ids: number[];
}

/**
 * 新增/编辑提交体。
 * - username 仅新增有效（编辑不可改，后端 edit 场景忽略）。
 * - password 新增必填；编辑留空 = 不修改（非空即重置密码）。
 */
export interface AdminForm {
  id?: number;
  username: string;
  nickname: string;
  password?: string;
  role_ids: number[];
  disable: number;
}

/** 管理员列表（含所属角色） */
export function getAdminList() {
  return axios.get<AdminRecord[]>('/api/admin/admin/lists');
}

/** 管理员详情（含 role_ids，供编辑回填角色选择） */
export function getAdminDetail(id: number) {
  return axios.get<AdminDetail>('/api/admin/admin/detail', { params: { id } });
}

export function addAdmin(data: AdminForm) {
  return axios.post('/api/admin/admin/add', data);
}

export function editAdmin(data: AdminForm) {
  return axios.post('/api/admin/admin/edit', data);
}

export function deleteAdmin(id: number) {
  return axios.post('/api/admin/admin/delete', { id });
}

/** 启用/禁用：disable 1=禁用 0=启用 */
export function updateAdminStatus(id: number, disable: number) {
  return axios.post('/api/admin/admin/status', { id, disable });
}
