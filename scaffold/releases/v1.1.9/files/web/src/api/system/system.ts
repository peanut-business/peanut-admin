import axios from 'axios';

export interface SystemServerItem {
  param: string;
  value: string;
}

export interface SystemEnvItem {
  option: string;
  require: string;
  status: number;
  remark: string;
}

export interface SystemAuthItem {
  dir: string;
  require: string;
  status: number;
  remark: string;
}

export interface SystemInfo {
  server: SystemServerItem[];
  env: SystemEnvItem[];
  auth: SystemAuthItem[];
}

export function getSystemInfo() {
  return axios.get<SystemInfo>('/api/admin/system/info');
}

export function clearSystemCache() {
  return axios.post('/api/admin/system/clearCache');
}
