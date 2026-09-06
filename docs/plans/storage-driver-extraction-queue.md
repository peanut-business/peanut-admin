# Storage Driver 提取执行队列

> 当前开发候选，未发布/未正式采用。本文是执行合同，不是发布或下游消费证据。
>
> 目标：在应用与 Core 之间落地已冻结的存储边界。本文是执行合同，不是完成证据。

## 队列总则

每项任务必须使用列出的前置 commit，严格限制写集，并把实现、依赖、验证和证据绑定到同一候选树。文档任务不运行 Runtime 检查；Runtime 任务按 owner 的一次最低充分验证执行，失败只允许一次聚焦诊断和一次失败组重跑。未发生的发布、候选封存和资格不写成完成。

Q0 原则与文档：Luna / medium。Q1 合同及 DDR：Sol / high。Q2 Core 驱动：Sol / high。Q3 应用装配与精确包消费：Sol / high。Q4 普通注释：Luna / medium；复杂注释实现：Sol / high。Q5 聚焦验证与集成：Sol / high；主代理终审。

## 任务矩阵

| ID | 前置 commit | 精确写集 | 禁止项 | 依赖/可并行 | 验收与 owner | 状态/证据 |
| --- | --- | --- | --- | --- | --- | --- |
| Q0 | 应用 `f74d841b4e084dbb8b5ec4a2d6312494042b0d10`；Core `cefb050002b455747c20bb0790864d6f50eb24d8` | 两仓边界、队列、入口登记与注释规则 | Runtime、依赖、Schema、测试、自检脚本 | 无；可与 Q1 研究并行 | 文档差异与主代理审计；Luna/medium | 已完成；主代理审计及 Luna docs check 通过 |
| Q1 | 应用 `f74d841b4e084dbb8b5ec4a2d6312494042b0d10`；Core `cefb050002b455747c20bb0790864d6f50eb24d8` | `packages/php/file-media/src/Storage/{StorageDriver,StorageObjectKey,StorageHttpTransport}.php`；四 Driver；`packages/php/composer.json`；`docs/decisions/dependencies/p1-storage-drivers.{md,json}` | Flysystem、新包、应用生命周期/Schema、三厂商 SDK 强绑 Core | 合同与 DDR 已实现；Q2/Q3 源码可并行；正式消费仍受 Q3 门禁 | Core 实现 owner：Sol/high；文档登记 owner：Luna/medium | 已完成源开发集成：Core commit `9358686fee873dd235489c8794abf556fd70ec4f`，tree `0b4edeefe72e4fbfd20cbfb6e05f89f7b03f17a7`；尚未发布或正式消费 |
| Q2 | Q1 合同已冻结；应用 `f74d841b4e084dbb8b5ec4a2d6312494042b0d10`；Core `cefb050002b455747c20bb0790864d6f50eb24d8` | `packages/php/file-media/src/Storage/{StorageDriver,StorageObjectKey,StorageHttpTransport}.php`；`packages/php/file-media/src/Storage/Driver/{LocalStorageDriver,AliyunStorageDriver,QcloudStorageDriver,QiniuStorageDriver}.php`；`packages/php/composer.json`；DDR 两文件；`resources/project-resources.json`（既有 lint resource 增加 development 覆盖） | `app` 引用、业务账本、Tenant/授权、现有另一 FileMedia 生命周期、表变更 | 与 Q3 源码实现并行；不得各自改同一合同文件 | Core lint：`for f in packages/php/file-media/src/Storage/StorageDriver.php packages/php/file-media/src/Storage/StorageObjectKey.php packages/php/file-media/src/Storage/StorageHttpTransport.php packages/php/file-media/src/Storage/Driver/LocalStorageDriver.php packages/php/file-media/src/Storage/Driver/AliyunStorageDriver.php packages/php/file-media/src/Storage/Driver/QcloudStorageDriver.php packages/php/file-media/src/Storage/Driver/QiniuStorageDriver.php; do /opt/homebrew/opt/php@8.3/bin/php -l "$f" || exit 1; done`；Composer：`/opt/homebrew/opt/php@8.3/bin/php /private/tmp/peanut-admin-core-tools/composer-2.10.2 validate --no-check-publish --working-dir packages/php`；Sol/high | 已完成源开发集成：Core commit `9358686fee873dd235489c8794abf556fd70ec4f`，tree `0b4edeefe72e4fbfd20cbfb6e05f89f7b03f17a7`；Composer version warning 非失败，资格/发布未执行 |
| Q3 | Q1 合同经主代理冻结后的同一候选；正式消费另需 immutable split+lock | `server/app/AppService.php`；`server/app/common/service/storage/{StorageDriverFactory,ObservedStorageDriver,StoragePath,StorageRepository,QiniuStorageHttpTransport}.php`；应用删除旧 `StorageDriver.php` 与 `driver/{Local,Aliyun,Qcloud,Qiniu}StorageDriver.php`；`server/tests/Productization/FileMediaHostTest.php` | alpha.12 伪替代、path/vendor 伪替代、兼容桥、Repository/洋葱层、双写 | 源码可与 Q2 并行；合入/正式消费阻塞于新 immutable split 与 lock | `/opt/homebrew/bin/php -l` 受改 PHP；`cd server && /opt/homebrew/bin/php tests/Productization/FileMediaHostTest.php`；Sol/high | 应用源码已实现并通过 PHP lint 与 `PB04-FILE-MEDIA-HOST-001`，但未正式采用；immutable split/lock 外部阻塞 |
| Q4 | Q2/Q3 当前实现 | 新增或实质修改类/方法注释；复杂流、临时文件、Tenant/授权/副作用说明 | 为标准 CRUD/访问器/纯构造函数堆注释；顺手改无关领域 | 已随 Sol 实现；主代理审计通过 | 注释静态审计；Luna/medium 或 Sol/high | 已完成；审计通过 |
| Q5 | Q1-Q4 当前开发候选 | 仅集成登记、证据和本任务允许的文档状态文件 | 新功能、Schema/UI、HTTP 整文件下载、正式消费、发布 | Q2/Q3/Q4；主代理终审收敛 | Core Composer 静态边界、两仓 docs check 已通过；主代理终审 | 部分完成；正式消费待新 immutable split |

## 不可变依赖与提取门禁

Q3 的正式消费必须等待新的 immutable Composer split 版本及其 lock 证据。当前 `alpha.12` 没有新类，不能以 path repository、vendor 复制或未锁定分支作为替代。Aliyun/Qcloud/Qiniu SDK 只能按 accepted DDR 由 Core aggregate 以 Composer `suggest` 揭示；应用 Host 必须显式 require 并锁定精确版本后才能构造对应 Driver，未安装不得启用。Provider SDK 的具体实例由应用注入；Core aggregate 不因四个 Driver 而强制厂商依赖。

Q2/Q3 必须证明账户空间路由、凭据解密、用途、授权、对象账本、补偿和 `ObservedStorageDriver` 仍由应用拥有；Core 不能引用应用或现有另一条 FileMedia Schema。Tenant 由宿主 HTTP/Worker 建立，缺失即拒绝。标准 CRUD 可免方法注释，Tenant 权限、事务、幂等、软删和外部副作用不可免。

每次操作先解析完整配置快照；Core 不读取 Settings 或当前 Tenant 的全局可变值，也不缓存可变 client。对象按账本 space 定位，prefix 不代替授权，不新增 fallback。应用必要的 Core 技术接口宿主 adapter 属于本次跨仓合同装配，不能被误判为 ThinkPHP Repository/洋葱层。

## 外部停止线与发布编排

当前 `alpha.12` source release 不含新类，正式消费必须等待新的不可变 PHP split 包身份及其 lock；`alpha13` 只是下一候选可用名称，不是发布事实。现有 `release.yml` tag 触发会同时强制 PHP/Web 版本并发布 npm；发布范围仍 pending 用户选择，本轮不启动尚未选择的 PHP/npm 联动步骤，也不修改发布脚本、版本、资源或锁。

应用 Tenant 资格测试 `OfficialCapabilityTenantQualificationTest.php` 未运行：该 worktree 未安装 vendor，且本任务禁止安装或复用依赖。该停止线只阻塞对应资格，不影响已完成的 PHP lint 和 FileMedia Host 聚焦验证。
