# Consumer-ready 最小交付控制器

Document ID: `pa-docs-operations-consumer-ready-control`

Status: `current`

Owner: `release-qualification`

Audience: `maintainer, architect, ai`

## 当前定位

`scripts/consumer-ready-control` 当前是 Peanut Admin 仓内的**最小、只读、preflight-only
控制器**。它把此前散落在聊天、手工命令和多个脚本之间的准备检查汇总成一个结构化入口，目标是
在 seal、P0-E 或发布动作之前一次性暴露可避免的问题，而不是增加新的资格 Gate。

机器策略位于 `resources/consumer-ready-control.json`。当前实现不会修改版本、生成 scaffold、
claim 资源、连接数据库、启动端口/容器/浏览器、运行 P0-E、创建 tag 或发布 GitHub Release。
这些动作仍由现有权威脚本负责。

## 当前功能

控制器提供一个命令和四个检查阶段：

```bash
scripts/consumer-ready-control preflight --phase prepare
scripts/consumer-ready-control preflight --phase seal --check-remote
scripts/consumer-ready-control preflight --phase qualify --check-remote --run-id <run_id>
scripts/consumer-ready-control preflight --phase release --check-remote \
  --qualification /absolute/path/to/summary.json
```

它统一检查：

- candidate 是否由 Git 解析为完整 commit/tree、是否等于当前 HEAD、工作树是否干净；
- `release-versions.json`、`RELEASE_METADATA.json`、application inventory、P0-E fixture 与
  capability ledger 是否描述同一目标版本；
- 本地 tag 是否未占用；使用 `--check-remote` 时，同时只读检查远端 tag 和 GitHub Release；
- scaffold manifest、固定 source commit/tree、inventory、fixture 摘要与文件数是否一致；
- application inventory 与 scaffold 制品能否通过已有严格 `--check`；
- P0-E fixture 场景是否与资源登记白名单一致，最长合法 run ID 生成的每个数据库名是否仍在
  MySQL 64 字符限制内；
- 项目资源登记、P0-E 远程管理绑定、活跃租约和浏览器 CLI 是否满足进入资格前的条件；
- qualify 阶段是否来自当前 `origin/main`，run ID、输出目录、cache 目录和派生数据库名是否安全；
- release 阶段的资格 summary 是否属于同一 main commit/tree、八组均通过且零资源残留。

输出固定为 JSON。`status=ready` 时退出码为 `0`，`status=blocked` 为 `1`，控制器自身或策略错误为
`2`。每个失败包含稳定 `code`、实际 `detail` 和最小 `remediation`，调用者不应从自由文本猜测下一步。

## 使用边界

控制器是准备门禁，不是执行器：

- `ready` 不等于 qualified、release-ready 或已发布；
- 它不能替代 `scripts/project-resource-lease claim`；
- 它不能替代 Development 聚焦验证或 `scripts/p0e-runtime-qualification`；
- 它不会自动修复版本、清理他人资源、创建数据库或选择 fallback；
- `--check-remote` 只读访问远端；网络或 GitHub 身份不可用时必须停止，不得把未知当成“版本可用”；
- 当前只服务 Peanut Admin consumer-ready 源码交付，不是跨项目通用平台。

## 为什么先做最小版本

本轮已反复出现工具未准备、数据库物理名超长、手工 SHA 错误、DB 地址遗漏、空库阶段交接缺失、
scaffold/fixture/产品版本错位等问题。它们多数能在不启动 Runtime 的情况下提前发现。先把这些高频、
低成本、可确定的检查收敛成一个入口，比立即建设会自动改版本、claim 资源和发布的复杂编排器更安全。

最小控制器至少经过两个到三个真实固定候选后，才能根据实际数据决定哪些步骤值得自动化；不能把一次
会话中的临时处理直接固化为跨项目规则。

## 后续升级路线

### 阶段 2：持久候选状态机

在有重复证据后，引入机器状态 `development → release-prepared → scaffold-sealed → main-frozen →
qualified → released`，并记录候选失效原因。状态文件只保存身份和证据引用，不保存凭据或动态资源内容。

### 阶段 3：项目内执行适配器

把版本准备、seal、资源 provision、qualification 和 cleanup 作为独立适配器接入。每个适配器必须先
支持 dry-run、幂等和结构化恢复指针；资源动作仍只能消费项目登记并持有唯一租约。

### 阶段 4：跨项目提取

只有至少两个项目反复证明相同的状态和错误合同后，才把通用的身份、状态、证据和停止线提取为共享
CLI 或 Skill。数据库、端口、部署权限、业务 Gate 和清理责任继续由各项目配置与适配器拥有。

## 升级条件

满足以下条件前保持当前最小形态：

1. 至少两个真实候选使用同一 preflight，且失败代码能稳定解释实际阻塞；
2. 没有出现控制器误报 ready 后才可由无资源检查发现的身份或准备缺口；
3. 自动化动作有明确 owner、回滚、幂等和资源租约合同；
4. 新能力减少总重试与主线程上下文，而不是只把现有 shell 命令包一层。
