# 中文富文本编辑器临时 Spike

本目录是可整体删除的技术验证，不注册为 Peanut Admin 正式 Module，不接文章 API，也不改变现有文章编辑器。

## 结论

选择固定版本 `Tiptap/ProseMirror 3.30.0`，不做 Lexical 并列实现。理由：

| 方案 | 当前证据 | 判断 |
| --- | --- | --- |
| 仓内 `contenteditable` | `official-article` 已有零依赖实现和 DOMPurify，但依赖已废弃的 `document.execCommand`，只保存 HTML，没有版本化节点模型或锚点映射 | 只复用清洗边界，不继续扩写 |
| Tiptap/ProseMirror | Vue 3/Vite 可直接使用；原生 JSON、Schema、事务位置映射、输入法处理和撤销历史；媒体节点可扩展；媒体项目既有调研给出未来 Yjs/Hocuspocus 的最短采用路径 | 本 Spike 唯一实现 |
| Lexical | 能力和许可证可接受，但仓内未安装；媒体项目调研仍要求补 Vue/Yjs/网关组合证据，当前没有能抵消第二套实现成本的优势 | 不实现 |

## 文档边界

`peanut.richtext/1` 固定以下可被 Core 直接采用或薄适配的 JSON：

- `schemaVersion`：文档 Schema 身份，不绑定数据库版本；
- `editorModel`：当前节点语义来源；
- `content`：ProseMirror JSON，覆盖标题、段落、列表、链接和 `mediaPlaceholder`；
- `annotations`：本 Spike 只保存一个评论范围及 `active/invalid`。正文改变后无法保持原引用文字时明确变为 `invalid`，不静默漂移。

媒体节点只保存稳定 `ref/kind/label`，不保存 URL、上传凭据或媒体系统私有字段。媒体资源管理系统可以直接提供同一引用，或在边界处把自身 AssetId 薄映射为 `ref`。`peanut.richtext/0` HTML 只做 DOMPurify 清洗后的只读渲染，不自动迁移或写回。

## 运行

登记资源：

- `peanut-admin-rich-text-editor-spike-toolchain`（development，本机 Node 24.13.0 / pnpm 9.15.6）；
- `peanut-admin-rich-text-editor-spike-pnpm-store`（development，`/private/tmp/peanut-admin-editor-spike-pnpm-store`）；
- `peanut-admin-npm-registry`（development，`registry.npmjs.org:443`）；
- `peanut-admin-local-development-admin`（development，`127.0.0.1:20181`）。

取得项目资源租约后运行：

```sh
pnpm install --frozen-lockfile --store-dir /private/tmp/peanut-admin-editor-spike-pnpm-store
pnpm dev
```

打开 `http://127.0.0.1:20181/`。页面的“运行聚焦检查”覆盖粘贴清洗、结构 JSON、评论锚点失效、撤销/重做和旧版只读；中文组合输入与 360px 布局须在真实 Chromium 的同一聚焦场景中验证。

## 刻意跳过

- 协同传输、Yjs/CRDT、Presence、自动保存和最终化握手；
- 评论线程、建议模式、工作流、文章 API 与完整产品 UI；
- 旧文档可编辑迁移、未知 Schema 猜测和生产 Module/Plugin 打包。

只有进入正式采用阶段时，才把文档 Schema 和渲染器移入 Core，并另行资格验证协同与迁移；本 Spike 不提前搭这些层。
