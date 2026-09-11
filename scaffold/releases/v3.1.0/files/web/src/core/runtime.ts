import {
  createAdminOverrideRegistry,
  permissionEvaluatorSlot,
  PERMISSION_EVALUATOR_OVERRIDE_KEY,
} from '@peanut-admin/admin/core';
import PEANUT_ADMIN_OVERRIDES from '@/peanut.overrides';

export { PERMISSION_EVALUATOR_OVERRIDE_KEY } from '@peanut-admin/admin/core';
export type { PermissionEvaluator } from '@peanut-admin/admin/core';

const registry = createAdminOverrideRegistry({
  slots: [permissionEvaluatorSlot] as const,
  overrides: PEANUT_ADMIN_OVERRIDES,
});

export const permissionEvaluator = registry.get(
  PERMISSION_EVALUATOR_OVERRIDE_KEY
);

export const coreOverrideDiagnostics = registry.diagnostics;
