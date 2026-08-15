import axios from 'axios';

export interface StorageEngineItem {
  name: string;
  path: string;
  engine: string;
  status: number;
}

export interface StorageDetail {
  status: number;
  bucket?: string;
  region?: string;
  access_key?: string;
  secret_key?: string;
  domain?: string;
}

export interface StorageSetupForm {
  engine: string;
  status: number;
  bucket?: string;
  region?: string;
  access_key?: string;
  secret_key?: string;
  domain?: string;
}

export function getStorageList() {
  return axios.get<StorageEngineItem[]>('/api/admin/storage/lists');
}

export function getStorageDetail(engine: string) {
  return axios.get<StorageDetail>('/api/admin/storage/detail', {
    params: { engine },
  });
}

export function setupStorage(data: StorageSetupForm) {
  return axios.post('/api/admin/storage/setup', data);
}

export function changeStorage(engine: string) {
  return axios.post('/api/admin/storage/change', { engine });
}
