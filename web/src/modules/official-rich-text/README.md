# 中文富文本与实时协同临时 Spike

本目录是可整体删除的技术验证，不注册为 Peanut Admin 正式 Module，不接文章 API，也不改变现有文章编辑器。它把原先分开的“中文富文本编辑器”和“实时协同/快照”收敛为同一个最小场景。

## 结论

选择固定版本 `Tiptap/ProseMirror 3.30.0 + Yjs 13.6.32 + Hocuspocus 4.6.0`。编辑器与协同是同一条编辑链：Tiptap 提供新闻稿结构和输入体验，Yjs 同步同一份 ProseMirror 文档，Hocuspocus 负责连接鉴权、服务端确认和最终化前拒写。

| 方案 | 当前证据 | 判断 |
| --- | --- | --- |
| 仓内 `contenteditable` | `official-article` 已有 DOMPurify，但依赖已废弃的 `document.execCommand`，只保存 HTML，没有版本化节点模型或锚点映射 | 只复用清洗边界 |
| Tiptap + Yjs + Hocuspocus | 原生 JSON/Schema、事务映射、IME 和撤销历史；官方 Collaboration 扩展直接把 ProseMirror 节点绑定到 Yjs；Hocuspocus 已验证鉴权、只读重连和自托管 WebSocket | 本 Spike 唯一实现 |
| Lexical + Yjs | 编辑能力与许可证可接受，但仓内未安装，媒体项目仍缺 Vue/Yjs/网关组合证据 | 没有足以抵消第二套实现成本的优势，不实现 |
| ShareDB | 成熟 OT 后端，但需要另一套 operation、富文本绑定和适配器 | 不减少当前场景的代码或风险，不实现 |

## 可复用边界

`peanut.richtext/1` 固定可由 Core 直接采用或薄适配的文档 JSON：

- `schemaVersion`：编辑器 Schema 身份；
- `editorModel`：固定为 `tiptap-prosemirror`；
- `content`：ProseMirror JSON，覆盖标题、段落、列表、链接和 `mediaPlaceholder`；
- `annotations`：评论范围及 `active/invalid`，正文改变导致原引用无法保持时明确失效。

媒体节点只保存稳定 `ref/kind/label`，不保存 URL、上传凭据或媒体系统私有字段。媒体资源管理系统可直接提供同一引用，或把自身 AssetId 薄映射为 `ref`。`peanut.richtext/0` HTML 只做 DOMPurify 清洗后的只读渲染，不自动迁移或写回。

协同适配器只暴露会话授权、Yjs update、服务端确认 Snapshot 和最终化边界。Snapshot 固定 `through_server_sequence / content_digest / editor_schema_version / convergence_state / encoded_state_base64`；它只是可恢复的已确认协同状态，**不是 Revision**。`beginFinalization` pin 同一 Snapshot 并关闭旧连接，`finalize` 只返回该 pin；正式媒体系统以后用它创建不可变 Revision。

## 运行

先取得以下登记资源的项目租约：

- `peanut-admin-rich-text-editor-spike-toolchain`：Node 24.13.0 / pnpm 9.15.6；
- `peanut-admin-rich-text-editor-spike-pnpm-store`：`/private/tmp/peanut-admin-editor-spike-pnpm-store`；
- `peanut-admin-rich-text-editor-spike-npm-registry`：`registry.npmjs.org:443`；
- `peanut-admin-rich-text-editor-spike-http`：`http://127.0.0.1:20181`；
- `peanut-admin-rich-text-editor-spike-collaboration`：`ws://127.0.0.1:20282`；
- `peanut-admin-rich-text-editor-spike-chromium`：唯一真实浏览器聚焦场景。

```sh
pnpm install --frozen-lockfile --store-dir /private/tmp/peanut-admin-editor-spike-pnpm-store
pnpm dev
```

打开 `http://127.0.0.1:20181/?role=alice` 和 `?role=bob` 即为同一合成 Tenant 草稿的两个编辑会话。页面“运行聚焦检查”验证本地编辑合同；最终真实 Chromium 场景另验证中文组合输入、粘贴清洗、协同撤销/重做、两端收敛、断线待同步、重连、撤权拒写、Snapshot pin、旧会话拒写、360px 和基本无障碍。

## 实际验证（2026-08-31）

- `pnpm build` 通过：Vite 3.2.11 转换 95 个模块并生成产物；只出现 500 KiB chunk 体积警告，本 Spike 未为临时页面增加拆包配置。
- 唯一 Chromium 场景未完成：Alice 页面和两个登记监听均成功启动，但 Playwright 命名会话在合并步骤执行期间关闭，CLI 返回 `Session closed`，因此不能把中文组合输入、粘贴、协同收敛、撤权、最终化和 360px 断言写成通过。
- 一次只读诊断确认 `127.0.0.1:20181` 与 `127.0.0.1:20282` 当时仍由联合启动器健康监听，而 Chromium 进程及命名会话已经不存在。按本任务失败预算未重跑；当前状态是“实现已形成、真实浏览器资格未完成”。

## 刻意跳过

- Presence、评论线程、建议模式、工作流和完整产品 UI；
- 数据库/对象存储持久化、跨实例广播、TLS、容量/性能、备份恢复和生产安全资格；
- 正式 Revision/Artifact 写入、自动保存策略和最终化失败恢复；
- 旧文档可编辑迁移、未知 Schema 猜测和正式 Module/Plugin 打包。

内存适配器重启即丢失，Snapshot digest 绑定当前 Yjs 状态而不是跨文档重建的内容规范摘要；只有进入正式采用阶段时才替换为 Core 授权、持久化与 Revision 适配器。

docs-impact：`technical, developer-site`。资源登记只服务此内部可删除 Spike；日常开发数据库、公开命令和 docs-site 使用方式未改变，因此对应通用资源文档目标应逐项 waiver，不投影临时端口或合成会话。
