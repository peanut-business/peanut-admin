---
layout: home

hero:
  name: Peanut Admin
  text: 全端管理应用脚手架
  tagline: 用一套清晰边界连接管理端、PC、H5 与 UniApp，保留可覆盖的品牌、业务模块和部署 Host。
  image:
    src: /brand/logo.svg
    alt: Peanut Admin
  actions:
    - theme: brand
      text: 5 分钟快速开始
      link: /getting-started
    - theme: alt
      text: 浏览文档
      link: /guide/
    - theme: alt
      text: GitHub
      link: https://github.com/peanut-business/peanut-admin

features:
  - icon: 🧭
    title: 管理端基线
    details: Vue 3 与 Element Plus 管理端，菜单、角色、按钮/API 权限和操作审计形成闭环。
  - icon: 🖥️
    title: PC 与移动端
    details: Nuxt 3 PC、UniApp/H5 共用无 UI client 契约，同时保留各端导航与平台能力。
  - icon: 🧱
    title: 清晰的应用边界
    details: 产品领域由应用 Module 唯一拥有，公共 Runtime 只通过两个公开核心包消费。
  - icon: 🎨
    title: 可覆盖品牌
    details: 一个 bootstrap manifest 提供克隆默认值，安装后由网站配置 Runtime 统一管理四端品牌。
  - icon: 🚀
    title: 可重复交付
    details: 支持空库安装、前滚迁移、三端静态构建和 Docker Compose 生产发布。
  - icon: 🔐
    title: 安全默认值
    details: 首次安装显式提供管理员密码，敏感配置不进入模板、URL、日志或响应。
---

<div class="home-proof">
  <p class="eyebrow">CURRENT BASELINE</p>
  <h2>从可运行模板，到可维护的产品 Host</h2>
  <p>Peanut Admin 已完成管理端、会员与财务、内容与装修、通知、支付、OAuth/渠道等应用领域收口。当前文档只描述仓库中已经交付的能力；多租户 SaaS 仍是后续独立阶段。</p>
  <div class="home-proof-actions">
    <a href="/capabilities">查看能力与边界</a>
    <a href="/deployment">部署与升级</a>
    <a href="/releases">版本状态</a>
  </div>
</div>

## 选择你的入口

<div class="doc-entry-grid">
  <a class="doc-entry" href="/getting-started">
    <strong>第一次使用</strong>
    <span>从依赖、空库安装到首次登录，完成最短成功路径。</span>
  </a>
  <a class="doc-entry" href="/guide/development">
    <strong>开发与扩展</strong>
    <span>理解应用/核心边界、覆盖 Host、数据库迁移和客户端约定。</span>
  </a>
  <a class="doc-entry" href="/guide/user-manual">
    <strong>后台管理员</strong>
    <span>按模块查找业务操作、权限范围和安全注意事项。</span>
  </a>
</div>

## 发布边界

当前应用仍处于产品化正式基线收尾阶段。Composer `peanut-admin/core` 与 npm `@peanut-admin/admin` 是仅有的公开运行依赖；内部领域目录不是独立包。PB08B 正式候选集成验收已经完成，但许可证/provenance 门禁与 PB09 发布尚未完成，因此本站不会把当前状态描述为正式 `1.0.0`，也不会宣称 SaaS 已实现。
