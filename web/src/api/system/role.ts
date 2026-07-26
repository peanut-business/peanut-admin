import axios from 'axios';

/** 角色记录 */
export interface RoleRecord {
  id: number;
  name: string;
  desc: string;
  sort: number;
  create_time?: number;
  update_time?: number;
}

/** 角色详情：额外带已授权的菜单 id 列表 */
export interface RoleDetail extends RoleRecord {
  menu_ids: number[];
}

/** 新增/编辑提交体：id 仅编辑时带，menu_ids 为授权菜单 */
export interface RoleForm {
  id?: number;
  name: string;
  desc: string;
  sort: number;
  menu_ids: number[];
}

/** 角色列表（按 sort desc, id desc） */
export function getRoleList() {
  return axios.get<RoleRecord[]>('/api/admin/role/lists');
}

/** 全量角色（供下拉选择） */
export function getRoleAll() {
  return axios.get<RoleRecord[]>('/api/admin/role/all');
}

/** 角色详情（含 menu_ids，供编辑回填权限树） */
export function getRoleDetail(id: number) {
  return axios.get<RoleDetail>('/api/admin/role/detail', { params: { id } });
}

export function addRole(data: RoleForm) {
  return axios.post('/api/admin/role/add', data);
}

export function editRole(data: RoleForm) {
  return axios.post('/api/admin/role/edit', data);
}

export function deleteRole(id: number) {
  return axios.post('/api/admin/role/delete', { id });
}
