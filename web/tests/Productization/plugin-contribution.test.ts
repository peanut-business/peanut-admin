import type { RouteRecordRaw } from 'vue-router';
import {
  collectPluginContributions,
  routesForTenantModules,
} from '../../src/core/plugin-contribution-policy';

function expect(condition: boolean, message: string): void {
  if (!condition) throw new Error(message);
}

const route: RouteRecordRaw = {
  path: '/app/fixtures/delivery-records',
  name: 'fixture-delivery-record-list',
  redirect: '/',
  meta: {
    requiresAuth: true,
    tenantModuleKey: 'fixture.delivery-record',
    requiredPermissions: 'fixture.delivery-record.read',
  },
};
const contributions = collectPluginContributions({
  fixture: {
    default: { moduleKey: 'fixture.delivery-record', routes: [route] },
  },
});
const exact = (permissions: ReadonlySet<string>, permission: string) =>
  permissions.has(permission);

expect(contributions.length === 1, 'fixture contribution was not collected');
expect(
  routesForTenantModules(contributions, [], ['*'], exact).length === 0,
  'a deployed Module became visible before TenantModule enablement'
);
expect(
  routesForTenantModules(
    contributions,
    ['fixture.delivery-record'],
    [],
    exact
  ).length === 0,
  'an enabled TenantModule became visible without member permission'
);
expect(
  routesForTenantModules(
    contributions,
    ['fixture.delivery-record'],
    ['fixture.delivery-record.read'],
    exact
  ).length === 1,
  'enabled and authorized fixture Module was not visible'
);

console.log('PLUGIN-FRONTEND-CONTRIBUTION-001 passed');
