import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const APP_SETTING: AppRouteRecordRaw = {
  path: '/app-setting',
  name: 'appSetting',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.appSetting',
    requiresAuth: true,
    icon: 'icon-apps',
    order: 6,
  },
  children: [
    {
      path: 'hot-search',
      name: 'AppSettingHotSearch',
      component: () => import('@/views/app-setting/hot-search/index.vue'),
      meta: {
        locale: 'menu.appSetting.hotSearch',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'customer-service',
      name: 'AppSettingCustomerService',
      component: () => import('@/views/app-setting/customer-service/index.vue'),
      meta: {
        locale: 'menu.appSetting.customerService',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'pay',
      name: 'AppSettingPay',
      component: () => import('@/views/app-setting/pay/index.vue'),
      meta: {
        locale: 'menu.appSetting.pay',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'channel',
      name: 'AppSettingChannel',
      component: () => import('@/views/app-setting/channel/index.vue'),
      meta: {
        locale: 'menu.appSetting.channel',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'decorate',
      name: 'AppSettingDecorate',
      component: () => import('@/views/app-setting/decorate/index.vue'),
      meta: {
        locale: 'menu.appSetting.decorate',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'transaction',
      name: 'AppSettingTransaction',
      component: () => import('@/views/app-setting/transaction/index.vue'),
      meta: {
        locale: 'menu.appSetting.transaction',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
  ],
};

export default APP_SETTING;
