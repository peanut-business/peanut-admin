import axios from 'axios';
import type {
  TenantAuthentication,
  TenantSessionOutcome,
  TenantSelection,
} from '@/core/tenant-session';

const tenantClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || undefined,
});

interface TenantEnvelope<T> {
  data: T;
  meta: { request_id: string };
}

export async function tenantLogin(email: string, password: string) {
  const response = await tenantClient.post<TenantEnvelope<TenantSessionOutcome>>(
    '/api/tenant/session/login',
    { email, password }
  );
  return response.data.data;
}

export async function selectTenant(challengeToken: string, tenantId: number) {
  const response = await tenantClient.post<TenantEnvelope<TenantAuthentication>>(
    '/api/tenant/session/select',
    { challenge_token: challengeToken, tenant_id: tenantId }
  );
  return response.data.data;
}

export async function tenantSwitch(accessToken: string) {
  const response = await tenantClient.post<TenantEnvelope<TenantSelection>>(
    '/api/tenant/session/switch',
    {},
    { headers: { Authorization: `Bearer ${accessToken}` } }
  );
  return response.data.data;
}

export async function tenantLogout(accessToken: string) {
  await tenantClient.post(
    '/api/tenant/session/logout',
    {},
    { headers: { Authorization: `Bearer ${accessToken}` } }
  );
}
