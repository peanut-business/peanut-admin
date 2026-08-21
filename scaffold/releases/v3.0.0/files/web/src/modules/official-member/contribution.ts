import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.member',
  routes: [
    { path: '/member', name: 'officialMemberRoot', component: DEFAULT_LAYOUT, meta: { requiresAuth: true, tenantModuleKey: 'official.member' }, children: [
      { path: 'list', name: 'MemberList', component: () => import('@/views/member/list/index.vue'), meta: { locale: 'menu.member.list', requiresAuth: true, tenantModuleKey: 'official.member', requiredPermissions: 'member/lists' } },
      { path: 'tag', name: 'MemberTag', component: () => import('@/views/member/tag/index.vue'), meta: { locale: 'menu.member.tag', requiresAuth: true, tenantModuleKey: 'official.member', requiredPermissions: 'member/tag/lists' } },
    ] },
    { path: '/finance', name: 'officialMemberFinanceRoot', component: DEFAULT_LAYOUT, meta: { requiresAuth: true, tenantModuleKey: 'official.member' }, children: [{ path: 'account-log', name: 'FinanceAccountLog', component: () => import('@/views/finance/account-log/index.vue'), meta: { locale: 'menu.finance.accountLog', requiresAuth: true, tenantModuleKey: 'official.member', requiredPermissions: 'finance/account-log/lists' } }] },
  ],
};
export default contribution;
