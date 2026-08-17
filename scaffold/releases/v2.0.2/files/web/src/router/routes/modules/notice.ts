import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const NOTICE: AppRouteRecordRaw = {
  path: '/notice',
  name: 'notice',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.notice',
    requiresAuth: true,
    icon: 'icon-notification',
    order: 3,
  },
  children: [
    {
      path: 'channel',
      name: 'NoticeChannel',
      component: () => import('@/views/notice/channel/index.vue'),
      meta: {
        locale: 'menu.notice.channel',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'template',
      name: 'NoticeTemplate',
      component: () => import('@/views/notice/template/index.vue'),
      meta: {
        locale: 'menu.notice.template',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'log',
      name: 'NoticeLog',
      component: () => import('@/views/notice/log/index.vue'),
      meta: {
        locale: 'menu.notice.log',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
  ],
};

export default NOTICE;
