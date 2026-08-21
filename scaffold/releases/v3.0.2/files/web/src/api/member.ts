import axios from 'axios';

export interface MemberTagRecord {
  id: number;
  name: string;
  remark: string;
}

export interface MemberRecord {
  id: number;
  sn: string;
  account: string;
  nickname: string;
  avatar: string;
  mobile: string;
  email: string;
  real_name: string;
  sex: string;
  sex_value: number;
  channel: string;
  channel_value: number;
  birthday: string | null;
  status: number;
  is_disable: number;
  balance: number;
  user_money: number;
  total_recharge_amount: number;
  points: number;
  create_time: string;
  update_time: string;
  login_time: string;
  login_ip: string;
  tags?: MemberTagRecord[];
  tag_ids?: number[];
}

export interface MemberDetail {
  id: number;
  sn: string;
  account: string;
  nickname: string;
  avatar: string;
  real_name: string;
  sex: 0 | 1 | 2;
  mobile: string;
  create_time: string;
  login_time: string;
  channel: string;
  user_money: number;
  balance: number;
}

export type MemberEditableField = 'account' | 'sex' | 'mobile' | 'real_name';

export interface MemberForm {
  id?: number;
  nickname?: string;
  avatar?: string;
  mobile?: string;
  email?: string;
  sex?: number;
  birthday?: string | null;
  status?: number;
  tag_ids?: number[];
}
export type MemberTagForm = Partial<MemberTagRecord> & { id?: number };

export interface MemberListParams {
  keyword?: string;
  channel?: number | '';
  create_time_start?: string;
  create_time_end?: string;
  status?: string;
  page_no?: number;
  page_size?: number;
  export?: 1 | 2;
  page_type?: 0 | 1;
  page_start?: number;
  page_end?: number;
  file_name?: string;
}

export interface MemberListResult {
  lists: MemberRecord[];
  count: number;
  pageNo: number;
  pageSize: number;
}

export interface MemberExportInfo {
  count: number;
  page_size: number;
  sum_page: number;
  max_page: number;
  all_max_size: number;
  page_start: number;
  page_end: number;
  file_name: string;
}

export interface MemberExportResult {
  url: string;
  file_name: string;
}

export function getMemberList(params: MemberListParams = {}) {
  return axios.get<MemberListResult>('/api/admin/member/lists', { params });
}

export function getMemberExportInfo(params: MemberListParams) {
  return axios.get<MemberExportInfo>('/api/admin/member/lists', {
    params: { ...params, export: 1 },
  });
}

export function exportMembers(params: MemberListParams) {
  return axios.get<MemberExportResult>('/api/admin/member/lists', {
    params: { ...params, export: 2 },
  });
}

export function getMemberDetail(id: number) {
  return axios.get<MemberDetail>('/api/admin/user.user/detail', {
    params: { id },
  });
}

export function addMember(data: MemberForm) {
  return axios.post('/api/admin/member/add', data);
}

export function editMemberProfile(data: MemberForm) {
  return axios.post('/api/admin/member/profile/edit', data);
}

export function updateMemberField(data: {
  id: number;
  field: MemberEditableField;
  value: string | number;
}) {
  return axios.post('/api/admin/user.user/edit', data);
}

export function updateMemberStatus(id: number, status: number) {
  return axios.post('/api/admin/member/status', { id, status });
}

export function adjustMemberBalance(
  id: number,
  amount: number,
  remark: string,
  idempotencyKey = crypto.randomUUID(),
) {
  return axios.post(
    '/api/admin/member/adjustBalance',
    { id, amount, remark },
    { headers: { 'Idempotency-Key': idempotencyKey } },
  );
}

export function adjustMemberMoney(data: {
  user_id: number;
  action: 1 | 2;
  num: number;
  remark?: string;
}, idempotencyKey = crypto.randomUUID()) {
  return axios.post('/api/admin/user.user/adjustMoney', data, {
    headers: { 'Idempotency-Key': idempotencyKey },
  });
}

// 标签
export function getMemberTagList() {
  return axios.get<MemberTagRecord[]>('/api/admin/member/tag/lists');
}

export function addMemberTag(data: MemberTagForm) {
  return axios.post('/api/admin/member/tag/add', data);
}

export function editMemberTag(data: MemberTagForm) {
  return axios.post('/api/admin/member/tag/edit', data);
}

export function deleteMemberTag(id: number) {
  return axios.post('/api/admin/member/tag/delete', { id });
}
