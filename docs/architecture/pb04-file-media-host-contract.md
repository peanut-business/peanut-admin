# PB04-04 文件与素材 Host 合同

> 状态：Accepted
>
> 应用前置提交：`22d06e3df7a96b5489f3900ecccf6848f30e41c1`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB04-FILE-MEDIA-HOST-001`

## 1. 决策与唯一所有权

PB04-04 保留 Peanut Admin 应用文件/素材 Runtime，不切换核心 File And Media：

- 应用拥有 `pa_file_cate`、`pa_file`、图片/视频/普通文件分类与选择器、LikeAdmin 上传/移动/重命名/删除语义、公开 URL、云凭据、ThinkPHP HTTP/UI。
- 应用 `Driver` 继续拥有 local/qiniu/aliyun/qcloud Provider 装配；核心不读取应用 `pa_config(type=storage)` 或云厂商凭据。
- 核心 File And Media 仅是 Composer `peanut-admin/core` 内部候选边界，不是第三个包；应用不得 deep import。
- 当前没有核心 File And Media 下游采用授权，也没有第二条应用素材 Runtime；本切片不新增 override slot、不双写表。

## 2. 不等价事实

| 维度 | Peanut Admin 应用 | 核心 File And Media 候选 |
|---|---|---|
| schema | `pa_file_cate` + `pa_file`；分类、来源、类型、URI、storage、软删 | Tenant-owned `pa_file_object`；opaque key、SHA-256、revision、ready/archived |
| 可见性 | local public root 或云端公开 domain，返回可直接消费 URL | 默认 Tenant-private、Web root 外存储、授权 download/delivery grant |
| 生命周期 | 删除素材时删除对象并软删记录；分类可级联子树 | archive 元数据，默认保留对象；retention deletion 延后 |
| Provider | local、七牛、阿里云、腾讯 COS 的应用适配器 | `local-private` 开发适配器和能力声明；生产 object storage 未宣称 |
| 安全/API | LikeAdmin 管理 API 与扩展名/大小白名单 | MIME/哈希检测、Tenant/Permission、私有单次 token、ETag/审计语义 |

核心候选明确不提供当前产品所需的永久公开 URL 和云 SDK，且候选树不构成下游采用批准。因此不能用路径或 repository adapter 假装等价。

## 3. 应用唯一链与不变量

唯一上传链：

```text
UploadController → UploadService → storage Driver/engine
  → pa_file → FileService URL
```

唯一素材链：

```text
FileController → FileLogic / FileCateLogic
  → File / FileCate → pa_file / pa_file_cate
```

固定已验收规则：

1. 图片、视频、文件分别使用白名单和大小上限；上传类型与目标分类类型一致，错误上传不落记录。
2. 分类树同类型；父分类查询包含后代，未分组为 `cid=0`；来源/名称/分类/类型可组合筛选。
3. 移动只允许未分组或同类型分类；重命名必填且不超过 20 字符。
4. 素材删除按记录 `storage` 选择原 Provider；对象删除失败时数据库回滚并返回失败。分类删除先完成子树素材/对象退出，再软删分类。
5. `pa_file.storage` 是素材对象 Provider 的权威 provenance。素材列表和上传响应必须按该字段拼 URL，不能按当前默认 Provider 误拼旧云对象。
6. local URI 继续使用 `storage/` 前缀与当前站点；显式云 Provider 缺少 domain 时返回空 URL，不能伪造站点 URL。没有 provenance 的旧通用 URI 保留当前默认 Provider 兼容语义。
7. 租户拥有的头像、文章/装修资源、二维码和富文本中的素材引用，在写入前必须通过当前
   TenantContext 校验 `tenants/v1/<tenant_id>/...` 对象命名空间；跨租户对象引用直接拒绝。
   外部 CDN/微信等非应用对象 URL 仍可保留为外部引用。

## 4. Provider、Host、数据升级与边界

- `server/app/common/service/storage/Driver.php` 是应用 Provider Host；不新增核心 override key。
- 本切片不改 schema。M02 已证明 `storage` 列与索引存在；历史空 `storage` 记录仍按 `storage/` 前缀推断 local，否则沿用默认 Provider 兼容。
- 切换默认 Provider 不迁移旧对象。`pa_file.storage` 记录必须继续按原 Provider domain 解析，原配置不得在对象迁移前删除。
- 网站配置、文章、渠道、装修等只保存 URI 而没有 Provider 字段的引用，不在本切片猜测归属；PB06/PB07 必须按各自表 owner 决定引用 provenance、迁移与回滚。
- 所有管理写接口继续经过 Login/Auth/OperationLog；公开 URL 不等于匿名上传权限。

## 5. 精确写集与禁改集

Runtime 白名单：

- `server/app/common/service/FileService.php`；
- `server/app/common/model/file/File.php`；
- `server/app/common/service/UploadService.php`。

证据与状态白名单：

- `server/tests/Productization/FileMediaHostTest.php`；
- `.github/workflows/ci.yml`，仅登记聚焦测试；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/architecture/core-application-capability-graph.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`。

禁止修改 File/FileCate Controller/Logic、Provider SDK 实现、存储配置页面、数据库、菜单/路由、前端素材页/选择器、核心仓、依赖与其他产品领域。发现删除或上传语义与封存 M02 证据冲突时停止并另立 Runtime 合同。

## 6. 测试 owner 与最低验收

M02/S01 已经一次验证三类上传、错误不写、分类/筛选/移动/重命名、素材与存储同步删除、权限、选择器、无效 Provider 拒绝和配置恢复：

- `output/playwright/m02/api-db-summary.json`；
- `output/playwright/m02/browser-summary.json`；
- `output/playwright/s01/core-summary.json`。

这些行为已封存，PB04 不重复上传、数据库或浏览器流程。`PB04-FILE-MEDIA-HOST-001` 只执行一次无外部写入聚焦测试，证明：

1. 三份封存证据仍通过且夹具/配置已清理恢复；
2. local、默认云 Provider、显式原 Provider 与绝对 URL 的映射结果；
3. 显式未知/无 domain Provider fail-closed 为空 URL；
4. File model 和上传响应都把 `storage` 传给唯一 FileService；删除仍按 `storage` 选 Driver；
5. 应用文件 Runtime 未导入核心 FileMedia 或 `pa_file_object`。

执行命令：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/FileMediaHostTest.php
```

实现 owner 另运行一次白名单 PHP lint 与一次最终 `git diff --check`。不重跑 M02/S01、LikeAdmin parity、数据库/API、Web build/typecheck 或浏览器。

## 7. 停止线

通过只表示应用素材唯一 Runtime、Provider provenance URL、核心未消费边界和测试 owner 已固定。它不批准核心 File And Media 下游采用，不宣称私有交付、病毒扫描、CDN、缩略图或对象迁移，不提前修改文章、渠道或装修引用，也不开始 PB04 任务/运维。

## 8. 实施证据

- 应用 CodeGraph 两次限定图谱确认上传、Provider、素材/分类与删除只有一条应用 Runtime；核心只读合同证明其是 Tenant-private、archive/delivery 模型且没有下游采用授权。
- PHP 8.3 下三个 Runtime 文件和新增测试的一次聚焦 lint 全部通过。
- `PB04-FILE-MEDIA-HOST-001` 一次通过：封存 M02/S01 证据、零夹具/配置恢复、local/默认云/显式原 Provider/未知 Provider URL 和唯一 wiring 均成立。
- 测试没有上传文件、写数据库、调用云 SDK 或启动浏览器；未重复 M02/S01 或 LikeAdmin parity。
- 核心仓和既有 `.playwright-cli/` 未触碰；应用 schema、Provider SDK、素材页面与产品引用未修改。
