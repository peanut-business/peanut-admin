import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.task',
  routes: [{
    path: '/system', name: 'officialTaskRoot', component: DEFAULT_LAYOUT,
    meta: { requiresAuth: true, tenantModuleKey: 'official.task' },
    children: [{ path: 'crontab', name: 'SystemCrontab', component: () => import('@/modules/official-task/views/index.vue'), meta: { locale: 'menu.system.crontab', requiresAuth: true, tenantModuleKey: 'official.task', requiredPermissions: 'official.task.list' } }],
  }],
};
export default contribution;
