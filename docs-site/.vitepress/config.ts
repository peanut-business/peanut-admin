import { defineConfig } from 'vitepress'

export default defineConfig({
  lang: 'zh-CN',
  title: 'Peanut Admin',
  description: 'Peanut Admin 的公开开发与使用文档。',
  cleanUrls: true,
  lastUpdated: true,
  sitemap: {
    hostname: 'https://peanut-admin-doc.007345.xyz',
  },
  themeConfig: {
    siteTitle: 'Peanut Admin',
    nav: [
      { text: '文档首页', link: '/' },
      { text: '开始使用', link: '/getting-started' },
      { text: '开发指南', link: '/guide/development' },
      { text: '管理员手册', link: '/guide/user-manual' },
    ],
    sidebar: {
      '/guide/': [
        {
          text: '使用文档',
          items: [
            { text: '开发与部署指南', link: '/guide/development' },
            { text: '管理员使用手册', link: '/guide/user-manual' },
          ],
        },
      ],
      '/': [
        {
          text: '快速入口',
          items: [
            { text: '开始使用', link: '/getting-started' },
            { text: 'API 约定', link: '/api' },
            { text: '部署清单', link: '/deployment' },
          ],
        },
      ],
    },
    outline: { level: [2, 3] },
    search: { provider: 'local' },
    footer: {
      message: 'Peanut Admin 公开文档',
    },
  },
})
