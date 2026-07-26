import axios from 'axios';

export interface ListRes<T> {
  lists: T[];
  count: number;
  pageNo: number;
  pageSize: number;
}

// 文件类型：10 图片 / 20 视频 / 30 文件
export type FileType = 10 | 20 | 30;

// ---- 分类 ----
export interface FileCateRecord {
  id: number;
  pid: number;
  type: FileType;
  name: string;
  create_time?: string;
}

export function getFileCateList(type: FileType) {
  return axios.get<FileCateRecord[]>('/api/admin/file/cate/lists', {
    params: { type },
  });
}

export function addFileCate(data: { type: FileType; name: string }) {
  return axios.post('/api/admin/file/cate/add', data);
}

export function editFileCate(data: { id: number; name: string }) {
  return axios.post('/api/admin/file/cate/edit', data);
}

export function deleteFileCate(id: number) {
  return axios.post('/api/admin/file/cate/delete', { id });
}

// ---- 文件 ----
export interface FileRecord {
  id: number;
  cid: number;
  type: FileType;
  name: string;
  uri: string;
  url: string;
  create_time?: string;
}

export interface FileListParams {
  type: FileType;
  cid?: number | '';
  name?: string;
  page_no?: number;
  page_size?: number;
}

export function getFileList(params: FileListParams) {
  return axios.get<ListRes<FileRecord>>('/api/admin/file/lists', { params });
}

export function moveFile(ids: number[], cid: number) {
  return axios.post('/api/admin/file/move', { ids, cid });
}

export function renameFile(id: number, name: string) {
  return axios.post('/api/admin/file/rename', { id, name });
}

export function deleteFile(ids: number[]) {
  return axios.post('/api/admin/file/delete', { ids });
}

// 上传地址（供 a-upload 直接使用）
export const uploadUrl: Record<FileType, string> = {
  10: '/api/admin/upload/image',
  20: '/api/admin/upload/video',
  30: '/api/admin/upload/file',
};
