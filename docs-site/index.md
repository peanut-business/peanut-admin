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
  - icon: '01'
    title: 管理端基线
    details: Vue 3 与 Element Plus 管理端，菜单、角色、按钮/API 权限和操作审计形成闭环。
  - icon: '02'
    title: PC 与移动端
    details: Nuxt 3 PC、UniApp/H5 共用无 UI client 契约，同时保留各端导航与平台能力。
  - icon: '03'
    title: 清晰的应用边界
    details: 产品领域由应用 Module 唯一拥有，公共 Runtime 只通过两个公开核心包消费。
  - icon: '04'
    title: 可覆盖品牌
    details: 一个 bootstrap manifest 提供克隆默认值，安装后由网站配置 Runtime 统一管理四端品牌。
  - icon: '05'
    title: 可重复交付
    details: 支持空库安装、前滚迁移、三端静态构建和 Docker Compose 生产发布。
  - icon: '06'
    title: 安全默认值
    details: 首次安装显式提供管理员密码，敏感配置不进入模板、URL、日志或响应。
---

<div class="home-proof">
  <div class="home-proof-copy">
    <p class="eyebrow">CURRENT CANDIDATE · 2.0.0</p>
    <h2>从可运行模板，到可维护的产品 Host</h2>
    <p>Standalone 与多租户双模式、实例内 Tenant 平台管理、可信租户会话及代表业务隔离，已经收进同一条可验证的交付链。</p>
    <div class="home-proof-actions">
      <a href="/capabilities">查看能力与边界 <span>→</span></a>
      <a href="/deployment">部署与升级</a>
      <a href="/releases">版本状态</a>
    </div>
  </div>
  <div class="home-proof-metrics" aria-label="当前产品基线摘要">
    <div><strong>4</strong><span>管理端、PC、H5 与 UniApp</span></div>
    <div><strong>2</strong><span>Standalone / 多租户部署模式</span></div>
    <div><strong>1</strong><span>统一品牌与应用 Runtime</span></div>
    <div><strong>0</strong><span>仓库共享默认密码</span></div>
  </div>
</div>

<div class="home-section-heading">
  <p class="eyebrow">START HERE</p>
  <h2>选择你的入口</h2>
  <p>从第一次安装，到应用扩展和日常管理，每种角色都能直接抵达需要的上下文。</p>
</div>

<div class="doc-entry-grid">
  <a class="doc-entry" href="/getting-started">
    <span class="doc-entry-index">01</span>
    <span class="doc-entry-copy"><strong>第一次使用</strong><span>从依赖、空库安装到首次登录，完成最短成功路径。</span></span>
    <span class="doc-entry-arrow">↗</span>
  </a>
  <a class="doc-entry" href="/guide/development">
    <span class="doc-entry-index">02</span>
    <span class="doc-entry-copy"><strong>开发与扩展</strong><span>理解应用/核心边界、覆盖 Host、数据库迁移和客户端约定。</span></span>
    <span class="doc-entry-arrow">↗</span>
  </a>
  <a class="doc-entry" href="/guide/user-manual">
    <span class="doc-entry-index">03</span>
    <span class="doc-entry-copy"><strong>后台管理员</strong><span>按模块查找业务操作、权限范围和安全注意事项。</span></span>
    <span class="doc-entry-arrow">↗</span>
  </a>
</div>

<div class="home-flow">
  <div class="home-section-heading">
    <p class="eyebrow">DELIVERY PATH</p>
    <h2>从干净基线创建，再按应用边界演进</h2>
    <p>2.0.0 不接管 1.x 历史状态；新应用从空库、原生身份和明确所有权开始。</p>
  </div>
  <div class="home-flow-grid">
    <div><span>01</span><strong>创建</strong><p>从中性模板生成独立应用。</p></div>
    <div><span>02</span><strong>扩展</strong><p>业务留在 app-owned 边界。</p></div>
    <div><span>03</span><strong>迁移</strong><p>只追加 2.0.0 基线后的变更。</p></div>
    <div><span>04</span><strong>交付</strong><p>固定制品、迁移与运行证据。</p></div>
  </div>
</div>

<div class="home-release">
  <div>
    <p class="eyebrow">RELEASE BOUNDARY</p>
    <h2>发布身份清楚，产品边界同样清楚</h2>
    <p>Peanut Admin <code>2.0.0</code> 已完成 fresh-only 正式源码发布；生产部署仍按独立工作流执行。Composer <code>peanut-admin/core</code> 与 npm <code>@peanut-admin/admin</code> 是仅有的公开运行依赖；DCS 业务模块和跨应用运营平台不进入本产品。</p>
  </div>
  <a href="/releases">查看版本与发布 <span>→</span></a>
</div>
