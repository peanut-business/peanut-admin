import type { PluginFrontendContribution } from '@/core/plugin-contribution-policy';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.payment',
  routes: [
    { path: '/app-setting', name: 'officialPaymentSettingsRoot', component: DEFAULT_LAYOUT, meta: { requiresAuth: true, tenantModuleKey: 'official.payment' }, children: [{ path: 'pay', name: 'AppSettingPay', component: () => import('@/views/app-setting/pay/index.vue'), meta: { locale: 'menu.appSetting.pay', requiresAuth: true, tenantModuleKey: 'official.payment', requiredPermissions: 'setting/pay/config' } }] },
    { path: '/finance', name: 'officialPaymentFinanceRoot', component: DEFAULT_LAYOUT, meta: { requiresAuth: true, tenantModuleKey: 'official.payment' }, children: [
      { path: 'recharge', name: 'FinanceRecharge', component: () => import('@/views/finance/recharge/index.vue'), meta: { locale: 'menu.finance.recharge', requiresAuth: true, tenantModuleKey: 'official.payment', requiredPermissions: 'finance/recharge/lists' } },
      { path: 'refund', name: 'FinanceRefund', component: () => import('@/views/finance/refund/index.vue'), meta: { locale: 'menu.finance.refund', requiresAuth: true, tenantModuleKey: 'official.payment', requiredPermissions: 'finance/refund/record' } },
    ] },
  ],
};
export default contribution;
