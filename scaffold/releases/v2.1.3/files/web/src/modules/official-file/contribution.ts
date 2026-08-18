import type { PluginFrontendContribution } from '@/core/plugin-contribution-policy';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.file',
  routes: [{
    path: '/system', name: 'officialFileRoot', component: DEFAULT_LAYOUT,
    meta: { requiresAuth: true, tenantModuleKey: 'official.file' },
    children: [{
      path: 'file', name: 'SystemFile', component: () => import('@/views/system/file/index.vue'),
      meta: { locale: 'menu.system.file', requiresAuth: true, tenantModuleKey: 'official.file', requiredPermissions: 'file/lists' },
    }],
  }],
};
export default contribution;
