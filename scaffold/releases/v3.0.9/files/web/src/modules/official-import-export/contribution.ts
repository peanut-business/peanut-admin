import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.import-export',
  routes: [
    {
      path: '/system/configuration-transfer',
      name: 'ConfigurationTransfer',
      component: DEFAULT_LAYOUT,
      meta: {
        locale: 'menu.system.configurationTransfer',
        requiresAuth: true,
        tenantModuleKey: 'official.import-export',
        requiredPermissions: 'official.import-export.configuration.export',
      },
      children: [
        {
          path: '',
          name: 'SystemConfigurationTransfer',
          component: () => import('./views/index.vue'),
          meta: {
            locale: 'menu.system.configurationTransfer',
            requiresAuth: true,
            tenantModuleKey: 'official.import-export',
            requiredPermissions: 'official.import-export.configuration.export',
          },
        },
      ],
    },
  ],
};
export default contribution;
