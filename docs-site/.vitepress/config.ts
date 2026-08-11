import { defineConfig } from 'vitepress'
import brandManifest from '../generated/brand.json'

const canonicalUrl = process.env.PEANUT_DOCS_SITE_URL?.trim()
const { website } = brandManifest

export default defineConfig({
  lang: 'zh-CN',
  title: website.name,
  description: website.slogan,
  cleanUrls: true,
  lastUpdated: true,
  sitemap: canonicalUrl ? { hostname: canonicalUrl } : undefined,
  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/brand/favicon.svg' }],
    ['meta', { name: 'theme-color', content: '#165DFF' }],
  ],
  themeConfig: {
    logo: '/brand/logo.svg',
    siteTitle: website.name,
    nav: [
      { text: '产品', link: '/' },
      { text: '能力与场景', link: '/capabilities' },
      {
        text: '文档',
        items: [
          { text: '文档门户', link: '/guide/' },
          { text: '快速开始', link: '/getting-started' },
          { text: '开发指南', link: '/guide/development' },
          { text: '部署与升级', link: '/deployment' },
          { text: 'API 与扩展', link: '/api' },
          { text: '管理员手册', link: '/guide/user-manual' },
        ],
      },
      {
        text: '版本',
        items: [
          { text: '版本与发布', link: '/releases' },
          { text: '许可证与告知', link: '/legal' },
        ],
      },
      { text: 'GitHub', link: website.github_url },
    ],
    sidebar: {
      '/guide/': [
        {
          text: '文档门户',
          items: [
            { text: '文档首页', link: '/guide/' },
            { text: '快速开始', link: '/getting-started' },
            { text: '开发指南', link: '/guide/development' },
            { text: '部署与升级', link: '/deployment' },
            { text: 'API 与扩展', link: '/api' },
            { text: '管理员使用手册', link: '/guide/user-manual' },
            { text: '版本与发布', link: '/releases' },
            { text: '许可证与告知', link: '/legal' },
          ],
        },
      ],
      '/': [
        {
          text: '产品',
          items: [
            { text: '产品首页', link: '/' },
            { text: '能力与场景', link: '/capabilities' },
          ],
        },
        {
          text: '使用文档',
          items: [
            { text: '文档门户', link: '/guide/' },
            { text: '开始使用', link: '/getting-started' },
            { text: '开发指南', link: '/guide/development' },
            { text: '部署与升级', link: '/deployment' },
            { text: 'API 与扩展', link: '/api' },
            { text: '管理员手册', link: '/guide/user-manual' },
            { text: '版本与发布', link: '/releases' },
            { text: '许可证与告知', link: '/legal' },
          ],
        },
      ],
    },
    outline: { level: [2, 3] },
    search: { provider: 'local' },
    socialLinks: [{ icon: 'github', link: website.github_url }],
    footer: {
      message: website.slogan,
      copyright: `© ${new Date().getFullYear()} ${website.copyright}`,
    },
  },
})
