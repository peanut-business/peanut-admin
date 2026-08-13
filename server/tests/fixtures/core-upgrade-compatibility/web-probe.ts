import {
  createAdminOverrideRegistry,
  defineAdminOverrideSlot,
  hasPermission,
  type AdminOverride,
} from '@peanut-admin/admin/core';

const probeSlot = defineAdminOverrideSlot({
  key: 'compatibility.permission.probe',
  kind: 'service',
  contractVersion: '1.0.0',
  defaultValue: hasPermission,
  validate: (candidate: unknown): candidate is typeof hasPermission =>
    typeof candidate === 'function',
});

const overrides: readonly AdminOverride[] = Object.freeze([]);
const registry = createAdminOverrideRegistry({
  slots: [probeSlot] as const,
  overrides,
});

export const coreUpgradeCompatibilityProbe = registry.get(probeSlot.key);
