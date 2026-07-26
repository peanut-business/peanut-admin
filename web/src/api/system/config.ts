import axios from 'axios';

export interface WebsiteConfig {
  name: string;
  logo: string;
  favicon: string;
  copyright: string;
  icp: string;
}

export function getWebsiteConfig() {
  return axios.get<WebsiteConfig>('/api/admin/config/website');
}

export function saveWebsiteConfig(data: WebsiteConfig) {
  return axios.post('/api/admin/config/website/save', data);
}
