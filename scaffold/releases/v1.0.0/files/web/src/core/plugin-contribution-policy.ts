import type { RouteRecordRaw } from 'vue-router';
import type { PermissionEvaluator } from './permission-policy';
import { evaluateRequiredPermissions } from './permission-policy';

export interface PluginFrontendContribution {
  moduleKey: string;
  routes: RouteRecordRaw[];
}

export function collectPluginContributions(
  modules: Record<string, { default?: PluginFrontendContribution }>
): PluginFrontendContribution[] {
  return Object.keys(modules)
    .sort()
    .map((path) => modules[path].default)
    .filter(
      (contribution): contribution is PluginFrontendContribution =>
        typeof contribution?.moduleKey === 'string' &&
        contribution.moduleKey.length > 0 &&
        Array.isArray(contribution.routes)
    );
}

/** Deployment presence alone never exposes a Tenant Module route. */
export function routesForTenantModules(
  contributions: PluginFrontendContribution[],
  enabledModules: readonly string[],
  grantedPermissions: readonly string[],
  evaluator: PermissionEvaluator
): RouteRecordRaw[] {
  const enabled = new Set(enabledModules);
  return contributions.flatMap((contribution) => {
    if (!enabled.has(contribution.moduleKey)) return [];
    return contribution.routes.filter((route) => {
      const required = route.meta?.requiredPermissions;
      return (
        (typeof required === 'string' || Array.isArray(required)) &&
        evaluateRequiredPermissions(required, grantedPermissions, evaluator)
      );
    });
  });
}
