import axios from 'axios';

export interface MemberTagRecord {
  id: number;
  name: string;
  remark: string;
}

export interface MemberRecord {
  id: number;
  sn: string;
  nickname: string;
  avatar: string;
  mobile: string;
  email: string;
  sex: number;
  birthday: string | null;
  status: number;
  balance: number;
  points: number;
  create_time: number;
  update_time: number;
  tags?: MemberTagRecord[];
  tag_ids?: number[];
}

export type MemberForm = Partial<MemberRecord> & { id?: number };
export type MemberTagForm = Partial<MemberTagRecord> & { id?: number };

// 会员列表（支持 keyword/status 过滤）
export function getMemberList(params?: { keyword?: string; status?: string }) {
  return axios.get<MemberRecord[]>('/api/admin/member/lists', { params });
}

export function getMemberDetail(id: number) {
  return axios.get<MemberRecord>('/api/admin/member/detail', {
    params: { id },
  });
}

export function addMember(data: MemberForm) {
  return axios.post('/api/admin/member/add', data);
}

export function editMember(data: MemberForm) {
  return axios.post('/api/admin/member/edit', data);
}

export function updateMemberStatus(id: number, status: number) {
  return axios.post('/api/admin/member/status', { id, status });
}

export function adjustMemberBalance(
  id: number,
  amount: number,
  remark: string
) {
  return axios.post('/api/admin/member/adjustBalance', { id, amount, remark });
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
