import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const FINANCE: AppRouteRecordRaw = {
  path: '/finance',
  name: 'finance',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.finance',
    requiresAuth: true,
    icon: 'icon-fingerprint',
    order: 4,
  },
  children: [
    {
      path: 'account-log',
      name: 'FinanceAccountLog',
      component: () => import('@/views/finance/account-log/index.vue'),
      meta: {
        locale: 'menu.finance.accountLog',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'recharge',
      name: 'FinanceRecharge',
      component: () => import('@/views/finance/recharge/index.vue'),
      meta: {
        locale: 'menu.finance.recharge',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'refund',
      name: 'FinanceRefund',
      component: () => import('@/views/finance/refund/index.vue'),
      meta: {
        locale: 'menu.finance.refund',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
  ],
};

export default FINANCE;
