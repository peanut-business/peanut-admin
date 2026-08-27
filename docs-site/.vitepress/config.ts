import { defineConfig } from 'vitepress'
import brandManifest from '../generated/brand.json'

const canonicalUrl = process.env.PEANUT_DOCS_SITE_URL?.trim()
const { website } = brandManifest

const guide = [
  { text: '文档首页', link: '/guide/' },
  { text: '快速开始', link: '/getting-started' },
  { text: '能力目录', link: '/capabilities' },
  { text: '核心概念', link: '/guide/concepts' },
]

const development = [
  { text: '开发总览', link: '/guide/development' },
  { text: '后端开发', link: '/guide/backend' },
  { text: '前端开发', link: '/guide/frontend' },
  { text: 'Module 开发', link: '/guide/module-development' },
  { text: '数据、权限与多租户', link: '/guide/data-permissions-tenancy' },
]

const delivery = [
  { text: '测试与排错', link: '/guide/testing' },
  { text: '部署与升级', link: '/guide/deployment-upgrade' },
  { text: '版本与发布', link: '/releases' },
]

const reference = [
  { text: '参考入口', link: '/reference' },
  { text: 'API 与扩展', link: '/api' },
  { text: '文档事实来源', link: '/reference/source-map.generated' },
  { text: '许可证与告知', link: '/legal' },
]

export default defineConfig({
  srcExclude: [
    'architecture/identity-and-tenancy.md',
    'architecture/module-execution-context.md',
    'architecture/official-module-qualification.md',
    'demo-access.md',
    'deployment.md',
    'guide/reading-guide.md',
    'guide/release-and-application-lifecycle.md',
    'guide/scaffold-upgrade.md',
    'guide/user-manual.md',
    'platform.md',
    'product-status.md',
    'troubleshooting.md',
  ],
  lang: 'zh-CN',
  title: `${website.name} 开发者文档`,
  description: 'Peanut Admin 面向应用开发者、Module 作者与运维人员的任务型文档。',
  cleanUrls: true,
  lastUpdated: process.env.VITEPRESS_DISABLE_GIT !== 'true',
  ignoreDeadLinks: false,
  sitemap: canonicalUrl ? { hostname: canonicalUrl } : undefined,
  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/brand/favicon.svg' }],
    ['meta', { name: 'theme-color', content: '#2457E6' }],
  ],
  themeConfig: {
    logo: '/brand/logo.svg',
    siteTitle: `${website.name} 文档`,
    nav: [
      { text: '文档', link: '/guide/' },
      { text: '概念', link: '/guide/concepts' },
      { text: '开发', items: development },
      { text: '交付', items: delivery },
      { text: '参考', items: reference },
      { text: 'GitHub', link: website.github_url },
    ],
    sidebar: {
      '/guide/': [
        { text: '开始', items: guide },
        { text: '开发', items: development },
        { text: '验证与交付', items: delivery },
        { text: '参考', items: reference },
      ],
      '/reference/': [
        { text: '参考', items: reference },
        { text: '下一步', items: guide },
      ],
      '/': [
        { text: '开始', items: guide },
        { text: '开发', items: development },
        { text: '验证与交付', items: delivery },
        { text: '参考', items: reference },
      ],
    },
    outline: { level: [2, 3] },
    search: { provider: 'local' },
    socialLinks: [{ icon: 'github', link: website.github_url }],
    footer: {
      message: '开发者文档是权威事实的公开投影，不是第二套产品状态。',
      copyright: `© ${new Date().getFullYear()} ${website.copyright}`,
    },
  },
})
