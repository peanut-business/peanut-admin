import axios from 'axios';

interface Envelope<T> {
  code: number;
  msg: string;
  data: T;
}

export interface Page<T> {
  lists: T[];
  count: number;
}

export interface Session {
  access_token: string;
  expires_in: number;
}

export interface Tenant {
  id: number;
  code: string;
  display_name: string;
  status: 'provisioning' | 'active' | 'suspended' | 'closed';
  revision: number;
}

export interface Invitation {
  id: number;
  tenant_id: number;
  email: string;
  display_name: string;
  status: 'pending' | 'accepted' | 'revoked' | 'expired';
  delivery_status: 'pending_delivery' | 'sent' | 'failed';
  generation: number;
  expires_at: string;
  accept_token?: string;
}

export interface EntryBinding {
  id: number;
  tenant_id: number;
  tenant_code: string;
  tenant_name: string;
  host: string;
  client_key: 'admin-web' | 'member-api';
  status: 'active' | 'disabled';
}

export interface ModuleState {
  id: number | null;
  tenant_id: number;
  module_key: string;
  status: 'not_enabled' | 'enabled' | 'disabled';
  source: string;
  config_revision: number;
  effective_at: string | null;
  expires_at: string | null;
  disabled_reason: string | null;
  installed_version: string;
  installation_status: 'active';
}

export interface Operator {
  id: number;
  account_id: number;
  display_name: string;
  email: string;
  status: 'active' | 'suspended' | 'closed';
  security_revision: number;
  role_keys: string[];
}

export interface PlatformRole {
  id: number;
  key: string;
  name: string;
  description: string | null;
  is_builtin: boolean | number;
  status: 'active' | 'archived';
  revision: number;
  permission_count: number;
  permission_keys: string[];
}

export interface Permission {
  id: number;
  key: string;
  name: string;
  description: string | null;
  risk_level: string;
}

export interface TenantOwner {
  member_id: number;
  tenant_id: number;
  display_name: string;
  email: string | null;
  member_status: string;
  role_key: string;
}

export interface AuditEvent {
  id: number;
  event_type: string;
  action: string;
  outcome: string;
  operator_id: number | null;
  target_type: string | null;
  target_id: string | null;
  request_id: string;
  occurred_at: string;
}

export interface InvitationInspection {
  tenant_name: string;
  display_name: string;
  email_hint: string;
  status: string;
  expires_at: string;
  requires_password: boolean;
}

const tokenKey = 'peanut-platform-token';
const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || undefined,
});

client.interceptors.request.use((config) => {
  const token = localStorage.getItem(tokenKey);
  if (token) {
    config.headers = config.headers || {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

async function unwrap<T>(request: Promise<{ data: Envelope<T> }>): Promise<T> {
  const result = await request;
  if (result.data.code !== 20000) {
    if (result.data.code === 40100) {
      localStorage.removeItem(tokenKey);
    }
    const details = result.data.data as { error_code?: string } | null;
    const errorCode = details?.error_code || '';
    const localizedErrors: Record<string, string> = {
      OWNER_INVITATION_DELIVERY_UNAVAILABLE:
        '尚未配置租户所有者邀请邮件服务，当前不能创建或发送邀请。',
      PLATFORM_AUTHENTICATION_INVALID: '平台登录凭据无效，请重新登录。',
      PLATFORM_SESSION_INVALID: '平台会话已失效，请重新登录。',
      TENANT_CODE_EXISTS: '租户编码已被使用。',
      TENANT_OWNER_INVITATION_PENDING: '该租户已有待接受的所有者邀请。',
      TENANT_ENTRY_BINDING_CONFLICT: '该域名和客户端已绑定其他租户。',
    };
    const fallback = result.data.msg || '平台接口拒绝请求';
    const localizedMessages: Record<string, string> = {
      'Platform authentication credential is invalid.':
        '平台登录凭据无效，请重新登录。',
      'Owner invitation delivery is not configured.':
        '尚未配置租户所有者邀请邮件服务，当前不能创建或发送邀请。',
    };
    throw new Error(
      localizedErrors[errorCode] || localizedMessages[fallback] || fallback
    );
  }
  return result.data.data;
}

export function hasPlatformSession(): boolean {
  return !!localStorage.getItem(tokenKey);
}

export const api = {
  async login(email: string, password: string) {
    const session = await unwrap<Session>(
      client.post('/api/platform/session/login', { email, password })
    );
    localStorage.setItem(tokenKey, session.access_token);
    return session;
  },
  async logout() {
    try {
      await unwrap(client.post('/api/platform/session/logout'));
    } finally {
      localStorage.removeItem(tokenKey);
    }
  },
  tenants: (page = 1, pageSize = 100) =>
    unwrap<Page<Tenant>>(
      client.get('/api/platform/tenants', {
        params: { page, page_size: pageSize },
      })
    ),
  tenantDetail: (id: number) =>
    unwrap<Record<string, unknown>>(
      client.get('/api/platform/tenants/detail', { params: { id } })
    ),
  tenantOwner: (tenantId: number) =>
    unwrap<TenantOwner>(
      client.get('/api/platform/tenants/owner', { params: { tenant_id: tenantId } })
    ),
  provision: (payload: Record<string, string | number>) =>
    unwrap<Invitation>(client.post('/api/platform/tenants/provision', payload)),
  transition: (
    action: 'activate' | 'suspend' | 'close',
    tenant: Tenant,
    changeReason: string
  ) =>
    unwrap(
      client.post(`/api/platform/tenants/${action}`, {
        tenant_id: tenant.id,
        expected_revision: tenant.revision,
        change_reason: changeReason,
      })
    ),
  invitations: (tenantId: number) =>
    unwrap<Page<Invitation>>(
      client.get('/api/platform/tenants/invitations', {
        params: { tenant_id: tenantId, page: 1, page_size: 100 },
      })
    ),
  inviteOwner: (payload: Record<string, string | number>) =>
    unwrap<Invitation>(client.post('/api/platform/tenants/invitations', payload)),
  resendInvitation: (invitationId: number) =>
    unwrap<Invitation>(
      client.post('/api/platform/tenants/invitations/resend', {
        invitation_id: invitationId,
        expires_in_hours: 72,
      })
    ),
  revokeInvitation: (invitationId: number) =>
    unwrap(
      client.post('/api/platform/tenants/invitations/revoke', {
        invitation_id: invitationId,
      })
    ),
  inspectInvitation: (token: string) =>
    unwrap<InvitationInspection>(
      client.get('/api/tenant/owner-invitations/inspect', { params: { token } })
    ),
  acceptInvitation: (token: string, newAccountPassword?: string) =>
    unwrap<Record<string, unknown>>(
      client.post('/api/tenant/owner-invitations/accept', {
        token,
        new_account_password: newAccountPassword || '',
      })
    ),
  entryBindings: (tenantId?: number) =>
    unwrap<EntryBinding[]>(
      client.get('/api/platform/tenant-entry-bindings', {
        params: tenantId ? { tenant_id: tenantId } : {},
      })
    ),
  enableEntryBinding: (payload: Record<string, string | number>) =>
    unwrap<EntryBinding>(
      client.post('/api/platform/tenant-entry-bindings/enable', payload)
    ),
  disableEntryBinding: (bindingId: number, changeReason: string) =>
    unwrap(
      client.post('/api/platform/tenant-entry-bindings/disable', {
        binding_id: bindingId,
        change_reason: changeReason,
      })
    ),
  moduleStates: (tenantId: number) =>
    unwrap<Page<ModuleState>>(
      client.get('/api/platform/tenants/modules', {
        params: { tenant_id: tenantId, page: 1, page_size: 100 },
      })
    ),
  changeModule: (
    enabled: boolean,
    tenantId: number,
    moduleKey: string,
    changeReason: string
  ) =>
    unwrap(
      client.post(
        `/api/platform/tenants/modules/${enabled ? 'enable' : 'disable'}`,
        {
          tenant_id: tenantId,
          module_key: moduleKey,
          config: {},
          change_reason: changeReason,
        }
      )
    ),
  operators: () =>
    unwrap<Page<Operator>>(
      client.get('/api/platform/operators', {
        params: { page: 1, page_size: 100 },
      })
    ),
  createOperator: (payload: Record<string, string>) =>
    unwrap(client.post('/api/platform/operators/create', payload)),
  updateOperator: (operator: Operator, displayName: string, changeReason: string) =>
    unwrap(
      client.post('/api/platform/operators/update', {
        operator_id: operator.id,
        expected_revision: operator.security_revision,
        display_name: displayName,
        change_reason: changeReason,
      })
    ),
  transitionOperator: (
    action: 'activate' | 'suspend' | 'close',
    operator: Operator,
    changeReason: string
  ) =>
    unwrap(
      client.post(`/api/platform/operators/${action}`, {
        operator_id: operator.id,
        expected_revision: operator.security_revision,
        change_reason: changeReason,
      })
    ),
  replaceOperatorRoles: (
    operator: Operator,
    roleIds: number[],
    changeReason: string
  ) =>
    unwrap(
      client.post('/api/platform/operators/roles/replace', {
        operator_id: operator.id,
        role_ids: roleIds,
        expected_revision: operator.security_revision,
        change_reason: changeReason,
      })
    ),
  roles: () =>
    unwrap<Page<PlatformRole>>(
      client.get('/api/platform/roles', {
        params: { page: 1, page_size: 100 },
      })
    ),
  permissions: () =>
    unwrap<Page<Permission>>(
      client.get('/api/platform/permissions', {
        params: { page: 1, page_size: 100 },
      })
    ),
  createRole: (payload: Record<string, string>) =>
    unwrap(client.post('/api/platform/roles/create', payload)),
  updateRole: (
    role: PlatformRole,
    name: string,
    description: string,
    changeReason: string
  ) =>
    unwrap(
      client.post('/api/platform/roles/update', {
        role_id: role.id,
        expected_revision: role.revision,
        name,
        description,
        change_reason: changeReason,
      })
    ),
  archiveRole: (role: PlatformRole, changeReason: string) =>
    unwrap(
      client.post('/api/platform/roles/archive', {
        role_id: role.id,
        expected_revision: role.revision,
        change_reason: changeReason,
      })
    ),
  replaceRolePermissions: (
    role: PlatformRole,
    permissionKeys: string[],
    changeReason: string
  ) =>
    unwrap(
      client.post('/api/platform/roles/permissions/replace', {
        role_id: role.id,
        permission_keys: permissionKeys,
        expected_revision: role.revision,
        change_reason: changeReason,
      })
    ),
  audit: () =>
    unwrap<Page<AuditEvent>>(
      client.get('/api/platform/audit', {
        params: { page: 1, page_size: 100 },
      })
    ),
};
