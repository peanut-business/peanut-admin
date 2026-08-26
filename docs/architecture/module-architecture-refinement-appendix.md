# Peanut Admin 模块架构细化设计增补

本附录不含实现代码，仅细化 §11 的 4 个黄项。

本附录以 `docs/architecture/optimized-module-architecture-plan.md` 的 §8、§9、§11 为上位约束。
下文标为“新增契约”的名称是后续 D/E 阶段要实现的目标合同，不表示仓库当前已经存在；其余代码
事实均按当前工作树核对并给出 `文件:行`。任何包索引、lock、安装记录或页面状态都只能是
`module.json` 的派生结果，不能成为模块能力、依赖、权限或前端组件的第二真值。

## D1. 自包含 `.tar` 单模块包 / 多模块能力包的内部结构规范

### 【设计】

#### 1. 包是“项目根 overlay”，不是另一套源码布局

`.tar` 内的安装载荷直接使用项目根相对路径，不增加 `<package>/payload/` 之类的中间层。这样同一条
路径既是包内路径，也是校验通过后要落到工作区的目标路径：

- 包级派生 manifest 固定为 `plugins/<package-key>/plugin.json`。当前生成器已经把 manifest 写到这个
  位置（`server/app/platform/service/plugin/PluginArtifactWriter.php:35-38`），lock 也登记该相对路径及
  `manifest_sha256`（`server/app/platform/service/plugin/PluginArtifactWriter.php:74-84`）。
- 每个 Module 的唯一源 manifest 固定为其后端根下的 `module.json`。加载器也只从模块根读取这一份
  文件（`server/vendor/peanut-admin/core/kernel/src/Module/ManifestLoader.php:11-34`）。包内不得在
  `web/`、`META-INF/` 或 bundle 根复制 `module.json`。
- 后端路径严格由 module key 派生：例如 `acme.article` 对应
  `server/app/Modules/Acme/Article/`；前端路径对应 `web/src/modules/acme-article/`。现有 Host 构造把
  frontend root 固定为 `web/src/modules`（`server/app/platform/service/plugin/PluginModuleRegistryFactory.php:92-100`），
  `ModuleHostLayout` 再分别用 PascalCase key 分段和 key slug 派生两条路径
  （`server/vendor/peanut-admin/core/kernel/src/Module/ModuleHostLayout.php:25-43`）；slug 当前就是把 `.` 替换为
  `-`（`server/vendor/peanut-admin/core/kernel/src/Module/ModuleKey.php:38-41`）。
- `module.json.frontend.entry` 不是可自由选择的第二条路径真值。凡声明前端入口的 Module，preflight 必须
  计算 `ModuleHostLayout::frontendRelativePath(module key) + "contribution.ts"`，并要求 manifest 中的
  `frontend.entry` 与该结果**逐字符相等**；不做大小写、斜杠或相对段归一化后再接受。fixture 与 Article
  当前分别声明 `web/src/modules/fixture-delivery-record/contribution.ts` 和
  `web/src/modules/official-article/contribution.ts`
  （`server/app/Modules/Fixture/DeliveryRecord/module.json:20-22`、
  `server/app/Modules/Official/Article/module.json:20-22`）。因此该字段只是可校验的派生结果；包不得新增
  第二份前端组件表。

`plugin.json` 是从一个或多个 `module.json` 及对应 Composer/npm 文件生成的包索引，不是手工维护的
模块 manifest。它沿用现有 schema 的 `schema_version`、`key`、`version`、`source`、`composer`、
`npm`、`frontend`、`modules` 字段（`server/resources/schemas/plugin.schema.json:7-75`）。Module 依赖只
读取 `module.json.dependencies`；该字段当前是 `{module_key, version}` 对象数组
（`server/vendor/peanut-admin/core/kernel/resources/schemas/module-manifest.schema.json:35-45`），
bundle 不复制一份依赖图。

#### 2. 完整性与来源校验

每个包必须包含下列机器文件；后两者是“新增契约”，只服务于传输校验，不进入安装后的模块真值：

| 文件/值 | 规范 |
| --- | --- |
| `plugins/<package-key>/plugin.json` | 沿用现有 schema；`source.type=canonical-contents`、`source.reference=canonical-plugin-contents-v1`，`source.sha256` 是所有后端/前端模块子树的规范内容摘要。现有摘要算法按相对路径排序后计算 `path + NUL + file_sha256 + LF`（`server/app/platform/service/plugin/PluginArtifactWriter.php:181-198`）。 |
| `META-INF/files.sha256` | UTF-8、LF、按字节序升序；每行是 `<64位小写hex><两个空格><项目根相对路径>`。覆盖 `plugin.json` 和全部可安装载荷，不覆盖 `META-INF/files.sha256` 自身及签名文件。重复路径、反斜杠、绝对路径、空段、`.`、`..`、软/硬链接、设备文件均拒绝。 |
| `META-INF/signatures/<key-id>.json` | 可选的来源证明；字段固定为 `schema_version: 1`、`algorithm: "ed25519"`、`key_id`、`package_key`、`package_version`、`inventory_sha256`、`signature_base64`。签名输入是 `sha256(META-INF/files.sha256 原始字节)` 的 32 字节结果；`inventory_sha256` 必须与其小写 hex 一致。公钥只可由宿主受信任密钥解析器按 `key_id` 提供，包内公钥不能自证可信。 |
| `.tar` 摘要 | archive 自身不能安全地把自己的摘要写回包内；`module:pack`/`bundle:pack` 必须在封包完成后打印 `sha256:<64位小写hex>`，CLI 安装参数或 D3 的 HTTP `expected_sha256` 从可信渠道带入并在解包前比较。 |

来源判定采用单一 fail-closed 规则：安装方必须获得“可信渠道给出的 archive SHA-256 pin”或“宿主信任
锚验证通过的 Ed25519 签名”至少一项；两项都提供时必须都通过。签名不是第二套模块身份，它只证明
同一份 `plugin.json` 和载荷 inventory 的来源。现有 resolver 已经会再次核对 manifest digest、
Composer/npm/前端文件身份和规范内容摘要（`server/app/platform/service/plugin/PluginLockResolver.php:41-95`），
包安装必须复用同样的校验语义，不能降级为“能解压就安装”。

校验顺序固定如下，前一步失败不得产生工作区或数据库写入：

1. 对原始 `.tar` 计算 SHA-256 并核对 pin（若提供）；
2. 只列目录，做路径、成员类型、重复项、大小/数量上限检查；
3. 解到项目外的同文件系统临时目录，不直接覆盖工作区；
4. 核对 `META-INF/files.sha256` 的全覆盖、无多余载荷和逐文件摘要；
5. 验签（若提供）并核对 signer trust；
6. 校验 `plugin.json` schema、package key/version、每个 `module.json` schema、key 与后端派生路径，并
   逐字符断言 `frontend.entry === web/src/modules/<module-key slug>/contribution.ts`；再校验 Composer/npm
   identity、`source.sha256`。同时断言每个权限 key（包括 list/read 类）都以所属 module key 加 `.` 为
   前缀，菜单 `required_permission` 和后端路由引用完全一致；不接受旧扁平 key 与新 key 并存；
7. 从各自 `module.json.dependencies` 做完整依赖与版本约束检查，按依赖拓扑形成安装计划；
8. 核对目标路径不存在非同一 digest 内容后，才进入安装状态机。后端根、前端根和 package manifest
   是多个目标，不能伪称一次 rename 可让三者共同原子；实现必须逐项从同文件系统 staging rename，且在
   `plugins.lock` 激活前保留可逆的旧路径 quarantine。任一文件提升失败时恢复旧路径或按同一 package
   identity 幂等续跑；只有全部目标 digest 二次核对通过后才原子替换 lock 并调用统一 applier。任何
   preflight 失败均删除 staging，不改工作区、lock、catalog、账本或安装表。

生产环境禁止执行以上安装流程；生产 registry 仍只能解析明确 lock 的模块。当前 resolver 的类注释和
入口正是“只解析显式 lock、绝不扫描目录”（`server/app/platform/service/plugin/PluginLockResolver.php:6-29`）。

#### 3. 单模块包与多模块 bundle

两种包使用完全相同的 tar layout、inventory、签名和安装流水线，差别仅在包级身份和
`plugin.json.modules` 数量：

| 项 | 单模块包 | 多模块能力包 |
| --- | --- | --- |
| `package-key` | 必须等于唯一 module key | 独立的 bundle/plugin key；不得冒充任一 module key |
| `plugin.json.modules` | 恰好 1 项 | 2 项或以上，按 module key 排序；schema 当前已允许 1..N 项（`server/resources/schemas/plugin.schema.json:63-75`） |
| Module 身份 | `module.json.key` | 每个 `module.json.key` 仍是全局唯一 ID |
| 依赖 | 只读该 Module 的 `dependencies` | 仍逐个读取各 Module 的 `dependencies`；bundle 只提供共同运输，不覆盖依赖声明 |
| 生命周期原子单位 | 该 Module | 整个 bundle；不能从不可变 bundle 中单独拆装某个成员，否则 `plugin.json`、archive digest 与 lock 身份失真 |

多模块 bundle 的安装必须先完成全包校验，再按拓扑逐 Module apply；任何成员 preflight 失败时，一个成员
也不能落地。卸载时，给定成员 module key 只用于解析所属 package；UI/CLI 必须展示并确认“将处理整个
bundle 及其全部 module keys”，不得静默只删一个成员。

#### 4. 最小示例目录树

以下是 `acme.article` 单模块包的最小结构；`module.json` 中声明的资源文件按需出现，示例保留 fixture
已经具备的完整类别（现行 fixture 字段见
`server/app/Modules/Fixture/DeliveryRecord/module.json:12-34`）：

```text
plugins/acme.article/plugin.json
server/app/Modules/Acme/Article/
├── module.json
├── composer.json
├── ModuleProvider.php
├── Http/routes.php
├── Database/Migrations/202608260001-create-article.sql
└── Resources/
    ├── menus.json
    ├── permissions.json
    └── setting-definitions.json
web/src/modules/acme-article/
├── package.json
├── contribution.ts
└── views/index.vue
META-INF/
├── files.sha256
└── signatures/acme-release.json
```

### 【涉及文件/接口】

- 现有事实源：`server/resources/schemas/plugin.schema.json`、
  `server/vendor/peanut-admin/core/kernel/resources/schemas/module-manifest.schema.json`、
  `server/app/platform/service/plugin/PluginArtifactWriter.php`、
  `server/app/platform/service/plugin/PluginLockResolver.php`。
- 现有黄金参考：`server/app/Modules/Fixture/DeliveryRecord/module.json`、
  `plugins/fixture.delivery-record/plugin.json`；后者当前已经展示单模块 `modules`、Composer、npm、
  frontend 和 `source.sha256` 的对应关系（`plugins/fixture.delivery-record/plugin.json:1-38`）。
- 目标命令（正文已拍板、当前尚未实现）：`php think module:pack <key>`、
  `php think bundle:pack <key...>`、`php think module:install-package <pkg.tar>`。
- `META-INF/*` 只存在于 tar staging；安装后不复制进项目，不进入 `plugins.lock`，也不进入数据库。

### 【验收点】

1. 对 `acme.article` 打包后，tar 成员集合与上树相符；只有一个
   `server/app/Modules/Acme/Article/module.json`，不存在前端 manifest 副本或组件清单。
2. 把任一载荷字节改掉但不改 inventory，安装必须在文件落地前以完整性错误失败；同时修改载荷和
   inventory，但沿用原签名时，必须以签名错误失败。
3. 提供错误 `expected_sha256` 时，安装在列目录/解包前失败；未提供 pin 且没有可验证签名时 fail-closed。
4. tar 包含 `../x`、绝对路径、反斜杠路径、重复成员或链接时，安装失败，工作区、`plugins.lock`、
   `pa_plugin_installation`、`pa_module_installation`、catalog 与 `pa_module_migration` 均无变化。
5. fixture 与 Article 的现行 `frontend.entry` 分别与各自 key 派生结果逐字符相等，打包和安装 preflight
   均通过；把任一测试包的 entry 改成另一个语法合法但非派生值（包括大小写差异、额外目录或反斜杠）
   后，打包与安装 preflight 均 fail-closed，且文件、lock、catalog、账本和 installation 零变化。
6. 单模块包的 package key 不等于 module key时失败；bundle 出现重复 module key、后端 key↔路径不符、
   任一前端 entry 不等于 key 派生值、缺失内部依赖或外部依赖版本不满足时，全包失败且零成员落地。
7. 任一权限 key 未以其 module key 命名空间化（包括 `list`/`read`），或菜单/路由仍引用旧 key 时，
   打包或安装 preflight 失败；不生成兼容 alias。
8. 同一输入树重复打包，两份 `META-INF/files.sha256` 和 `plugin.json.source.sha256` 必须逐字节相同；tar
   归档元数据（成员顺序、mtime、uid/gid、mode）规范化后 archive SHA-256 也必须相同。
9. 安装成功后，重新用现有 resolver 校验得到的 package/module identity 与包内一致；`plugins.lock`
   仍只登记派生身份，不出现权限、依赖或前端组件的手工副本。

## D2. `--purge` 的原子性方案

### 【设计】

#### 1. 不伪造跨 DDL 事务，采用可恢复前滚状态机

当前迁移执行已经明确记录“MySQL DDL 会隐式提交”，所以账本先写 `applying`，执行 DDL 后再写
`applied`（`server/app/platform/service/plugin/PluginLifecycleService.php:298-338`）。`--purge` 沿用同一
事实：不承诺数据库 rollback，而承诺任意中断点都满足以下二选一：

- **可服务终态**：安装状态为 `active`，owned tables、applied 账本和 active catalog 彼此一致；或
- **不可服务但可恢复态**：安装状态为 `maintenance` 且
  `last_error_code=MODULE_PURGE_IN_PROGRESS`，运行时和重装 fail-closed，只允许同一 purge 继续前滚。

这不增加第二套模块状态。`pa_module_installation.status` 已有 `maintenance`，也已有
`last_error_code` 与 revision；允许值约束见 `server/database/init.sql:1055-1073`。多模块 bundle 同时把
现有 `pa_plugin_installation.status` 置为 `maintenance`；该表当前也允许 `maintenance` 和
`uninstalled`（`server/database/init.sql:1114-1139`）。同一数据库连接还应持有以 package key 派生的
MySQL advisory lock，直到 purge 结束，以阻止并发 install/disable/sync；真正的恢复依据仍是上述
installation 行，而不是 advisory lock。

#### 2. 默认 retire 的 catalog 软退役

现行 `PluginLifecycleService::uninstall()` 只把 Module 置 `maintenance`、Plugin 置 `uninstalled`，没有处理
catalog（`server/app/platform/service/plugin/PluginLifecycleService.php:122-173`）；这是目标统一 applier 要补的
对称语义，不能把现状误写成已经实现。默认 retire 的目标合同是：从 active registry 排除该 package 后，
把它贡献的 catalog 主记录及有 `status` 的关系记录统一更新为 `retired`，绝不物理删除：

- 菜单复用现有 `MenuCatalogSynchronizer` 调用 `retireMissing()` 的语义
  （`server/vendor/peanut-admin/core/kernel/src/Menu/MenuCatalogSynchronizer.php:14-26`）；仓库实际执行
  `pa_menu_definition.status='retired'`（`server/vendor/peanut-admin/core/kernel/src/Menu/PdoMenuCatalogRepository.php:63-79`）。
- 设置复用现有 synchronize 对缺失定义的软退役：更新 `pa_setting_definition.status='retired'` 并递增
  `revision`（`server/vendor/peanut-admin/core/settings/src/Persistence/PdoSettingRepository.php:31-76`），不删除
  deployment/tenant/target value 行。因为该方法只处理 registry 明确列出的 module keys，retire applier 必须
  用 `registerModule(target module key, [])` 显式登记“该 Module 当前零 active definitions”；现有 registry
  即使 definitions 为空也会保留 module key
  （`server/vendor/peanut-admin/core/settings/src/Definition/SettingDefinitionRegistry.php:17-45`）。
- 权限及授权资源的对称 retire 是**新增契约**：当前 `AuthorizationCatalogRepository` 没有 retireMissing
  方法（`server/vendor/peanut-admin/core/kernel/src/Authorization/Persistence/AuthorizationCatalogRepository.php:7-43`）。
  目标 applier 必须按 module key 将 `pa_permission`、`pa_protected_resource`、`pa_target_type`、
  `pa_data_condition_definition`、`pa_resource_operation` 及具备状态字段的关系行软退役；其中
  `pa_permission.status='retired'` 与 `pa_protected_resource.status='retired'` 必须同时写非空 `retired_at`，
  分别符合现行约束（`server/vendor/peanut-admin/core/kernel/src/Persistence/Schema/KernelSchema.php:103-124`、
  `server/database/init.sql:1175-1194`）。

retire 不删除 `pa_role_permission`、`pa_platform_role_permission` 或设置值，也不改变 `pa_tenant_module`；
运行时只消费 active catalog。重装同 key 且校验通过后，现有 upsert/update 语义把相同行重新激活而非
插入第二份：菜单 upsert 置 active（`server/vendor/peanut-admin/core/kernel/src/Menu/PdoMenuCatalogRepository.php:26-42`）、
permission upsert 置 active 并清 `retired_at`
（`server/vendor/peanut-admin/core/kernel/src/Authorization/Persistence/PdoAuthorizationCatalogRepository.php:13-38`）、
设置 update 置 active 并递增 revision
（`server/vendor/peanut-admin/core/settings/src/Persistence/PdoSettingRepository.php:410-428`）。这与 purge 的
物理删除严格分开。

#### 3. 清除集合与前置条件

purge 的目标集合先从仍在 staging/工作区中的每个 `module.json.database.owned_tables` 取并集；该字段是
schema 要求的唯一表归属声明（`server/vendor/peanut-admin/core/kernel/resources/schemas/module-manifest.schema.json:76-86`）。
多模块 bundle 按整个 package 取并集，任何表重复归属直接拒绝。

进入 destructive 步骤前必须一次性通过：

1. 包/manifest digest 与安装记录一致，module key 与路径一致；
2. 所有目标 `pa_tenant_module` 均不是 `enabled`。当前卸载也先检查 enabled 行并拒绝
   （`server/app/platform/service/plugin/PluginLifecycleService.php:128-136`）；purge 不替用户自动关闭
   TenantModule；它对成员/平台角色绑定的物理删除属于第三层 RBAC 副作用，必须按本节第 6 项先预览、
   再显式确认，不能静默发生；
3. 没有仍安装模块通过各自 `module.json.dependencies` 依赖目标 Module；
4. `information_schema` 中没有 owned set 之外的表以外键引用 owned table；若有，报告精确约束并停止，
   不用 `FOREIGN_KEY_CHECKS=0` 绕过；
5. 没有 package 外 catalog 关系引用目标 Module 的 permission、protected resource、target type 或
   data condition；发现跨 Module 引用时报告引用者并停止，不删除其他 Module 的关系；
6. 所有 owned table 的实际存在/缺失集合、该 module 的 `pa_module_migration` 行、catalog 行，以及按
   目标 permission IDs 命中的 `pa_role_permission`、`pa_platform_role_permission` 等引用行，必须形成
   D3 定义的 purge preview。调用方必须同时确认 package key 和 preview 的 `plan_digest`；执行前重算结果
   不同则以 `MODULE_UNINSTALL_PLAN_CHANGED` 拒绝。默认卸载不得借机升级为 purge。

#### 4. 固定执行顺序

以下阶段全部幂等；重试从真实数据库状态继续，并携带 D3 已确认的同一 `confirm_plan` 约束可删除
identifier，不依赖“上次执行到第几步”的另一张进度表：

1. **隔离（一个 DML 事务）**：锁 installation 行；再次检查 TenantModule/依赖；把 package 和全部
   Module 标为 `maintenance` + `MODULE_PURGE_IN_PROGRESS`，递增 revision 后提交。此时后端授权与
   Module guard 必须拒绝新操作。
2. **清 catalog（一个 DML 事务）**：按外键反向顺序物理删除目标 Module 的 catalog 数据，确保 purge
   后不会因同 key 重新激活旧授权/设置：
   - 设置值 `pa_setting_target_value`、`pa_setting_tenant_value`、`pa_setting_deployment_value`，再删
     `pa_setting_definition`；这些值表对 definition 都是 `ON DELETE RESTRICT`
     （`server/database/init.sql:1379-1466`）。
   - `pa_menu_definition`，以及引用目标 permission 的 `pa_role_permission`、
     `pa_platform_role_permission`、`pa_resource_operation_permission`、
     `pa_resource_operation_target_type.policy_selection_permission_id`，再删 `pa_permission`。菜单到权限也是
     `ON DELETE RESTRICT`（`server/database/init.sql:1307-1338`），角色绑定到权限同样如此
     （`server/vendor/peanut-admin/core/kernel/src/Persistence/Schema/KernelSchema.php:145-155`、`:243-255`）。
     operation relation 到 permission 的现行 FK 见 `server/database/init.sql:1239-1269`。
     这些删除是显式的 RBAC/catalog 关系变更：每张表的计划数、实际删除数和受影响 role 标识必须进入
     D3 uninstall 的 preview 与最终 `removed[]`；只能按目标 permission IDs 删除，不开通/关闭
     TenantModule、不授予其他权限，也不得删除未列入已确认 plan 的角色绑定。
   - 若 manifest 贡献 protected resources/target types/data conditions，则按关系表 →
     `pa_resource_operation` → `pa_protected_resource`/`pa_target_type`/
     `pa_data_condition_definition` 的外键反向顺序处理。Module schema 当前确实支持这些 catalog 类别
     （`server/vendor/peanut-admin/core/kernel/resources/schemas/module-manifest.schema.json:107-238`）。
   - 不触碰 legacy `pa_system_menu`；Module catalog 的现行表是 `pa_menu_definition`、`pa_permission` 和
     `pa_setting_definition`。
3. **删业务表（DDL 前滚）**：根据 `information_schema` 外键图对 owned set 做反向拓扑排序，逐表执行
   `DROP TABLE IF EXISTS`。每条 DDL 是独立原子语句，但整个序列不是事务；因此允许中断后出现“部分表
   已缺失”，installation 的 maintenance 标记保证该状态不可被当作 active。循环和 owned set 外引用在
   preflight 已拒绝，不关闭外键检查。
4. **清账本并确认数据库 clean（一个 DML 事务）**：只有二次查询确认所有 owned tables 均不存在后，才删除该批
   module keys 的 `pa_module_migration` 行。账本现行唯一键为 `(module_key,migration_key)`，并保存
   `checksum/status`（`server/database/init.sql:1156-1173`）；“表未全无而先删账本”是禁止路径。随后再
   核对 catalog 零残留并提交；此时仍保留 package 和全部 Module 的
   `maintenance/MODULE_PURGE_IN_PROGRESS`，不能提前宣告 purge 完成。
5. **停用 lock、删代码并封口**：数据库 clean 后，先生成排除该 package 的 canonical lock，fsync 后以
   同文件系统 rename 原子替换 `plugins.lock`；再按 `pa_plugin_module` 中的 module keys 及 key 派生路径，
   把后端根、前端根和 package manifest 分别 rename 到 package/digest 唯一的 quarantine，二次核对目标
   路径均不存在后删除 quarantine。最后一个 DML 事务才删除对应 `pa_module_installation` 行、把
   `pa_plugin_installation` 置 `uninstalled` 并清除 error marker，然后提交并失效 D3 规定的缓存。
   `pa_plugin_module` 保留现有 package→module ownership，供重复 purge 判定 bundle scope；它不保存
   owned tables、权限或前端组件，不能替代 `module.json`。若此阶段中断，maintenance marker 与
   `pa_plugin_module` 足以让原命令只续跑 lock/派生路径/quarantine 清理，不会重建数据；四项数据库集合
   已经 clean。不能在步骤 4 之前移走 `module.json`，否则 DDL 中断后会失去 owned tables 的唯一声明。

这里的“补偿”是**安全前滚到 clean**，不是尝试凭空恢复已删业务数据。任何异常都保留
`MODULE_PURGE_IN_PROGRESS`，记录失败点并返回可重试错误；恢复只能续跑已确认 `confirm_plan` 中尚存的
目标，发现计划外新引用时停止而不扩大删除范围。只有四项数据库终态断言以及文件、lock 收口断言
全部通过后才移除该标记。

#### 5. purge 后重装

- purge 未完成时，`module:install-package` 和在线 install 必须返回冲突
  `MODULE_PURGE_IN_PROGRESS`，不能把缺表/旧账本的中间态重新标成 active。
- purge 完成后，owned tables 不存在、该 module 的 migration ledger 为零、catalog 为零；重装重新
  校验同一或新版 package，完整执行 migration 并写入新的 checksum 账本，得到空业务表。这与正文
  §5.7 的“purge 后干净重跑”一致。
- 默认 retire 不走本状态机：表和账本均保留，重装对 checksum 匹配且 `status=applied` 的迁移继续
  skip。当前实现正是先比 checksum，再对 `applied` 直接 continue
  （`server/app/platform/service/plugin/PluginLifecycleService.php:280-296`）。

### 【涉及文件/接口】

- 唯一表归属：各 Module 的 `module.json.database.owned_tables`；例如 Article 当前声明
  `pa_article_cate`、`pa_article`、`pa_article_collect`
  （`server/app/Modules/Official/Article/module.json:20-25`）。
- 生命周期现状：`server/app/platform/service/plugin/PluginLifecycleService.php`；现行默认 uninstall
  仅把 Module 置 maintenance、Plugin 置 uninstalled 并保留 migrations/ownership/data，尚未调用 catalog
  retire（`server/app/platform/service/plugin/PluginLifecycleService.php:122-173`）。目标 retire 复用现有
  Menu/Setting 的软退役语义，并为 Authorization catalog 补对称的新增契约。
- 状态/账本/catalog schema：`server/database/init.sql:1055`、`:1114`、`:1141`、`:1156`、`:1307`、
  `:1341`，以及 Core 的 `pa_permission`/RBAC schema。
- 目标命令：`php think module:uninstall-package <key> [--purge]`。对 bundle 成员 key 的调用必须先解析并
  展示整个 package scope，再要求确认。

### 【验收点】

1. 默认 retire 前后分别按目标 module key 统计：owned tables、业务数据、`pa_module_migration`、
   `pa_role_permission`、`pa_platform_role_permission` 和 setting value 行数全部不变；catalog 主记录仍在，
   但 `status='active'` 计数为 0、`status='retired'` 计数等于 retire 前 active 数，文件已删除。重装同一
   已验证 package 后，相同 catalog key/ID 重新 active、role binding 仍可复用，migration 被 skip 且旧
   业务数据仍在，不产生重复 catalog 行。
2. 正常 purge 完成后逐项断言：所有 `owned_tables` 在 `information_schema.tables` 中计数为 0；业务
   数据因此为 0；目标 module keys 的 `pa_module_migration` 计数为 0；所有 catalog 主表和引用/值表
   中与目标 module/permission/definition 关联的计数为 0；无 active installation。执行前 preview 必须
   单列 `pa_role_permission` 与 `pa_platform_role_permission` 的计划删除数及 role 标识，执行后最终
   `removed[]` 的实际删除数与已确认 plan 完全一致；任一未列入 plan 的角色绑定计数不变。
3. 构造“清到一半中断”：在 catalog 事务提交且第一张 owned table 的 `DROP TABLE` 成功后注入进程终止。
   此时必须可断言 installation 是 `maintenance/MODULE_PURGE_IN_PROGRESS`、catalog 已不可见、账本仍
   完整、owned tables 是可枚举的部分集合；任何 Module 请求和 install 均 fail-closed。
4. 对上一步原命令原参数重试：已缺表因 `IF EXISTS` 跳过，其余表继续删除；只有全表缺失后二次核对
   才清账本，再完成 lock/文件收口后结束状态。最终满足验收点 2；第三次重复 purge 返回
   `operation=unchanged`，不报错也不触碰其他 Module。
5. 在 DDL 全部成功、账本事务尚未开始时注入中断；重试必须识别“表已全无但账本仍在”，先清账本再
   封口，不能尝试运行 migration 或恢复 active。
6. 在数据库 clean 事务提交、lock 尚未替换，以及 lock 已替换、首个代码根已进入 quarantine 两处分别
   注入中断；两次重试都必须依靠 maintenance marker、`pa_plugin_module` 和派生路径完成文件收口，最终
   lock 不含 package、三个代码/manifest 目标与 quarantine 均不存在，随后才清 marker。过程中不能再次
   删除 catalog/账本或执行 migration。
7. owned table 存在 package 外外键引用、任一 TenantModule 仍 enabled、存在依赖者、manifest digest
   不匹配时，purge 在 marker/DDL 前失败，四项均无变化。
8. purge 完成后重装，全部 migration 重新执行并生成新 `applied` 账本；业务表为空。retire 后重装则
   仍 skip 并保留数据，两条路径不得混淆。

## D3. 在线模块管理页 + `/dev-tools` 平面的页面与接口契约

### 【设计】

#### 1. 页面分工，不新增控制面

新增页面落在现有 Admin Web 的 `/dev-tools/modules`，作为
`web/src/router/routes/modules/dev-tools.ts` 的第二个 child（新增目标页面路径：
`web/src/views/dev-tools/modules/index.vue`）。父路由已经是 `/dev-tools` 且带
`instanceTool: true`（`web/src/router/routes/modules/dev-tools.ts:4-25`），不得新建顶级 route、独立 SPA
或 Module Federation 容器。

页面只管理**实例安装层**：展示 package/module identity、依赖、installation 状态、manifest digest、
阻塞原因；执行统一脚手架创建、上传安装、实例停用、卸载/显式 purge、开发库 sync。表格数据是每次从
`module.json` + `plugins.lock` + `pa_plugin_installation`/`pa_plugin_module`/
`pa_module_installation` 编译得到的 projection，不持久化页面状态，也不允许浏览器提交 owned tables、
依赖、权限或前端组件清单。

**接口平面采用方案 A：现有 Platform 实例通道。** 模块安装是实例/部署动作，不能再挂到
`/api/admin/*`。现有 Platform 路由已明确声明它不共享 Admin session/RBAC
（`server/route/app.php:104,147,150`），其登录中间件建立独立 `PlatformOperatorContext`
（`server/app/platform/http/middleware/PlatformLoginMiddleware.php:12-36`）；这与 §8 三层解耦直接一致，
也避免为 InstanceTool 再造第三套身份系统。`/dev-tools/modules` 仍是现有 Admin Web 下的开发工具页面，
但它调用本节接口时必须携带**独立 Platform bearer token**，不得复用或转换 Tenant Admin token。现有
Platform client 已使用独立 `peanut-platform-token` 并写入 `Authorization: Bearer`
（`platform/src/api/platform.ts:139-153`）。目标页面没有有效 Platform session 时，只在
`/dev-tools/modules` 内显示 Platform 重认证区并调用现有 `POST /api/platform/session/login`，不得创建新
登录端点、不得把 Tenant Admin token 换成 Platform token，也不得退回 `AdminPermissionService`；现有
Platform session 路由登记见 `server/route/app.php:104,147,150`。

**租户开通层不搬进该页。** 现有 Platform Web 已有“模块目录”页面并调用现成 API
（`platform/src/App.vue:19-24`、`platform/src/api/platform.ts:318-339`）；继续复用：

- `GET /api/platform/tenants/modules?tenant_id=<id>&page=<n>&page_size=<n>`，权限
  `platform.tenant.read`（`server/route/app.php:104`）；
- `POST /api/platform/tenants/modules/enable` 与 `/disable`，权限
  `platform.tenant.module.manage`（`server/route/app.php:147,150`）。

这三条只读/写 `pa_tenant_module` 及授权 revision，不安装代码、不执行 migration、不改变成员角色授权。
后端服务在 enable 时先要求 Module 已安装（`server/app/platform/service/module/PlatformTenantModuleService.php:37-56`）。
成员 RBAC 仍是第三层，模块页不提供任何“顺便授权”开关。

#### 2. 新增 HTTP 合同

下表的六条路径、controller 和 `platform.module.*` 权限均为**新增契约**；注册位置是现有
`server/route/app.php` 的 Platform route 区，不进入 Admin route group。所有响应沿用 `{code,msg,data}`，
成功 `code=20000`（`server/app/common/service/JsonService.php:13-35`）。

| 操作 | 方法 + 路径 | 入参 | 成功 `data` 最小字段 | 权限 key | 缓存时机 |
| --- | --- | --- | --- | --- | --- |
| 查看安装模块/依赖 | `GET /api/platform/instance-tools/modules` | 可选 `module_key`；分页 `page/page_size` | `lists[]:{module_key,name,version,manifest_digest,package_key,package_version,package_modules[],status,dependencies[],dependents[],tenant_enabled_count,blockers[],lifecycle_protected}`、`count/pageNo/pageSize` | `platform.module.read`（新增 Platform 权限） | 只读，不失效 |
| 创建 Module 骨架 | `POST /api/platform/instance-tools/modules/create`，JSON | `module_key`；可选 `vendor` 必须与 key 首段派生值一致 | `operation=created`、`module_key`、`vendor`、`backend_path`、`frontend_path`、`frontend_entry` | `platform.module.create`（新增） | 只生成文件，不写 catalog；后续 `module:sync` 成功后再失效 |
| 安装 package | `POST /api/platform/instance-tools/modules/install`，`multipart/form-data` | `package`（`.tar`）、`expected_sha256`（64 hex）、可选 `signature_key_id` | `operation=installed|reactivated|unchanged`、`package_key`、`archive_sha256`、`modules[]:{module_key,version,status}`、`catalog_revision` | `platform.module.install`（新增） | 文件/lock 落地且 applier 的 DB 事务提交后、返回前；失败不失效 |
| 卸载 / purge | `POST /api/platform/instance-tools/modules/uninstall`，JSON | 预览：`module_key`、`purge`、`preview:true`；执行：再带 `preview:false`、`confirm_package_key`、`confirm_plan_digest`、`confirm_plan`（预览返回的规范计划对象）、`change_reason` | 预览：`operation=preview`、`plan_digest`、`affected_modules[]`、`preserved[]`、`removed[]`、`blockers[]`；执行：`operation=retired|purged|unchanged`、同一 scope 的实际 `removed[]`、`catalog_revision` | `platform.module.uninstall`（新增） | retire catalog 事务提交后立即失效；purge 最终事务再确认一次；失败保留恢复 marker |
| 实例停用 | `POST /api/platform/instance-tools/modules/disable`，JSON | Bundle 任一 `module_key`、`change_reason` | `operation=disabled|unchanged`、`package_key`、`affected_modules[]`、`status=maintenance`、`catalog_revision` | `platform.module.disable`（新增） | 整个 Bundle 的 maintenance + catalog 软退役提交后、返回前 |
| 触发 `module:sync` | `POST /api/platform/instance-tools/modules/sync`，JSON | 可选 `module_key`；空值表示同步当前开发树全部 Module | `operation=synced|unchanged`、`modules[]`、`catalog_revision`、`changes:{menus,permissions,settings}` | `platform.module.sync`（新增） | applier 提交后、返回前 |

uninstall preview 的 `removed[]` 每项固定为
`{scope,table,action,count,identifiers[]}`。默认 retire 把 catalog 项列为 `action=soft_retire`，并在
`preserved[]` 明示角色绑定、setting values、owned tables 和 migration ledger 保留；purge 必须把
`pa_role_permission`、`pa_platform_role_permission`、`pa_resource_operation_permission` 以及命中
`policy_selection_permission_id` 的 `pa_resource_operation_target_type` 行逐表列为
`action=physical_delete`。角色绑定的 `identifiers[]` 至少包含 tenant `role_id/tenant_id` 或
`platform_role_id`，不得包含凭据。执行请求必须重算 preview；package 或任一计数/标识变化即返回
`40900/MODULE_UNINSTALL_PLAN_CHANGED`，不进入 D2 marker/DDL。成功响应的 `removed[]` 是实际删除/退役
结果，必须与已确认 plan 逐项一致。

`plan_digest` 的生成也是固定合同，不能依赖 PHP 数组插入顺序或数据库默认排序。服务端先从 preview
投影出不含 `plan_digest`、展示文案和时间字段的 `confirm_plan`：顶层只含 `schema_version:1`、
`package_key`、`package_manifest_digest`、`operation`（`retire|purge`）、`affected_modules`、`preserved`、
`removed`、`blockers`。对象 key 按 UTF-8 字节升序；`affected_modules` 按 `module_key`，后三个数组按
`scope/table/action` 升序，每项 `identifiers` 再按其规范 JSON 字节升序；只允许 string、boolean、整数、
array、object，不允许 float、null 或环境相关字段。随后用 UTF-8、无空白 JSON 编码，字符串不做 Unicode
归一化，计算 `sha256(规范 JSON 原始字节)`，以 64 位小写 hex 返回。执行请求必须原样回传
`confirm_plan`，服务端先核对其摘要与 `confirm_plan_digest`，再在写 maintenance marker 前从当前
filesystem/lock/DB 重建计划并逐字节比较；任一差异均 fail-closed。purge 已进入
`MODULE_PURGE_IN_PROGRESS` 后的重试仍携带同一 `confirm_plan`，只允许续删其中列明而当前仍存在的
identifier；出现任何计划外新引用则保留 marker 并返回恢复冲突，不能静默扩大删除范围。

六条 route 的鉴权/门控链固定为：

1. `PlatformLoginMiddleware` 校验 Platform Host 与 bearer token，并建立 `PlatformOperatorContext`；当前
   实现见 `server/app/platform/http/middleware/PlatformLoginMiddleware.php:14-35`。
2. `PlatformPermissionMiddleware` 要求权限以 `platform.` 开头，并调用
   `PlatformRuntimeFactory::sessions()->assertAllowed()`；当前实现见
   `server/app/platform/http/middleware/PlatformPermissionMiddleware.php:15-39`。每条 route 使用上表自己的
   精确 key，不共用宽泛 manage key。session service 把检查交给 Platform evaluator
   （`server/app/platform/service/PlatformOperatorSessionService.php:52-55`），factory 真实装配
   `PdoPlatformAuthorizationRepository` + `PlatformAuthorizationEvaluator`
   （`server/app/platform/service/PlatformRuntimeFactory.php:49-77`）。
3. 新增目标 middleware `server/app/platform/http/middleware/PlatformInstanceToolMiddleware.php`
   （**新增契约**）只做环境/形态门控：要求 `APP_ENV=development`、应用 debug 开启，并调用现有
   `InstanceToolAccessGuard::fromConfiguredValue(config('deployment.mode'))->allows()`；`APP_ENV`/`APP_DEBUG`
   是现行环境字段（生产示例见 `.env.example:1-2`），debug 状态读取框架现有 `app()->isDebug()`
   （`server/vendor/topthink/framework/src/think/App.php:268-270`），其环境初始化见同文件 `:583-588`。该 guard 当前仅在
   Standalone 返回 true（`server/app/common/service/instance/InstanceToolAccessGuard.php:12-20`），配置值
   来自 `DEPLOYMENT_MODE`（`server/config/deployment.php:4-8`）。失败统一返回
   `40300/MODULE_RUNTIME_MUTATION_DISABLED`，且上传体不得进入 staging。

六个 `platform.module.*` key 的唯一代码登记点是 `CorePermissionCatalog::PLATFORM`（**新增成员契约**；
现有列表位置为 `server/vendor/peanut-admin/core/kernel/src/Authorization/CorePermissionCatalog.php:38-63`）。
现有 `CorePermissionCatalogSynchronizer` 会把 PLATFORM 成员以 `module_key='platform'` 同步到
`pa_permission`（`server/vendor/peanut-admin/core/kernel/src/Authorization/CorePermissionCatalogSynchronizer.php:17-24`、
`:45-59`），并由 `ModuleAuthorizationCatalogSynchronizer` 调用
（`server/vendor/peanut-admin/core/kernel/src/Authorization/ModuleAuthorizationCatalogSynchronizer.php:17-33`）。
路由、Core catalog、已同步的 active permission row 三者必须逐字符一致；页面不维护权限副本。

Platform 授权没有 Tenant root bypass：普通 Platform operator 只得到 active role 绑定的 active
`platform.*` 权限；内建 `platform.bootstrap-owner` 也只额外得到 `CorePermissionCatalog::PLATFORM` 中的
成员（`server/vendor/peanut-admin/core/kernel/src/Platform/Authorization/PdoPlatformAuthorizationRepository.php:36-67`），
最终 evaluator 仍按 EffectivePermissionSet 判定
（`server/vendor/peanut-admin/core/kernel/src/Platform/Authorization/PlatformAuthorizationEvaluator.php:18-39`）。
因此未登记到 Core catalog、也不存在 active DB grant 的 key，bootstrap-owner 同样拒绝；Tenant Admin
root 的 token 不是 Platform token，只会在第一层得到 401。HTTP adapter 必须直接调用与 CLI 共用的应用
服务，禁止在 Web 请求里启动 shell 子进程。

“停用”只改变安装层为 maintenance 并移除 active catalog；它不得自动更新任何
`pa_tenant_module`。若仍有 enabled TenantModule，返回冲突并要求先去 Platform 控制面关闭。重新上传
同一已验证 package 可把 maintenance 状态 reactivated；不提供另一条隐式恢复路径。

#### 3. 缓存失效合同

缓存失效必须发生在相应 DB 事务 **commit 之后、20000 响应之前**；回滚或失败不得发出成功失效信号：

- 菜单/catalog projection：使下一次菜单请求读取新 active catalog；页面请求成功后主动刷新当前菜单和
  模块表格，不把前端菜单当授权结果。
- 权限：对目标 Module 曾开通的 Tenant 递增既有 authorization revision，使 revision-keyed permission
  cache 自然换 key。现有 evaluator 的 cache key包含 repository revision
  （`server/vendor/peanut-admin/core/kernel/src/Authorization/TenantAuthorizationEvaluator.php:22-30`）。
- 设置：沿用 `pa_setting_definition.revision`；Setting cache key 已包含 definition revision
  （`server/vendor/peanut-admin/core/settings/src/Application/SettingResolver.php:384-413`），不得再建模块级
  shadow cache。
- TenantModule enable/disable 已在既有 repository 中递增 Tenant 与 TenantModule 的
  `authorization_revision`（`server/vendor/peanut-admin/core/kernel/src/Module/Persistence/PdoModuleRuntimeRepository.php:96-117`、`:123-139`）；复用该行为，不另建缓存 API。

#### 4. 成功、失败与重复调用

全局鉴权失败沿用 Platform 链：缺少/无效 Platform session 为 `{code:40100,msg,data:null}`
（`server/app/platform/http/middleware/PlatformLoginMiddleware.php:21-32`），缺 Platform 权限为
`{code:40300,msg,data:null}`（`server/app/platform/http/middleware/PlatformPermissionMiddleware.php:17-29`）。
环境/形态拒绝及进入接口后的领域失败使用 `data.error_code` + 可安全展示的 `msg`：环境门控
`40300/MODULE_RUNTIME_MUTATION_DISABLED`，输入/manifest/digest 错误 `42200`，状态/依赖/启用租户/purge
计划冲突 `40900`，registry 不可用 `50300`。不得返回服务器绝对路径、SQL、签名公钥或上传临时路径。

| 接口 | 首次成功 | 失败断言 | 完全相同的重复调用 |
| --- | --- | --- | --- |
| `platform.module.read` | 返回同一 revision 的只读 projection | registry 不可用为 `50300/MODULE_REGISTRY_UNAVAILABLE` | 数据未变化时响应逐字段相同（时间型诊断字段不得混入 identity） |
| `platform.module.create` | CLI 与 HTTP 调用同一 `ModuleScaffoldGenerator` 和同一模板，生成 key 派生的前后端骨架 | 非法 key/vendor 为 `42200`；任一目标已存在为 `40900/MODULE_CREATE_TARGET_EXISTS`，且不覆盖已有文件 | 首次成功后相同请求返回 target-exists；既有目录字节不变，不生成第二份骨架 |
| `platform.module.install` | 全包校验、文件/lock、migration、catalog 全部完成后才 `installed` | 任一 digest/签名/manifest/路径/依赖失败为 `42200` 或 `40900`，零部分安装 | active 且 archive/module identity 全同返回 `20000 operation=unchanged`；同 key 不同 identity 返回冲突，不隐式 upgrade |
| `platform.module.uninstall` | 首次只读 preview 返回 plan；确认同一 `plan_digest` 后默认 `retired`，显式 purge 完成 D2 全状态机后 `purged` | enabled TenantModule、依赖者、package/plan 确认不符或外键阻塞均在 destructive 步骤前 `40900`；preview 零写入 | 同一状态重复 preview 得到同一 digest；已 retire 再执行 retire、已 clean 再执行 purge 返回 `20000 operation=unchanged`；purge in progress 只按已确认 plan 续跑 |
| `platform.module.disable` | 无 enabled TenantModule 时进入 maintenance 并软退役 active catalog | enabled TenantModule/依赖阻塞返回 `40900` | 已 maintenance 返回 `20000 operation=unchanged` |
| `platform.module.sync` | 统一 applier 把本地 `module.json` projection 同步到开发库 | 非 development/Standalone、未知 key、checksum/owner 冲突 fail-closed | 无差异返回 `20000 operation=unchanged`，不重复插入 catalog |

现有 TenantModule API 的重复调用语义保持现状，不伪称为严格 no-op：enable 使用 upsert 并递增 config 与
authorization revision（`server/vendor/peanut-admin/core/kernel/src/Module/Persistence/PdoModuleRuntimeRepository.php:88-117`），
disable 也会递增 revision。它们是状态收敛且每次留审计的写调用；UI 在已处于目标状态时不重复发起。

### 【涉及文件/接口】

- Admin Web 现有路由：`web/src/router/routes/modules/dev-tools.ts`；目标新增 child
  `/dev-tools/modules`，不新增顶层面。
- 目标页面/API adapter（新增契约）：`web/src/views/dev-tools/modules/index.vue`、
  `web/src/api/dev-tools/modules.ts`；仅消费上述 HTTP projection、不存 manifest 副本，并只发送独立
  Platform bearer token。
- 后端现有 Platform 鉴权链：`server/route/app.php`、
  `server/app/platform/http/middleware/PlatformLoginMiddleware.php`、
  `server/app/platform/http/middleware/PlatformPermissionMiddleware.php`、
  `server/app/platform/service/PlatformOperatorSessionService.php`；新 route 不经过 Admin
  `LoginMiddleware/AuthMiddleware` 或 `AdminPermissionService`。
- 目标后端 adapter/门控（新增契约）：
  `server/app/platform/controller/PlatformModuleLifecycleController.php`、
  `server/app/platform/http/middleware/PlatformInstanceToolMiddleware.php`。后者复用现有
  `server/app/common/service/instance/InstanceToolAccessGuard.php` 和 `server/config/deployment.php`；不把
  前端 route meta 当作服务端授权。
- 权限 key 唯一代码登记：
  `server/vendor/peanut-admin/core/kernel/src/Authorization/CorePermissionCatalog.php` 的 `PLATFORM` 常量；
  同步落库复用 `CorePermissionCatalogSynchronizer`，授权读取复用
  `PdoPlatformAuthorizationRepository`/`PlatformAuthorizationEvaluator`。这些 key 均为本附录新增契约，
  不是仓库当前已存在的权限。
- 目标 CLI 与 HTTP 共用能力：`module:create`、`module:install-package`、`module:uninstall-package`、
  `module:sync`；`module:create` 与 HTTP create 必须直接复用
  `server/app/common/service/module/ModuleScaffoldGenerator.php` 及 `server/resources/module-scaffold/`，禁止
  Web 端维护第二套模板或启动 CLI 子进程。现有
  与 Module/Plugin 生命周期直接相关的 console 登记是 `module:install` 以及
  `plugin:install/reconcile/make/lock/upgrade/rollback/uninstall`（`server/config/console.php:13-21`），故本文
  没有把目标命令写成现状事实。
- 租户开通保持：`server/route/app.php:104,147,150`，以及
  `platform/src/api/platform.ts:318-339`。

### 【验收点】

1. development + Standalone 时 `/dev-tools/modules` 注册且需现有 Admin 页面登录；其六条数据/写请求还
   必须携带有效 Platform bearer token。缺 Platform token 返回 `40100`，Tenant Admin token（包括 Tenant
   root）不能被转换或接受为 Platform token；不存在第三个顶级控制面或 Module Federation 配置。
2. 对每条新 route 做三态鉴权断言：普通 Platform operator 未获对应 `platform.module.*` key 时返回
   `40300`；非 demo operator 经 active `pa_platform_role_permission` 获得该精确 key 后通过；故意让 route 引用一个未加入
   `CorePermissionCatalog::PLATFORM` 且未同步到 active `pa_permission` 的 key 时，内建
   Platform root-equivalent `platform.bootstrap-owner` 也返回 `40300`。另断言只获
   `platform.module.read` 的账户不能调用五条写
   接口，六条 key 的 route 参数、Core catalog 成员和 active DB row 逐字符一致。
3. 对 install 请求的调用链/探针断言只经过 `PlatformLoginMiddleware` →
   `PlatformPermissionMiddleware` → `PlatformInstanceToolMiddleware` → 共用 lifecycle service；
   `AdminPermissionService::canAccess`、`TenantAuthorizationEvaluator` 和任何 `TenantContext` 解析调用次数均
   为 0。这是“装包动作不经租户成员权限评估”的判定点。
4. `APP_ENV` 非 development、应用 debug 关闭、`DEPLOYMENT_MODE` 缺失/非法/multi-tenant、以及 production
   四类场景分别请求六条接口，均得到 route-not-found 或
   `40300/MODULE_RUNTIME_MUTATION_DISABLED`；install 上传不得进入 staging。development + debug +
   Standalone 且权限满足时才通过门控。
5. 对上表每条接口各执行一次成功、一次代表性失败、一次完全相同重复调用，响应必须符合上表
   operation/error_code；失败后检查文件、lock、installation、catalog 和 migration 无越权部分写入。
   其中 HTTP create 与 CLI `module:create` 生成同一 key 时必须得到相同目录布局和文件摘要；重复 create
   返回 `MODULE_CREATE_TARGET_EXISTS` 且原目录摘要不变，证明两种入口没有第二套生成机制。
6. uninstall 第一次 `preview:true` 必须零写入并返回稳定 `plan_digest`；对同一状态连续预览两次，
   `confirm_plan` 规范 JSON 字节与 digest 均相同，交换 SQL 返回顺序也不得改变摘要，改动任一 count 或
   identifier 则摘要必须改变。默认 retire preview 的角色绑定只出现在 `preserved[]`；purge preview 和
   执行响应的 `removed[]` 都逐表列出
   `pa_role_permission`/`pa_platform_role_permission` 计划数、实际数及 role 标识。篡改确认 digest或在
   preview 后新增一条命中角色绑定时，执行返回 `40900/MODULE_UNINSTALL_PLAN_CHANGED` 且零删除；确认
   未变化 plan 后，实际删除集合与 `removed[]` 完全相等，未列入 plan 的其它角色绑定不变。中断后用
   同一 `confirm_plan` 重试只能清理其中尚存 identifier；注入计划外新引用时保持 marker 并拒绝扩大删除。
7. 安装 package 不会新增任何 `pa_tenant_module` 行；租户 enable 不会新增/修改
   `pa_module_installation`、`pa_plugin_installation` 或 `pa_module_migration`；两者均不会自动新增
   `pa_role_permission`。三组断言分别为 0 变化。
8. sync/install/disable/uninstall 成功响应发出前，重新读取菜单、权限和设置得到新 revision；模拟事务
   回滚时 revision 与 cache key 不变化。前端即使手工构造菜单，也无法绕过后端 permission deny。
9. 现有 Platform TenantModule 页面及 `GET /api/platform/tenants/modules`、POST enable/disable 三条 API
   仍只处理 `pa_tenant_module`，不获得 install/uninstall/sync 能力；新实例接口也不自动开通 TenantModule
   或授予成员 RBAC。

## D4. 生产产物摇树剔除 dev-tools 代码的构建期方案

### 【设计】

#### 1. 当前机制为什么还不足以摇树

现有 `routesForDeployment`/`instanceTool` 已能把路由从运行时路由表过滤掉，但
`web/src/router/routes/index.ts` 当前用 `import.meta.glob('./modules/*.ts', {eager:true})` 导入所有 route
模块（`web/src/router/routes/index.ts:9-23`）；`dev-tools.ts` 又包含页面动态 import
（`web/src/router/routes/modules/dev-tools.ts:15-25`）。因此“先 eager import，再运行时过滤”不能作为
“chunk 不存在”的证据。

#### 2. 两层构建期门控

目标改为“构建图门控 + 现有 route policy 门控”，两层使用同一判断：

1. Vite config 改为配置工厂，在 Node 配置求值期使用 Vite 传入的 `command/mode` 和 `loadEnv` 读取
   `VITE_DEPLOYMENT_MODE`，生成布尔字面量 `__PEANUT_INSTANCE_TOOLS_COMPILED__`：仅
   `command=serve + mode=development + standalone` 为 `true`，任何 `command=build` 均为 `false`。
   应用代码仍可用 Vite 内建的 `import.meta.env.DEV` 做一致性断言，但不能在 Node config 中直接读取
   `import.meta.env`。不新增第三种 deployment mode；现有类型仍只有 `standalone | multi-tenant`
   （`web/src/env.d.ts:12`）。
2. 复用现有 Vite 虚拟模块做法（当前已有 `virtual:peanut-plugin-contributions`，见
   `web/config/vite.config.base.ts:29-49`），新增**派生的**
   `virtual:peanut-instance-tool-routes`：编译常量为 `true` 时，虚拟模块源码只导入现有
   `web/src/router/routes/modules/dev-tools.ts` 并导出该 route；为 `false` 时，源码只能是空 route 数组，
   不得包含 dev-tools 的 import 字符串。它不是第二套路由清单，只是对唯一 `dev-tools.ts` 的构建期开关。
3. 普通 eager glob 明确排除 `./modules/dev-tools.ts`；`routes/index.ts` 只静态导入上述虚拟模块并合并其
   返回数组。`dev-tools.ts` 内的页面仍用动态 import，因此生产虚拟模块为空时，route、page、API client
   的整条 import 链都没有入口。`web/src/views/dev-tools/**`、`web/src/api/dev-tools/**` 不得被其他
   barrel、菜单常量、测试 fixture 或 plugin contribution 静态导入。
4. 把上一步得到的 route 数组仍交给现有 `routesForDeployment(routes, mode,
   instanceToolsAllowed)`。当前 route index 已这样调用（`web/src/router/routes/index.ts:26-40`）；该层
   保留部署策略的 fail-closed 语义，但不再负责摇树。
5. `/dev-tools` parent 和所有 child（现有 code generator 及 D3 modules 页）必须位于同一个受控 import
   子图；不能只剔除新页面而把现有 `web/src/views/dev-tools/code/index.vue` 留在生产 chunk。

生产 config 求值时已经把虚拟模块内容生成为空数组；同时 dev-tools route 已从 eager glob 排除，所以
Rollup 从未看到 `dev-tools.ts` 的 import，页面动态 import 也不会进入可达模块图。`define` 的字面量还供
应用侧断言与死分支消除，但“虚拟模块源码不生成 import”是主门控。这是可摇树的原因，而不是依赖
`routesForDeployment` 在浏览器启动后过滤。

`scripts/package-release.sh` 当前通过 `pnpm --dir "$WEB_DIR" build` 生成 `web/dist`，再只把 dist 复制到
`server/public/admin/`（`scripts/package-release.sh:44-58`、`:84-94`）。因此上述 production build gate
一旦通过，交付 archive 的 Admin SPA 也不会重新带回 dev-tools Web 代码。D4 的“开发工具代码”范围是
Admin SPA import 图中的 route、page、API client 与其独占依赖；PHP 端生产“不注册/不执行运行时安装”
由 D3 环境门控负责，不能拿前端摇树替代后端授权。

#### 3. 构建即失败的证据

生产 Vite config 增加一个只读 bundle assertion：在 Rollup `generateBundle` 阶段遍历每个 chunk 的
`modules`，若规范化 module id 命中以下任一前缀/文件则直接使 build 失败：

- `web/src/router/routes/modules/dev-tools.ts`；
- `web/src/views/dev-tools/`；
- `web/src/api/dev-tools/`。

该断言检查 Rollup 的真实模块图，不依赖 minify 后字符串是否碰巧保留。另生成/保留 Vite manifest，供
人工核对 dynamic imports；manifest 不是前端组件清单或模块真值，只是本次构建证据。

### 【涉及文件/接口】

- `web/src/router/routes/index.ts`：普通 route glob 排除 dev-tools，合并虚拟模块返回的派生 route 数组；
  继续调用 `routesForDeployment`。
- `web/src/router/routes/modules/dev-tools.ts`：保留唯一 `/dev-tools` route 定义和 `instanceTool: true`；
  所有 child 同门控。
- `web/config/vite.config.base.ts` / `web/config/vite.config.dev.ts` /
  `web/config/vite.config.prod.ts`：定义编译常量、生成虚拟模块，并在 production config 登记 bundle
  assertion。现有 base config 已有虚拟模块实现和 `define` 入口
  （`web/config/vite.config.base.ts:29-49`、`:81-83`），production config 已集中维护 Rollup output
  （`web/config/vite.config.prod.ts:6-26`）。
- `web/src/env.d.ts`：只声明新增的 build-time boolean 与虚拟模块类型；不扩展
  `VITE_DEPLOYMENT_MODE` 枚举。
- `scripts/package-release.sh`：继续消费唯一的 `web/dist`，不增加另一套 production route 清单。

### 【验收点】

1. development + `VITE_DEPLOYMENT_MODE=standalone` 启动时，`/dev-tools/code` 与
   `/dev-tools/modules` 均可解析；`routesForDeployment` 仍会拒绝未显式允许 instance tools 的值。
2. 分别以 production build + `VITE_DEPLOYMENT_MODE=standalone`、production build +
   `VITE_DEPLOYMENT_MODE=multi-tenant` 构建一次；两次 bundle assertion 都通过，证明所有输出 chunk 的
   `modules` 不含三个禁入前缀。
3. 两份 `web/dist` 的 Vite manifest 中不存在 dev-tools route/page/API 对应 entry 或 dynamic import；
   对全部 JS chunk 搜索稳定哨兵 `/dev-tools`、`DevToolsCode`、`core.module.install` 结果均为 0。
   字符串扫描是辅助证据，Rollup module graph assertion 是主证据。
4. 在 dev-tools page 增加一个唯一测试哨兵并故意静态导入到普通 route，production build 必须被 bundle
   assertion 拒绝；移除越界 import 后只重跑该 build，必须通过。这证明 gate 真正命中待排除代码。
5. 执行 `scripts/package-release.sh` 后，对 `server/public/admin/` 应用同一 chunk/module-manifest 核对，
   结果与 `web/dist` 一致；release 中不存在第二份 Admin Web 源码目录。
6. 即使手工请求生产后端的安装/sync URL，仍由 D3 后端环境与授权门控拒绝；“前端 chunk 不存在”不得
   被用作后端安全边界。
