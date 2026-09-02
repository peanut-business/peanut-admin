# official.rich-text

`official.rich-text` 是 Peanut Admin 的独立结构化富文本 Module。唯一身份由
`server/app/Modules/Official/RichText/module.json` 声明；本目录是对应的前端包
`@peanut-admin/official-rich-text`。

## 边界

- `./document`：`peanut.richtext/1` 文档 JSON、Tiptap/ProseMirror extensions、粘贴清洗、媒体占位和评论锚点映射；
- `./collaboration`：Yjs + Hocuspocus 的会话授权、服务端确认 Snapshot 和最终化适配器；
- `./`：Module 前端 contribution。当前不贡献菜单或路由，由真实消费 Module 显式依赖并嵌入编辑器；
- `index.html`、`src/main.ts` 与 `server.mjs`：只用于模块开发预览和聚焦检查，不是第二套产品 Runtime。

媒体节点只保存稳定 `ref/kind/label`，不保存 URL、上传凭据或存储私有字段。Snapshot 固定
`through_server_sequence / content_digest / editor_schema_version / convergence_state /
encoded_state_base64`；它是服务端已确认协同状态，不是业务 Revision。

Article 尚未切换到本 Module。正式采用时由 Article 或媒体项目声明 Module 依赖并负责业务
Revision、审核工作流和持久化；本 Module 不拥有文章表、文件表、菜单、权限或 HTTP API。

## 开发预览

先 claim 以下登记资源：

- `peanut-admin-rich-text-module-toolchain`；
- `peanut-admin-rich-text-module-pnpm-store`；
- `peanut-admin-rich-text-module-npm-registry`；
- `peanut-admin-rich-text-module-preview`：`http://127.0.0.1:20181`；
- `peanut-admin-rich-text-module-collaboration`：`ws://127.0.0.1:20282`；
- `peanut-admin-rich-text-module-chromium`。

```sh
pnpm install --frozen-lockfile --store-dir /private/tmp/peanut-admin-rich-text-module-pnpm-store
pnpm dev
```

打开 `http://127.0.0.1:20181/?role=alice` 和 `?role=bob` 可验证同一合成 Tenant
稿件。页面聚焦检查覆盖粘贴清洗、结构 JSON、锚点失效、撤销/重做、旧版只读和协同连接。

## 限制

- 开发适配器使用内存状态，重启即丢失；
- 尚未提供持久化、跨实例广播、TLS、容量/性能、备份恢复或生产安全资格；
- 不包含 Presence UI、评论线程、建议模式、审核工作流和旧文档可编辑迁移；
- Tiptap/ProseMirror `3.30.0`、Yjs `13.6.32`、Hocuspocus `4.6.0` 由应用唯一依赖图和 lock 固定。
