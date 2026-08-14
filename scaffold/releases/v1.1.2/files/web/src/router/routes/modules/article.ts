import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const ARTICLE: AppRouteRecordRaw = {
  path: '/article',
  name: 'article',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.article',
    requiresAuth: true,
    icon: 'icon-file',
    order: 5,
  },
  children: [
    {
      path: 'cate',
      name: 'ArticleCate',
      component: () => import('@/views/article/cate/index.vue'),
      meta: {
        locale: 'menu.article.cate',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
    {
      path: 'list',
      name: 'ArticleList',
      component: () => import('@/views/article/list/index.vue'),
      meta: {
        locale: 'menu.article.list',
        requiresAuth: true,
        roles: ['admin'],
      },
    },
  ],
};

export default ARTICLE;
