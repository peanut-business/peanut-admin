import axios from 'axios';
import type {
  MaintenanceScheduleInput,
  OpsConsoleTransport,
  OpsTransportResult,
} from '@peanut-admin/admin/ops-console';

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

export interface SessionInfo {
  audience: 'platform';
  account_id: string;
  platform_operator_id: string;
  permissions: string[];
  navigation: string[];
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

export interface StorageAccount { id:number; account_key:string; driver:'local'|'qiniu'|'aliyun'|'qcloud'; name:string; credentials:Record<string,string>; status:string }
export interface StorageSpace { id:number; space_key:string; account_id:number; account_key:string; driver:string; name:string; access_type:'public'|'private'; bucket?:string; region?:string; endpoint?:string; access_domain?:string; local_path?:string; status:string }
export interface StorageRoute { route_key:string; access_type:'public'|'private'; space_id:number; space_key:string; space_name:string; driver:string }
export interface StorageSnapshot { accounts:StorageAccount[]; spaces:StorageSpace[]; routes:StorageRoute[]; purposes:string[] }
export interface DiagnosticDownload { bytes:ArrayBuffer; filename:string; sha256:string }

export interface OpsBackupProvider {
  key: 'peanut.paired-db-files';
}

export interface OpsBackupTask {
  task_key: string;
  task_type: 'ops.backup.create' | 'ops.restore.verify';
  status: 'queued' | 'running' | 'succeeded' | 'dead' | 'cancelled';
  attempt_count: number;
  max_attempts: number;
  revision: number;
  last_error_code: string | null;
  available_at: string;
  created_at: string;
  updated_at: string;
  completed_at: string | null;
}

export interface OpsLatestVerifiedBackup {
  backup_reference_key: string;
  task_key: string;
  provider_key: 'peanut.paired-db-files';
  manifest_sha256: string;
  source_commit: string;
  source_tree: string;
  source_release_key: string | null;
  consistency_started_at: string;
  consistency_completed_at: string;
  verified_at: string;
  age_seconds: number;
  source_matches_runtime: boolean;
}

export interface OpsLatestRestoreVerified {
  backup_reference_key: string;
  target_key: 'isolated-new-target';
  verified_at: string;
  verification_sha256: string;
  table_count: number;
  migration_count: number;
  tenant_count: number;
  account_count: number;
  tenant_member_count: number;
  file_count: number;
}

export interface OpsBackupCenterSnapshot {
  provider: OpsBackupProvider;
  latest_verified: OpsLatestVerifiedBackup | null;
  latest_restore_verified: OpsLatestRestoreVerified | null;
  tasks: OpsBackupTask[];
}

export type OpsUpgradeReadinessState = 'configuration_required' | 'blocked' | 'ready';

export interface OpsUpgradeReadinessCheck {
  key: string;
  status: OpsUpgradeReadinessState;
  code: string;
}

export interface OpsUpgradeReadinessSnapshot {
  schema_version: 1;
  state: OpsUpgradeReadinessState;
  code: string;
  preflight: { state: OpsUpgradeReadinessState; code: string };
  checks: OpsUpgradeReadinessCheck[];
  source: {
    runtime: { commit: string; tree: string; release_key: string | null; repository_clean: boolean };
    application: null | {
      application_version: string;
      template_version: string;
      template_source_commit: string;
      template_source_tree: string;
      template_inventory_sha256: string;
      application_manifest_sha256: string;
    };
  };
  target: null | {
    release_key: string;
    commit: string;
    tree: string;
    descriptor_sha256: string;
    scaffold: { from_version: string; to_version: string; source_commit: string; source_tree: string; inventory_sha256: string; manifest_sha256: string };
  };
  migrations: {
    current: { applied: number; target: number; pending: number; inventory_sha256: string; drift: boolean };
    release: null | { from_count: number; to_count: number; pending_count: number | null };
    blockers: string[];
  };
  modules: { status: OpsUpgradeReadinessState; installed_count: number; compatible_count: number; target_count: number; lock_sha256: string | null; blockers: string[] };
  scaffold: { status: OpsUpgradeReadinessState; code: string; candidate: string | null; automatic: number; preserved: number; conflicts: number; app_owned_count: number; conflict_reasons: Record<string, number> };
  backup: { latest_verified: OpsLatestVerifiedBackup | null; latest_restore_verified: OpsLatestRestoreVerified | null };
  maintenance: null | { maintenance_key: string; state: string; reason_key: string; starts_at: string; ends_at: string; revision: number };
  recovery_pointer: null | { provider_key: string; backup_reference_key: string; manifest_sha256: string; restore_target_key: string; restore_verification_sha256: string; restore_verified_at: string };
}

const tokenKey = 'peanut-platform-token';
const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || undefined,
  withCredentials: true,
});

let platformRefreshRequest: Promise<string> | null = null;

client.interceptors.request.use((config) => {
  const token = localStorage.getItem(tokenKey);
  if (token) {
    config.headers = config.headers || {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

client.interceptors.response.use(async (response) => {
  const envelope = response.data as Envelope<unknown>;
  const config = response.config as typeof response.config & {
    platformRefreshRetried?: boolean;
  };
  if (
    envelope?.code === 40100 &&
    !config.platformRefreshRetried &&
    config.url !== '/api/platform/session/login' &&
    config.url !== '/api/platform/session/refresh' &&
    config.url !== '/api/platform/session/logout'
  ) {
    config.platformRefreshRetried = true;
    try {
      platformRefreshRequest ||= client
        .post<Envelope<Session>>('/api/platform/session/refresh')
        .then((result) => {
          if (result.data.code !== 20000) throw new Error(result.data.msg);
          localStorage.setItem(tokenKey, result.data.data.access_token);
          return result.data.data.access_token;
        })
        .finally(() => {
          platformRefreshRequest = null;
        });
      const refreshedToken = await platformRefreshRequest;
      config.headers = config.headers || {};
      config.headers.Authorization = `Bearer ${refreshedToken}`;
      return client.request(config);
    } catch {
      localStorage.removeItem(tokenKey);
    }
  }
  return response;
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
  sessionInfo: () => unwrap<SessionInfo>(client.get('/api/platform/session/info')),
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
  storageSnapshot: () => unwrap<StorageSnapshot>(client.get('/api/platform/infrastructure/storage')),
  createStorageAccount: (payload:Record<string,unknown>) => unwrap(client.post('/api/platform/infrastructure/storage/account',payload)),
  updateStorageAccount: (payload:Record<string,unknown>) => unwrap(client.post('/api/platform/infrastructure/storage/account/update',payload)),
  createStorageSpace: (payload:Record<string,unknown>) => unwrap(client.post('/api/platform/infrastructure/storage/space',payload)),
  updateStorageSpace: (payload:Record<string,unknown>) => unwrap(client.post('/api/platform/infrastructure/storage/space/update',payload)),
  setStorageRoute: (payload:Record<string,unknown>) => unwrap(client.post('/api/platform/infrastructure/storage/route',payload)),
  audit: () =>
    unwrap<Page<AuditEvent>>(
      client.get('/api/platform/audit', {
        params: { page: 1, page_size: 100 },
      })
    ),
  backupCenter: () =>
    unwrap<OpsBackupCenterSnapshot>(
      client.get('/api/platform/v1/ops/backups')
    ),
  upgradeReadiness: () =>
    unwrap<OpsUpgradeReadinessSnapshot>(
      client.get('/api/platform/v1/ops/upgrade-readiness')
    ),
  async downloadDiagnostics(windowMinutes: 60 | 360 | 1440 = 60): Promise<DiagnosticDownload> {
    const result = await client.get<ArrayBuffer>('/api/platform/v1/ops/diagnostics', {
      params: { window_minutes: windowMinutes },
      responseType: 'arraybuffer',
    });
    const sha256 = String(result.headers['x-diagnostic-sha256'] || '');
    if (!/^[0-9a-f]{64}$/.test(sha256)) {
      let message = '诊断包生成失败';
      try {
        const envelope = JSON.parse(new TextDecoder().decode(result.data)) as Envelope<unknown>;
        message = envelope.msg || message;
      } catch {
        // A response without the checksum header is never accepted as an artifact.
      }
      throw new Error(message);
    }
    const actual = Array.from(
      new Uint8Array(await crypto.subtle.digest('SHA-256', result.data)),
      (byte) => byte.toString(16).padStart(2, '0')
    ).join('');
    if (actual !== sha256) throw new Error('诊断包完整性校验失败');
    const disposition = String(result.headers['content-disposition'] || '');
    const candidate = disposition.match(/filename="([A-Za-z0-9._-]+)"/)?.[1] || '';
    const filename = /^peanut-admin-diagnostics-[0-9]{8}-[0-9]{6}-[0-9a-f]{12}\.json$/.test(candidate)
      ? candidate
      : `peanut-admin-diagnostics-${sha256.slice(0, 12)}.json`;
    return { bytes: result.data, filename, sha256 };
  },
};

function opsStatus(code: number): number {
  if (code === 20000) return 200;
  if (code === 40100) return 401;
  if (code === 40300) return 403;
  if (code >= 40000 && code < 60000) return Math.floor(code / 100);
  return 500;
}

function opsRequestId(headers: Record<string, unknown>): string {
  const value = headers['x-request-id'];
  return typeof value === 'string' && value.length > 0
    ? value
    : `platform-${crypto.randomUUID()}`;
}

async function opsRead(
  path: string,
  signal: AbortSignal
): Promise<OpsTransportResult> {
  const result = await client.get<Envelope<unknown>>(path, { signal });
  const requestId = opsRequestId(result.headers as Record<string, unknown>);
  if (result.data.code === 20000) {
    return {
      status: 200,
      headers: new Headers({ 'X-Request-Id': requestId }),
      body: { data: result.data.data, meta: { request_id: requestId } },
    };
  }

  const details = result.data.data as { error_code?: string } | null;
  return {
    status: opsStatus(result.data.code),
    headers: new Headers({ 'X-Request-Id': requestId }),
    body: {
      code: details?.error_code || 'OPS_INTERNAL_ERROR',
      request_id: requestId,
    },
  };
}

async function opsSubmitBackup(
  providerKey: string,
  idempotencyKey: string,
  signal: AbortSignal
): Promise<OpsTransportResult> {
  const result = await client.post<Envelope<unknown>>(
    '/api/platform/v1/ops/tasks/backup',
    { provider_key: providerKey },
    { headers: { 'Idempotency-Key': idempotencyKey }, signal }
  );
  const requestId = opsRequestId(result.headers as Record<string, unknown>);
  if (result.data.code === 20000) {
    return {
      status: result.status,
      headers: new Headers({ 'X-Request-Id': requestId }),
      body: { data: result.data.data, meta: { request_id: requestId } },
    };
  }

  const details = result.data.data as { error_code?: string } | null;
  return {
    status: opsStatus(result.data.code),
    headers: new Headers({ 'X-Request-Id': requestId }),
    body: {
      code: details?.error_code || 'OPS_INTERNAL_ERROR',
      request_id: requestId,
    },
  };
}

async function opsSubmitRestore(
  providerKey: string,
  backupReferenceKey: string,
  targetKey: string,
  idempotencyKey: string,
  signal: AbortSignal
): Promise<OpsTransportResult> {
  const result = await client.post<Envelope<unknown>>(
    '/api/platform/v1/ops/tasks/restore',
    {
      provider_key: providerKey,
      backup_reference_key: backupReferenceKey,
      target_key: targetKey,
    },
    { headers: { 'Idempotency-Key': idempotencyKey }, signal }
  );
  const requestId = opsRequestId(result.headers as Record<string, unknown>);
  if (result.data.code === 20000) {
    return {
      status: result.status,
      headers: new Headers({ 'X-Request-Id': requestId }),
      body: { data: result.data.data, meta: { request_id: requestId } },
    };
  }

  const details = result.data.data as { error_code?: string } | null;
  return {
    status: opsStatus(result.data.code),
    headers: new Headers({ 'X-Request-Id': requestId }),
    body: {
      code: details?.error_code || 'OPS_INTERNAL_ERROR',
      request_id: requestId,
    },
  };
}

async function opsScheduleMaintenance(
  input: MaintenanceScheduleInput,
  expectedRevision: number,
  idempotencyKey: string,
  signal: AbortSignal
): Promise<OpsTransportResult> {
  const result = await client.put<Envelope<unknown>>(
    '/api/platform/v1/ops/maintenance',
    {
      reason_key: input.reasonKey,
      starts_at: input.startsAt,
      ends_at: input.endsAt,
    },
    {
      headers: {
        'If-Match': `"rev-${expectedRevision}"`,
        'Idempotency-Key': idempotencyKey,
      },
      signal,
    }
  );
  return opsMutationResult(result);
}

async function opsCloseMaintenance(
  maintenanceKey: string,
  expectedRevision: number,
  idempotencyKey: string,
  signal: AbortSignal
): Promise<OpsTransportResult> {
  const result = await client.post<Envelope<unknown>>(
    `/api/platform/v1/ops/maintenance/${encodeURIComponent(maintenanceKey)}/close`,
    {},
    {
      headers: {
        'If-Match': `"rev-${expectedRevision}"`,
        'Idempotency-Key': idempotencyKey,
      },
      signal,
    }
  );
  return opsMutationResult(result);
}

function opsMutationResult(result: { status: number; data: Envelope<unknown>; headers: Record<string, unknown> }): OpsTransportResult {
  const requestId = opsRequestId(result.headers);
  if (result.data.code === 20000) {
    return {
      status: result.status,
      headers: new Headers({ 'X-Request-Id': requestId }),
      body: { data: result.data.data, meta: { request_id: requestId } },
    };
  }

  const details = result.data.data as { error_code?: string } | null;
  return {
    status: opsStatus(result.data.code),
    headers: new Headers({ 'X-Request-Id': requestId }),
    body: {
      code: details?.error_code || 'OPS_INTERNAL_ERROR',
      request_id: requestId,
    },
  };
}

function unavailable(code: string): Promise<OpsTransportResult> {
  return Promise.resolve({
    status: 503,
    headers: new Headers(),
    body: { code },
  });
}

/** Platform Ops transport: maintenance state and its control writes share one Host. */
export function createPlatformOpsTransport(): OpsConsoleTransport {
  return {
    overview: (signal) => opsRead('/api/platform/v1/ops/status', signal),
    maintenance: (signal) =>
      opsRead('/api/platform/v1/ops/maintenance', signal),
    submitBackup: (providerKey, idempotencyKey, signal) =>
      opsSubmitBackup(providerKey, idempotencyKey, signal),
    submitRestore: (
      providerKey,
      backupReferenceKey,
      targetKey,
      idempotencyKey,
      signal
    ) =>
      opsSubmitRestore(
        providerKey,
        backupReferenceKey,
        targetKey,
        idempotencyKey,
        signal
      ),
    task: (taskKey, signal) =>
      opsRead(`/api/platform/v1/ops/tasks/${encodeURIComponent(taskKey)}`, signal),
    scheduleMaintenance: opsScheduleMaintenance,
    closeMaintenance: opsCloseMaintenance,
    logs: () => unavailable('OPS_LOGS_UNAVAILABLE'),
  };
}
