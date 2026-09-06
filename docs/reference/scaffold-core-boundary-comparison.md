# 后台脚手架的 Core、公共模块与生成应用边界

> 本页比较 LikeAdmin PHP 开源版、FastAdmin、MineAdmin 和 RuoYi-Vue-Plus 的官方源码结构与具体机制链，用来帮助判断 Peanut Admin Core 应是技术工具集、公共后台底座，还是完整应用。它不比较产品功能完成度；产品侧对比见[产品能力与同类产品参考矩阵](product-capability-reference-matrix.md)。

## 对 Peanut Admin 的直接结论

四个脚手架没有给出唯一正确的分包方式。它们提供了三种可核实的边界样本：

- LikeAdmin 以 **project 仓**承载后台和项目内 common；FastAdmin 的 CRUD command/stubs 把产物写回 project。二者都不能仅凭公共目录或模板名称视为可独立升级的 Core。
- MineAdmin 同时有 **应用 project 和版本化 library**；上传协议/结果 DTO 在公共库，Attachment 元数据和应用 service 留在应用。这是“公共机制与产品记录分开”的直接例子。
- RuoYi-Vue-Plus 用 Maven reactor 区分 **common mechanism 与 system module**；OSS client 生命周期在 common，文件元数据查询在 system。这证明公共模块也可以含有连接生命周期实现，但其 Java/Spring 静态工厂不能直接移植到 Peanut 的 ThinkPHP 宿主。

Peanut Admin 当前适合继续采用“两个聚合发布载体 + 内部能力域 + 独立应用宿主”的定位：Core 可以是公共后台底座，拥有产品无关的服务、Schema、PDO 实现和 UI；应用拥有业务规则、业务表、业务 UI、框架路由和运行装配。某个能力应保留、复用、适配或抽取，要看实际调用面和 data owner，不能从同名目录、厂商数量或其他脚手架的包数量推出。两个 aggregate 是当前实际分发边界，不是永久最优的冻结设计。

## 四个官方源码样本

本次只读取 2026-09-07 固定的官方仓提交，不把移动分支、README 宣传或第三方文章当运行事实。

### LikeAdmin PHP 开源版：整仓应用内的 common

LikeAdmin PHP 开源仓固定在 [`79734cb1cbf004ced91634ce0bb5f619a515aa3f`](https://github.com/likeadmin-likeshop/likeadmin_php/tree/79734cb1cbf004ced91634ce0bb5f619a515aa3f)。后端 Composer 是 `type: project`，只把 `app\` 映射到本仓 `app`；管理前端是另一个 Vite manifest。当前可见的 `app/common` 是项目内公共命名空间，没有证据表明它是独立发布的 Core。

存储链路也在应用内闭合：[`StorageLogic`](https://github.com/likeadmin-likeshop/likeadmin_php/blob/79734cb1cbf004ced91634ce0bb5f619a515aa3f/server/app/adminapi/logic/setting/StorageLogic.php#L35-L65) 从 `ConfigService` 读取默认存储并列出 local/qiniu/aliyun/qcloud；[`setup()`](https://github.com/likeadmin-likeshop/likeadmin_php/blob/79734cb1cbf004ced91634ce0bb5f619a515aa3f/server/app/adminapi/logic/setting/StorageLogic.php#L128-L166) 写各引擎配置并清缓存。厂商 SDK 是 [server project 依赖](https://github.com/likeadmin-likeshop/likeadmin_php/blob/79734cb1cbf004ced91634ce0bb5f619a515aa3f/server/composer.json#L21-L36)，文件记录是 [`app/common` 的软删除 Model](https://github.com/likeadmin-likeshop/likeadmin_php/blob/79734cb1cbf004ced91634ce0bb5f619a515aa3f/server/app/common/model/file/File.php#L13-L22)。

这能证明设置、厂商依赖和文件 Model 可全部留在应用 project；没有证明统一 driver interface、client 作用域或独立 Storage 包。仓库 README 另行链接商业 SaaS，因此本页不把商业版能力归入开源基线。

### FastAdmin：项目内生成器和外部 Addons 依赖

FastAdmin 固定在 [`cc9802877ab3255d66776875ecffdfbff3a68afb`](https://github.com/fastadminnet/fastadmin/tree/cc9802877ab3255d66776875ecffdfbff3a68afb)。根 Composer 是 `type: project`，依赖 `karsonzhang/fastadmin-addons`、Queue 和 PhpSpreadsheet；CRUD generator 本身仍在项目的 `application/admin/command`。

[`crud` command](https://github.com/fastadminnet/fastadmin/blob/cc9802877ab3255d66776875ecffdfbff3a68afb/application/admin/command/Crud.php#L240-L278) 接收 table/controller/model/import/menu/force 等选项；`local=0` 时把 model 写入 [`application/common/model`](https://github.com/fastadminnet/fastadmin/blob/cc9802877ab3255d66776875ecffdfbff3a68afb/application/admin/command/Crud.php#L280-L301)，并排除 attachment、config、admin_log 等系统表。这里可以确认生成器产出项目源码和项目内 common model；本次源码未给出生成输出的长期 owner 或升级覆盖政策，所以保持未知。

FastAdmin 的样本支持 Peanut 把生成业务 controller/model/UI 视为应用源码，同时保留显式的外部扩展包边界。它没有提供证据要求 Peanut 把既有 Core 服务退回应用，也没有提供可直接照搬的升级规则。

### MineAdmin：版本化 Core/Upload 库与应用 Attachment

MineAdmin 应用固定在 [`5cda1790a37e4586a7a3e61ba80153b2da547482`](https://github.com/mineadmin/MineAdmin/tree/5cda1790a37e4586a7a3e61ba80153b2da547482)。应用 Composer 是 project，把 `App\` 映射到本仓 `app/`，同时声明 [`mineadmin/core`、`mineadmin/upload` 等版本化依赖](https://github.com/mineadmin/MineAdmin/blob/5cda1790a37e4586a7a3e61ba80153b2da547482/composer.json#L58-L65)。官方 `mineadmin/core` 是 `type: library` 并导出 `Mine\Core\`，其 [`BootApplicationSubscriber`](https://github.com/mineadmin/Core/blob/9a99b8f7fa7d92d14b67c82db2bd15873357c99e/Subscriber/BootApplicationSubscriber.php#L21-L48) 把应用 migration/seed 路径登记给 Hyperf；[`UploadSubscriber`](https://github.com/mineadmin/Core/blob/9a99b8f7fa7d92d14b67c82db2bd15873357c99e/Subscriber/Upload/UploadSubscriber.php#L12-L19) 提供 local adapter 订阅装配。

上传调用的职责划分很具体：应用 Controller 把上传文件转为临时 `SplFileInfo` 后调用 service；[`AttachmentService`](https://github.com/mineadmin/MineAdmin/blob/5cda1790a37e4586a7a3e61ba80153b2da547482/app/Service/AttachmentService.php#L24-L52) 依赖 `UploadInterface`，按 hash 去重，再把 mode/path/url 等结果写入 Attachment repository。Upload 库的 [`Factory`](https://github.com/mineadmin/Upload/blob/b90ec45b236f5a8db673aa7773d8bbf438abb29d/Factory.php#L17-L31) 通过事件取得 Upload，结果 [`DTO`](https://github.com/mineadmin/Upload/blob/b90ec45b236f5a8db673aa7773d8bbf438abb29d/Upload.php#L13-L25) 定义存储元数据。

这些是 MineAdmin 应用、Core 和 Upload 三个官方仓的固定源码样本；本次没有验证三者的共同 lock、联动运行、完整运行绑定、多云 provider 或租户隔离，不能把源码存在写成已组合运行或发布包已通过资格。

### RuoYi-Vue-Plus：common client 与 system 数据

RuoYi-Vue-Plus 固定在 [`bffc39a89fd6ed196031e71cbceefd9986eecce8`](https://github.com/dromara/RuoYi-Vue-Plus/tree/bffc39a89fd6ed196031e71cbceefd9986eecce8)。Maven 根 artifact 是 `ruoyi-vue-plus` 6.0.0，并导入 [`ruoyi-common-bom`](https://github.com/dromara/RuoYi-Vue-Plus/blob/bffc39a89fd6ed196031e71cbceefd9986eecce8/pom.xml#L144-L150)；源码对应 `ruoyi-common-oss` 和 `ruoyi-modules/ruoyi-system`。

[`OssFactory`](https://github.com/dromara/RuoYi-Vue-Plus/blob/bffc39a89fd6ed196031e71cbceefd9986eecce8/ruoyi-common/ruoyi-common-oss/src/main/java/org/dromara/common/oss/factory/OssFactory.java#L26-L72) 从 Redis 默认 key 和缓存 JSON 配置创建 client，按 key 缓存，并在配置变化时关闭重建；system 的 [`SysOssServiceImpl`](https://github.com/dromara/RuoYi-Vue-Plus/blob/bffc39a89fd6ed196031e71cbceefd9986eecce8/ruoyi-modules/ruoyi-system/src/main/java/org/dromara/system/service/impl/SysOssServiceImpl.java#L165-L188) 经 mapper 查询 OSS 元数据并按 `ossId` 缓存。根 POM 还明确列出 S3、FESOD sheet 和 SnailJob 依赖。

这个样本能证明连接/client 生命周期可以由 common 机制负责，而 system module 保存文件记录。Peanut 当前约束是 Tenant/account/credential 可变配置由应用每次解析且不缓存可变 client；RuoYi 的静态工厂与 Redis 方案只是职责对照，不是迁移方案。README 的多租户表述也不是本次隔离链审计证据。

## 横向比较

| 维度 | LikeAdmin PHP | FastAdmin | MineAdmin | RuoYi-Vue-Plus | Peanut Admin 的含义 |
| --- | --- | --- | --- | --- | --- |
| 发布粒度 | 后端 project + 独立前端工程 | 单 project + Addons 依赖 | 应用 project + 多个版本化 library | Maven reactor 的 common/modules/admin | 保留 PHP/Web 两聚合包；内部域无需为目录数量各拆一包 |
| 公共后台能力 | 项目内 `app/common` | 项目内 common/model 与命令 | `mineadmin/core` 提供启动装配等 | common BOM 与多个 common module | Core 可包含服务、Schema、PDO 和 UI，不限于工具函数 |
| 生成应用 | 本次未核实生成器/升级覆盖规则 | CRUD command/stubs 生成项目文件；长期 owner/升级规则未核实 | 应用 project 消费 library；生成规则未核实 | 本次未核实 generator 实现与升级覆盖规则 | Peanut 的 controller/model/UI 归应用；升级器只覆盖已声明为 managed 的文件 |
| Storage 机制 | 设置 logic、SDK、File Model 同 project | 本次未核实统一机制 | Upload 协议/DTO 与应用 Attachment 分离 | common 管 client，system 管元数据 | 可以抽窄 driver；凭据、用途、授权、账本和补偿继续由应用负责 |
| 二次开发 | `app/common` 位于 project；具体升级规则未核实 | 生成 CRUD 与 Addons | 应用 service/repository 在 project | system module 承载业务 | 业务 Module 与 ThinkPHP Model/Scope 留在应用，不为分包增加 Repository 层 |
| 升级边界 | 本次未核实覆盖规则 | 本次未核实生成物 owner | Composer library 提供版本边界 | Maven 模块/BOM 提供版本边界 | 只有 package/managed 文件可自动升级；app-owned 与 secret 冲突时 fail-closed |
| 租户证据 | 开源仓未做隔离链审计 | 未审计 | 未审计 | README 声明，未审计隔离链 | 继续以 Peanut 自身可信 Context、RBAC、TenantScope 和资格 Gate 为准 |

## 五个当前边界议题

脚手架证据只能帮助选择职责，不能替代 Peanut 自身调用审计。结合[Core 能力与应用采用全景](core-capabilities-and-application-adoption.md)，五个议题可按以下方式继续：

| 议题 | 已有 Peanut Core | 独立应用现状 | 当前决定 | 最小后续任务与验收 |
| --- | --- | --- | --- | --- |
| Settings | typed definition、scope/revision/ETag、secret、Schema/PDO | ImportExport 等已用 Core Settings；业务 key/default 与宿主 protector 在应用/Module | **保留已有能力复用** | 按真实 key 检查重复语义；验收 scope、secret、ETag，不把所有设置一刀切归任一仓 |
| Storage | Core dev 有四操作 driver、key 校验和四 provider 实现；高层 FileMedia 另有 Schema/runtime | canonical 应用仍拥有 driver、凭据/account/space、用途、授权、账本、补偿和生命周期；`590e618` 只是未合候选 | **候选抽取，先复审后决定采用** | 决定后再做不可变 split、精确 lock、Host 装配和既有 FileMedia 安全 Gate；不因 MineAdmin/RuoYi 样本合并整个 FileMedia |
| Crontab/Task | Core TaskJob 有 ledger/lease/retry/cancel/worker | 官方 Task Module 已装配 Core，业务任务/cron 规则在应用 | **已有能力复用，保留应用策略** | 防止第二套队列；新增任务验收 Tenant、授权复核、lease/retry/cancel |
| ImportExport | Core ImportExport 组合 FileMedia 与 TaskJob | 官方 Module 已采用，应用注入格式、文件和日志 adapter | **已有能力复用，宿主适配** | 只为具体数据类型补 handler；验收格式、权限、任务、失败补偿和业务落库 |
| Logs | Kernel Audit 与 OpsConsole 提供中立审计/诊断合同 | 应用拥有业务操作日志、真实 log source、脱敏和用途 | **按日志语义分别复用/适配** | 先区分业务审计与诊断日志；分别验收 audience/Tenant/权限、脱敏和查询来源 |

## 如何决定新的提取

对每个候选能力先回答以下问题，再改 Runtime：

1. 当前 Core 是否已有同语义的服务、合同、Schema 或 UI，独立应用是否已经通过真实入口采用？
2. 状态和表属于产品无关后台机制，还是某个业务 Module？Core 拥有中立 Schema 与“业务表归应用”可以同时成立。
3. 宿主需要注入哪些不可变依赖、可信 Tenant、凭据、provider、路由和生命周期？升级时谁能安全覆盖哪些文件？
4. 最小公共调用面是否能由现有调用链描述，并且不会把 ThinkPHP Model/Scope 包成第二套 Repository？
5. 验收能否固定到不可变包/lock、真实宿主调用和直接安全 Gate，而不是目录存在、测试 fixture 或参考宿主示例？

本轮不据此自动实施新迁移。Core Storage Driver 源码保留，应用 `590e618` 候选也保留；是否正式采用，要在全景审计后由后续实现任务落到新的不可变版本、下游 lock 和一次最低充分验证。

另一个独立后续项是评估 aggregate 发布成本：比较维持 PHP/Web 两个聚合包和拆出少数独立发布单元时的依赖图、兼容矩阵、版本联动、失败原子性与下游升级步骤。未完成这份比较前，不因内部 13 个 PHP 域自动拆 13 个包，也不假设移动文件就能消除应用 lock 和升级冲突。

## 证据限制

所有外部链接均固定到上述官方仓 40 位 commit。已读取的是 manifest、目录和代表机制链；没有运行四个项目、审计完整插件生态、验证所有 provider、证明升级兼容或核验端到端 Tenant 隔离。文中的“未核实”表示本次证据边界，不表示能力绝对不存在。
