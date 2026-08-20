import axios from 'axios';
import type {
  TenantAuthentication,
  TenantSessionOutcome,
  TenantSelection,
} from '@peanut-admin/admin/core';

const tenantClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || undefined,
  withCredentials: true,
});

type TenantEnvelope<T> =
  | {
      data: T;
      meta: { request_id: string };
    }
  | {
      code: number;
      msg: string;
      data: null;
    };

function tenantData<T>(response: TenantEnvelope<T>): T {
  if ('code' in response) {
    throw new Error(response.msg || 'Tenant session request failed.');
  }
  return response.data;
}

export async function tenantLogin(email: string, password: string) {
  const response = await tenantClient.post<
    TenantEnvelope<TenantSessionOutcome>
  >('/api/tenant/session/login', { email, password });
  return tenantData(response.data);
}

export async function selectTenant(challengeToken: string, tenantId: number) {
  const response = await tenantClient.post<
    TenantEnvelope<TenantAuthentication>
  >('/api/tenant/session/select', {
    challenge_token: challengeToken,
    tenant_id: tenantId,
  });
  return tenantData(response.data);
}

export async function tenantSwitch(accessToken: string) {
  const response = await tenantClient.post<TenantEnvelope<TenantSelection>>(
    '/api/tenant/session/switch',
    {},
    { headers: { Authorization: `Bearer ${accessToken}` } }
  );
  return tenantData(response.data);
}

export async function refreshTenantSession() {
  const response = await tenantClient.post<
    TenantEnvelope<TenantAuthentication>
  >('/api/tenant/session/refresh');
  return tenantData(response.data);
}

export async function tenantLogout(accessToken: string) {
  await tenantClient.post(
    '/api/tenant/session/logout',
    {},
    { headers: { Authorization: `Bearer ${accessToken}` } }
  );
}
