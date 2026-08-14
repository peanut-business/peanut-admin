import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const MEMBER: AppRouteRecordRaw = {
  path: '/member',
  name: 'member',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.member',
    requiresAuth: true,
    icon: 'icon-user',
    order: 2,
  },
  children: [
    {
      path: 'list',
      name: 'MemberList',
      component: () => import('@/views/member/list/index.vue'),
      meta: {
        locale: 'menu.member.list',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'tag',
      name: 'MemberTag',
      component: () => import('@/views/member/tag/index.vue'),
      meta: {
        locale: 'menu.member.tag',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
  ],
};

export default MEMBER;
