import axios from 'axios';
import {
  clearPlatformToken,
  getPlatformToken,
  setPlatformToken,
} from '@/core/platform-session';

interface Envelope<T> {
  code: number;
  msg: string;
  data: T;
}

export interface PlatformSession {
  access_token: string;
  expires_in: number;
}

export interface PlatformTenant {
  id: number;
  code: string;
  display_name: string;
  status: 'provisioning' | 'active' | 'suspended' | 'closed';
  revision: number;
}

const platformClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || undefined,
});

platformClient.interceptors.request.use((config) => {
  const token = getPlatformToken();
  if (token) {
    config.headers = config.headers || {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

async function data<T>(request: Promise<{ data: Envelope<T> }>): Promise<T> {
  const response = await request;
  if (response.data.code !== 20000) {
    const errorCode =
      response.data.data &&
      typeof response.data.data === 'object' &&
      'error_code' in response.data.data
        ? String(response.data.data.error_code)
        : '';
    const message = response.data.msg || 'Platform request rejected';
    throw new Error(errorCode ? `[${errorCode}] ${message}` : message);
  }
  return response.data.data;
}

export async function platformLogin(email: string, password: string) {
  const session = await data<PlatformSession>(
    platformClient.post('/api/platform/session/login', { email, password })
  );
  setPlatformToken(session.access_token);
  return session;
}

export async function platformLogout() {
  try {
    await data(platformClient.post('/api/platform/session/logout'));
  } finally {
    clearPlatformToken();
  }
}

export async function platformTenants(page = 1, pageSize = 50) {
  return data<{ lists: PlatformTenant[]; count: number }>(
    platformClient.get('/api/platform/tenants', {
      params: { page, page_size: pageSize },
    })
  );
}

export function provisionTenant(payload: {
  tenant_code: string;
  tenant_name: string;
  owner_email: string;
  initial_password: string;
  owner_display_name: string;
}) {
  return data(platformClient.post('/api/platform/tenants/provision', payload));
}

export function transitionTenant(
  action: 'activate' | 'suspend' | 'close',
  tenant: PlatformTenant,
  changeReason: string
) {
  return data(
    platformClient.post(`/api/platform/tenants/${action}`, {
      tenant_id: tenant.id,
      expected_revision: tenant.revision,
      change_reason: changeReason,
    })
  );
}

export function enableTenantModule(payload: {
  tenant_id: number;
  module_key: string;
  config: Record<string, unknown>;
  change_reason: string;
}) {
  return data(
    platformClient.post('/api/platform/tenants/modules/enable', payload)
  );
}

export function disableTenantModule(payload: {
  tenant_id: number;
  module_key: string;
  change_reason: string;
}) {
  return data(
    platformClient.post('/api/platform/tenants/modules/disable', payload)
  );
}
