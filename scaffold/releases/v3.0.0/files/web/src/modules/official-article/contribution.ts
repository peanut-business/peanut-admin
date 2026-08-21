import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
import { DEFAULT_LAYOUT } from '@/router/routes/base';

const contribution: PluginFrontendContribution = {
  moduleKey: 'official.article',
  routes: [
    {
      path: '/article',
      name: 'article',
      component: DEFAULT_LAYOUT,
      meta: {
        locale: 'menu.article',
        requiresAuth: true,
        icon: 'icon-file',
        order: 5,
        tenantModuleKey: 'official.article',
        requiredPermissions: 'article.article/lists',
      },
      children: [
        {
          path: 'cate',
          name: 'ArticleCate',
          component: () => import('@/views/article/cate/index.vue'),
          meta: {
            locale: 'menu.article.cate',
            requiresAuth: true,
            tenantModuleKey: 'official.article',
            requiredPermissions: 'article.articleCate/lists',
          },
        },
        {
          path: 'list',
          name: 'ArticleList',
          component: () => import('@/views/article/list/index.vue'),
          meta: {
            locale: 'menu.article.list',
            requiresAuth: true,
            tenantModuleKey: 'official.article',
            requiredPermissions: 'article.article/lists',
          },
        },
      ],
    },
  ],
};

export default contribution;
