import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.file',
  routes: [{
    path: '/system/file', name: 'officialFileRoot', component: DEFAULT_LAYOUT,
    meta: { requiresAuth: true, tenantModuleKey: 'official.file', requiredPermissions: 'official.file.list' },
    children: [{
      path: '', name: 'SystemFile', component: () => import('@/modules/official-file/views/index.vue'),
      meta: { locale: 'menu.system.file', requiresAuth: true, tenantModuleKey: 'official.file', requiredPermissions: 'official.file.list' },
    }],
  }],
};
export default contribution;
