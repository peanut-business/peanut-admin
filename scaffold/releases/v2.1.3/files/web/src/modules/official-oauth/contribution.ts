import type { PluginFrontendContribution } from '@/core/plugin-contribution-policy';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.oauth',
  routes: [{
    path: '/app-setting', name: 'officialOAuthRoot', component: DEFAULT_LAYOUT,
    meta: { requiresAuth: true, tenantModuleKey: 'official.oauth' },
    children: [{
      path: 'channel', name: 'AppSettingChannel', component: () => import('@/views/app-setting/channel/index.vue'),
      meta: { locale: 'menu.appSetting.channel', requiresAuth: true, tenantModuleKey: 'official.oauth', requiredPermissions: 'setting/web-page/config' },
    }],
  }],
};
export default contribution;
