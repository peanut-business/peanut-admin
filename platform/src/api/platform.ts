import axios from 'axios';

interface Envelope<T> { code: number; msg: string; data: T }
export interface Tenant { id: number; code: string; display_name: string; status: 'provisioning' | 'active' | 'suspended' | 'closed'; revision: number }
export interface Session { access_token: string; expires_in: number }

const tokenKey = 'peanut-platform-token';
const client = axios.create({ baseURL: import.meta.env.VITE_API_BASE_URL || undefined });
client.interceptors.request.use((config) => {
  const token = localStorage.getItem(tokenKey);
  if (token) { config.headers = config.headers || {}; config.headers.Authorization = `Bearer ${token}`; }
  return config;
});
async function unwrap<T>(request: Promise<{ data: Envelope<T> }>): Promise<T> {
  const result = await request;
  if (result.data.code !== 20000) throw new Error(result.data.msg || '平台接口拒绝请求');
  return result.data.data;
}
export const api = {
  async login(email: string, password: string) { const session = await unwrap<Session>(client.post('/api/platform/session/login', { email, password })); localStorage.setItem(tokenKey, session.access_token); return session; },
  async logout() { try { await unwrap(client.post('/api/platform/session/logout')); } finally { localStorage.removeItem(tokenKey); } },
  tenants: (page = 1, pageSize = 50) => unwrap<{ lists: Tenant[]; count: number }>(client.get('/api/platform/tenants', { params: { page, page_size: pageSize } })),
  provision: (payload: Record<string, string>) => unwrap(client.post('/api/platform/tenants/provision', payload)),
  transition: (action: 'activate' | 'suspend' | 'close', tenant: Tenant, reason: string) => unwrap(client.post(`/api/platform/tenants/${action}`, { tenant_id: tenant.id, expected_revision: tenant.revision, change_reason: reason })),
  module: (enabled: boolean, tenantId: number, moduleKey: string, reason: string) => unwrap(client.post(`/api/platform/tenants/modules/${enabled ? 'enable' : 'disable'}`, { tenant_id: tenantId, module_key: moduleKey, config: {}, change_reason: reason })),
};
export function unavailable(name: string): never { throw new Error(`${name}接口尚未由后端提供，无法执行该操作。`); }
