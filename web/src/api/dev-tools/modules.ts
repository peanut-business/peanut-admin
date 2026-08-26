import axios from 'axios';

const PLATFORM_TOKEN_KEY = 'peanut-platform-token';

interface Envelope<T> {
  code: number;
  msg: string;
  data: T;
}

export interface ModuleRuntimeRow {
  module_key: string;
  name: string;
  version: string;
  manifest_digest: string;
  package_key: string;
  package_version: string;
  status: string;
  dependencies: Array<{ module_key: string; version: string }>;
  dependents: string[];
  tenant_enabled_count: number;
  blockers: string[];
}

export interface UninstallPlanEntry {
  scope: string;
  table: string;
  action: string;
  count: number;
  identifiers: string[];
}

export interface UninstallPreview {
  operation: 'preview';
  plan_digest: string;
  confirm_plan: Record<string, unknown>;
  affected_modules: Array<{ module_key: string }>;
  preserved: UninstallPlanEntry[];
  removed: UninstallPlanEntry[];
  blockers: Array<{ code: string; identifiers: string[] }>;
}

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || undefined,
  withCredentials: true,
});

client.interceptors.request.use((config) => {
  const token = localStorage.getItem(PLATFORM_TOKEN_KEY);
  if (token) {
    config.headers = config.headers || {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

async function unwrap<T>(request: Promise<{ data: Envelope<T> }>): Promise<T> {
  const response = await request;
  if (response.data.code !== 20000) {
    if (response.data.code === 40100)
      localStorage.removeItem(PLATFORM_TOKEN_KEY);
    const details = response.data.data as { error_code?: string } | null;
    throw new Error(
      details?.error_code || response.data.msg || 'Platform request failed'
    );
  }
  return response.data.data;
}

export function hasPlatformSession(): boolean {
  return !!localStorage.getItem(PLATFORM_TOKEN_KEY);
}

export async function loginPlatform(
  email: string,
  password: string
): Promise<void> {
  const session = await unwrap<{ access_token: string }>(
    client.post('/api/platform/session/login', { email, password })
  );
  localStorage.setItem(PLATFORM_TOKEN_KEY, session.access_token);
}

export function listModules(params: {
  page: number;
  page_size: number;
  module_key?: string;
}) {
  return unwrap<{
    lists: ModuleRuntimeRow[];
    count: number;
    pageNo: number;
    pageSize: number;
  }>(client.get('/api/platform/instance-tools/modules', { params }));
}

export function installPackage(form: FormData) {
  return unwrap<Record<string, unknown>>(
    client.post('/api/platform/instance-tools/modules/install', form)
  );
}

export function previewUninstall(moduleKey: string, purge: boolean) {
  return unwrap<UninstallPreview>(
    client.post('/api/platform/instance-tools/modules/uninstall', {
      module_key: moduleKey,
      purge,
      preview: true,
    })
  );
}

export function executeUninstall(
  moduleKey: string,
  purge: boolean,
  preview: UninstallPreview,
  changeReason: string
) {
  return unwrap<Record<string, unknown>>(
    client.post('/api/platform/instance-tools/modules/uninstall', {
      module_key: moduleKey,
      purge,
      preview: false,
      confirm_package_key: preview.confirm_plan.package_key,
      confirm_plan_digest: preview.plan_digest,
      confirm_plan: preview.confirm_plan,
      change_reason: changeReason,
    })
  );
}

export function disableModule(moduleKey: string, changeReason: string) {
  return unwrap<Record<string, unknown>>(
    client.post('/api/platform/instance-tools/modules/disable', {
      module_key: moduleKey,
      change_reason: changeReason,
    })
  );
}

export function syncModules(moduleKey?: string) {
  return unwrap<Record<string, unknown>>(
    client.post('/api/platform/instance-tools/modules/sync', {
      module_key: moduleKey || '',
    })
  );
}
