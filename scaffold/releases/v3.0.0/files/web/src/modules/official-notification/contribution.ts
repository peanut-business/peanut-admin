import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.notification',
  routes: [{
    path: '/notice', name: 'officialNotificationRoot', component: DEFAULT_LAYOUT,
    meta: { requiresAuth: true, tenantModuleKey: 'official.notification' },
    children: [
      { path: 'channel', name: 'NoticeChannel', component: () => import('@/views/notice/channel/index.vue'), meta: { locale: 'menu.notice.channel', requiresAuth: true, tenantModuleKey: 'official.notification', requiredPermissions: 'notice/channel/detail' } },
      { path: 'template', name: 'NoticeTemplate', component: () => import('@/views/notice/template/index.vue'), meta: { locale: 'menu.notice.template', requiresAuth: true, tenantModuleKey: 'official.notification', requiredPermissions: 'notice/scene/lists' } },
      { path: 'log', name: 'NoticeLog', component: () => import('@/views/notice/log/index.vue'), meta: { locale: 'menu.notice.log', requiresAuth: true, tenantModuleKey: 'official.notification', requiredPermissions: 'notice/log/lists' } },
    ],
  }],
};
export default contribution;
