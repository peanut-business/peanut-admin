import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.task',
  routes: [{
    path: '/system', name: 'officialTaskRoot', component: DEFAULT_LAYOUT,
    meta: { requiresAuth: true, tenantModuleKey: 'official.task' },
    children: [{ path: 'crontab', name: 'SystemCrontab', component: () => import('@/views/system/crontab/index.vue'), meta: { locale: 'menu.system.crontab', requiresAuth: true, tenantModuleKey: 'official.task', requiredPermissions: 'crontab/lists' } }],
  }],
};
export default contribution;
