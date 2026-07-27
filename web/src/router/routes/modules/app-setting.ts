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
  ],
};

export default APP_SETTING;
