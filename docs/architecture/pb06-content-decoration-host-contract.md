# PB06 内容与装修 Host 合同

> 状态：Accepted
>
> 应用前置提交：`190e98de2332c1f9dbbedc6ed85c850f93796433`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB06-CONTENT-DECORATION-001`

## 1. 决策与唯一所有权

内容与装修是 Peanut Admin 产品领域，继续由应用 Module 唯一拥有，不迁入核心：

- 应用 Content Module 拥有文章、分类、公开列表/详情、收藏、计数与搜索规则，以及 `pa_article*` schema、管理/会员 HTTP 和三端 UI。
- 应用 Decoration Module 拥有移动首页、个人中心、客服页、PC 首页、系统主题、Tabbar 的 Schema、链接语义、保存事务与消费 DTO，以及 `pa_decorate_*` schema。
- `ProductAssetReferenceService` 是文章与装修产品记录的资源引用边界；`DecorationReadService` 是管理端、API、PC 与 UniApp/H5 的唯一装修读取 DTO。
- 核心只提供已发布的权限/Host/client 原语和未获采用授权的通用设置、文件、任务候选；没有文章、收藏或产品装修 Runtime。本片不 deep import、不增加 override、不复制或修改核心 Runtime。

## 2. 数据与状态合同

| 数据 | 权威语义 | 固定规则 |
|---|---|---|
| `pa_article_cate` | 文章分类 | 分类显示状态不级联文章；仍有文章引用时删除失败并保持两侧不变 |
| `pa_article` | 文章发布记录 | `is_show=1` 才进入公开内容；删除为软删；详情成功读取只增加一次实际浏览量 |
| `pa_article_collect` | 会员收藏状态 | `(member_id, article_id)` 唯一；收藏/取消为同一状态记录的幂等切换 |
| `pa_decorate_page` | 五类装修页面 | `type` 唯一且不可修改；Schema 校验后事务保存，客户端立即读取，无草稿/发布双状态 |
| `pa_decorate_tabbar` | Tabbar 条目 | `position` 唯一；首项固定首页、可见项至少两项，保存后立即读取 |

文章创建/编辑必须引用存在且未软删的分类。分类停用和文章发布状态保持独立，避免产生第二套隐式发布状态机。

## 3. 资源引用与 Provider 边界

素材对象仍由 PB04 的应用 File/Provider Host 拥有；内容记录只拥有对公开资源的引用：

1. 新保存的无 query/fragment 同源 local `/storage/` URL 收敛为 `storage/...` 相对 URI，读取时按当前应用域名补全；带 query/fragment 的完整 URL 保持绝对，避免静默改变签名或片段语义。
2. 新保存的云/CDN/外部 HTTP(S) URL保留完整绝对地址和原始域名，不能随默认存储 Provider 切换而改写。
3. 文章封面与 Tabbar 图标列扩为 `VARCHAR(2048)`；页面装修资源位于现有 JSON 字段，无需第二套 Provider 字段或素材复制。
4. 历史无 provenance 的相对 URI 继续沿用 PB04 `FileService` 兼容语义；本片不猜测并批量迁移旧对象。
5. 文章富文本只转换 `img/video src`，装修只转换固定图片 key；自定义链接、文章目标和小程序目标继续由装修 Schema 单独校验。

## 4. 唯一调用链

```text
文章管理 -> ArticleLogic -> Article/ArticleCate
公开文章/收藏 -> API ArticleLogic -> Article/ArticleCollect

管理端页面/Tabbar保存 -> DecorationPageLogic/DecorationTabbarLogic
  -> DecorationSchemaService -> pa_decorate_*

管理端详情 ─┐
移动/H5 API ├-> DecorationReadService -> DecorationSchemaService(resourcesForRead)
PC API ─────┘
```

管理端不得保留自己的装修格式化副本；PC 和 UniApp/H5 只渲染服务端 DTO，不复制 Schema、链接或发布状态机。

## 5. 权限、事务与失败边界

- 管理接口继续经过 `LoginMiddleware -> AuthMiddleware -> OperationLogMiddleware`；公开文章与装修读取保持既有匿名边界，收藏继续要求会员 token。
- 分类删除在同一事务锁分类与引用文章，任一引用存在即失败关闭。文章新增/编辑在验证层拒绝不存在或已软删分类。
- 装修保存先验证完整 Schema，再锁定固定类型页面并在事务内保存；Tabbar 样式与条目在同一事务替换。
- 同源判断按 scheme、host 和有效端口精确比较；运行环境必须只接受受信代理转发的 Host。PB08A 再决定中性脚手架的 canonical origin 配置，不在本片扩大默认配置范围。
- 资源 URL 归一化失败、JSON 无效、链接目标无效或类型越权时不写入。本片不新增远程资源抓取、对象搬迁、CDN 刷新或草稿审批。

## 6. 精确写集与禁改集

Runtime 与 schema 白名单：

- `server/app/common/service/ProductAssetReferenceService.php`；
- `server/app/common/model/article/Article.php`；
- `server/app/adminapi/validate/article/ArticleValidate.php`；
- `server/app/common/service/decoration/DecorationReadService.php`；
- `server/app/common/service/decoration/DecorationSchemaService.php`；
- `server/app/adminapi/logic/decoration/DecorationPageLogic.php`；
- `server/app/adminapi/logic/decoration/DecorationTabbarLogic.php`；
- `server/database/migrations/20260811-content-asset-reference.sql`。

证据与状态白名单：

- `server/tests/Productization/ContentDecorationHostTest.php`；
- `.github/workflows/ci.yml`，只登记无数据库聚焦测试；
- 本合同、PB03/能力图/应用发布契约/产品化计划、`AGENTS.md`；
- 开发指南、用户手册及其 `docs-site` 镜像，只同步资源引用和立即生效边界。

禁止修改核心仓、`vendor/`、`node_modules/`、`init.sql`、路由/菜单、前端页面、封存证据、PB07 通知/支付/OAuth/渠道、PB08A 品牌输入或 SaaS 设计。

## 7. 测试 owner 与一次最低验收

`PB06-CONTENT-DECORATION-001` 由 `server/tests/Productization/ContentDecorationHostTest.php` 拥有，不连接数据库、不写文件。一次运行证明：

1. 同源 local、云/CDN、大小写 scheme、不同端口、相似恶意域名和相对路径的资源引用规则；文章/装修不再调用会剥离当前云 Provider 域名的旧写转换。
2. 文章分类存在性、分类占用删除失败关闭、文章生命周期唯一 writer 与收藏/装修唯一键仍存在。
3. 管理端、API、PC 与 UniApp/H5 装修读取都经过同一 `DecorationReadService`，不存在第二份读取格式化。
4. 只读绑定封存 C01/C02 的分类、文章、收藏、状态/计数/软删证据和 DE01-DE02 的移动、Tabbar、PC 即时消费与恢复证据。
5. 应用内容/装修 owner 不 deep import 核心 Runtime。

执行命令固定为：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/ContentDecorationHostTest.php
```

实现 owner 另运行一次白名单 PHP lint、迁移静态检查和最终 `git diff --check`。不执行数据库写入、封存 C01/C02/DE01-DE02、LikeAdmin parity、全量 API/Web、核心候选或浏览器。

## 8. 停止线

通过只表示应用内容/装修产品 owner、唯一资源引用与装修读取边界、分类/发布规则和测试 owner 已固定。历史无 provenance 相对资源仍依赖原 Provider 配置，其不可恢复来源不得猜测；canonical origin 配置留给 PB08A。它不批准核心 Settings/File/Task 候选消费，不新增 CMS 工作流、对象迁移、全文检索、审核/版本发布，不开始 PB07、PB08A 或 SaaS。

## 9. 实施证据

- CodeGraph 限定图谱与三组静态审计确认文章/分类、收藏/计数、装修写入均只有应用 Runtime，管理端/API/PC/UniApp 的装修结果收敛到 `DecorationReadService`；核心没有产品内容或装修实现。
- PHP 8.3 下七个 Runtime 文件和新增测试的一次白名单 lint 全部通过；迁移静态核对确认文章封面和 Tabbar 两类图标均可保存完整绝对 URL。
- `PB06-CONTENT-DECORATION-001` 一次通过：同源 local、云/CDN、scheme 大小写、端口和相似域名边界，分类存在/占用规则，唯一 writer/读取 DTO 与 schema 唯一键均成立。
- 同次验收只读绑定封存 C01/C02 的文章、分类、收藏、计数、状态/软删/恢复证据和 DE01-DE02 的移动、Tabbar、PC 即时消费与恢复证据；没有重跑这些流程。
- 测试未连接数据库、写文件或启动浏览器；未执行 LikeAdmin parity、全量 API/Web、核心候选或 PB08A 品牌检查。
- 核心仓和既有 `.playwright-cli/` 未触碰；应用 `init.sql`、路由/菜单、前端页面、PB07、PB08A 品牌输入及 SaaS 设计未修改。
