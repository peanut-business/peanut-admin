import type { RouteRecordRaw } from 'vue-router';
import type { PluginFrontendContribution } from '@/core/plugin-contribution-policy';
import lockedContributions from 'virtual:peanut-plugin-contributions';

export const pluginRoutes: RouteRecordRaw[] = (
  lockedContributions as PluginFrontendContribution[]
)
  .flatMap((contribution) => contribution.routes)
  .map(
    (route): RouteRecordRaw => ({
      ...route,
      meta: {
        ...route.meta,
        requiresAuth: route.meta?.requiresAuth === true,
        hideInMenu: true,
      },
    }) as RouteRecordRaw
  );
