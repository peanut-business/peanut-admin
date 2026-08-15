import {
  createAdminOverrideRegistry,
  defineAdminOverrideSlot,
  hasPermission as coreHasPermission,
} from '@peanut-admin/admin/core';
import PEANUT_ADMIN_OVERRIDES from '@/peanut.overrides';

export type PermissionEvaluator = (
  permissions: ReadonlySet<string>,
  permission: string
) => boolean;

export const PERMISSION_EVALUATOR_OVERRIDE_KEY =
  'authorization.permission.service.evaluator' as const;

const isPermissionEvaluator = (
  candidate: unknown
): candidate is PermissionEvaluator => typeof candidate === 'function';

const permissionEvaluatorSlot = defineAdminOverrideSlot<
  PermissionEvaluator,
  {
    readonly key: typeof PERMISSION_EVALUATOR_OVERRIDE_KEY;
    readonly kind: 'service';
    readonly contractVersion: '1.0.0';
    readonly defaultValue: PermissionEvaluator;
    readonly validate: typeof isPermissionEvaluator;
  }
>({
  key: PERMISSION_EVALUATOR_OVERRIDE_KEY,
  kind: 'service',
  contractVersion: '1.0.0',
  defaultValue: coreHasPermission,
  validate: isPermissionEvaluator,
});

const registry = createAdminOverrideRegistry({
  slots: [permissionEvaluatorSlot] as const,
  overrides: PEANUT_ADMIN_OVERRIDES,
});

export const permissionEvaluator = registry.get(
  PERMISSION_EVALUATOR_OVERRIDE_KEY
);

export const coreOverrideDiagnostics = registry.diagnostics;
