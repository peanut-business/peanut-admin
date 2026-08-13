import type { AppRouteRecordRaw } from '../types';

const routes: AppRouteRecordRaw[] = [
  {
    path: '/platform/login',
    name: 'PlatformLogin',
    component: () => import('@/views/platform/login.vue'),
    meta: { requiresAuth: false, controlPlane: 'platform', hideInMenu: true },
  },
  {
    path: '/platform/tenants',
    name: 'PlatformTenants',
    component: () => import('@/views/platform/tenants.vue'),
    meta: { requiresAuth: true, controlPlane: 'platform', hideInMenu: true },
  },
];

export default routes;
