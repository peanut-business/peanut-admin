# PB04-03 字典 / Reference Codes Host 合同

> 状态：Accepted
>
> 应用前置提交：`ae8e2034d8d090c641ede4187e527616ffb19973`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB04-REFERENCE-CODES-HOST-001`

## 1. 决策

PB04-03 保留应用 `pa_dict_type` / `pa_dict_data` 为 Peanut Admin 租户扩展字典的唯一管理 Runtime，
并增加独立的只读系统参考码层 `pa_system_dict_type` / `pa_system_dict_data`；不切换核心 Reference Codes：

- 应用拥有字典类型、数据项、LikeAdmin 字段/状态/删除语义、ThinkPHP HTTP、管理页面和表迁移。
- 系统参考码层只提供部署级固定枚举读取；系统项由迁移种子维护，租户不能通过字典 CRUD 修改或删除。
- 业务读取按“系统项 + 当前租户扩展项”合并，重复 `value` 以系统项为准；第一阶段不支持租户覆盖系统项。
- 核心 `PeanutAdmin\ReferenceCodes\` 仍是 Composer `peanut-admin/core` 内部命名空间，不是独立包；应用不得 deep import。
- 当前应用没有消费核心 Reference Codes；系统参考码层不提供租户 CRUD，也不新增 PHP/Web override slot。
- 核心候选没有 Peanut Admin 下游采用授权；Alpha.2 包发布资格不能替代独立采用决策。

## 2. 不等价事实

| 维度 | 应用字典 | 核心 Reference Codes |
|---|---|---|
| schema | `pa_dict_type`、`pa_dict_data`（租户扩展）；`pa_system_dict_type`、`pa_system_dict_data`（系统只读） | `pa_reference_code_set`、`entry`、`entry_version` 三表 |
| scope | 租户扩展按 Tenant 隔离；系统参考码部署级共享 | Tenant-owned |
| 标识/版本 | 类型标识可编辑并事务同步数据项 | code 不可变、版本追加、退休永久保留 |
| 并发/API | LikeAdmin CRUD/status envelope | 强 ETag、`Idempotency-Key`、稳定 Problem Details |
| 权限 | `dict/type/*`、`dict/data/*` | `peanut.reference-codes.read/manage` |

这不是 repository 名称映射即可解决的差异。未来只有在固定核心资格、独立下游采用决定、字段/状态/API 映射、历史数据迁移与回滚合同全部完成后，才允许重新评估切换。

## 3. 应用唯一链与固定语义

唯一链为：

```text
DictTypeController / DictDataController
  → DictTypeValidate / DictDataValidate
  → DictTypeLogic / DictDataLogic
  → DictType / DictData
  → pa_dict_type / pa_dict_data

业务选择器读取：`DictDataLogic::byType` → `SystemDictRepository` + `DictTenantRepository`
```

固定现有已验收语义：

1. 类型列表支持名称、标识、状态筛选；`all` 只返回启用类型。
2. 数据列表按类型、名称、状态筛选；`byType` 只返回启用数据项。
3. 活跃类型标识由应用校验唯一；软删类型的标识允许复用。当前不宣称数据库级并发唯一。
4. 编辑类型在事务内锁定类型行，并同步所有未删除数据项的冗余 `type_value`。
5. 类型仍有数据项时拒绝删除；不级联删除数据。无效类型/数据 ID 不伪成功。
6. 类型和数据项状态各自独立；本切片不新增未获产品决定的级联禁用规则。
7. 管理端删除确认文案必须说明“存在数据时不能删除”，不能暗示级联删除。

## 4. 数据、权限、安全与错误

- schema/migration owner 是应用仓；系统参考码只允许追加迁移和固定种子，不开放租户写入。
- 所有管理写接口继续经过 Login、Auth 与 OperationLog middleware；不新增匿名或跨 audience API。
- 类型改名/占用删除继续使用现有事务与行锁。失败返回现有业务错误 envelope，并保持数据不变。
- 字典值可能被外部或未入库消费者保存；静态图谱未发现引用不等于可以级联删除，因此默认 fail-closed 拒绝占用类型删除。
- 核心的 Tenant 隔离、ETag、幂等、审计和版本语义不被伪装成当前应用能力。

## 5. 精确写集与禁改集

允许写入：

- `server/tests/Productization/ReferenceCodesHostTest.php`；
- `server/app/common/service/dict/SystemDictRepository.php`；
- `server/database/migrations/20260820-system-dictionary-layer.sql`；
- `.github/workflows/ci.yml`，仅登记所有权测试；
- `web/src/views/system/dict/locale/zh-CN.ts`、`en-US.ts`，仅修正删除确认文案；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/architecture/core-application-capability-graph.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`。

禁止把系统参考码接入核心 Reference Codes、修改 Dict Controller/Validate/Model 的租户 CRUD 语义、开放系统项写接口、修改核心仓、`vendor/`、`node_modules/` 和其他 PB04/产品领域。若静态检查发现 Runtime 语义与封存证据冲突，停止并另立 Runtime 合同。

## 6. 测试 owner 与最低证据

T01 已经一次完成类型改名同步、占用/无效删除拒绝、解除占用后删除、页面数据可见与可恢复清理：

- `output/playwright/t01/system-tools-core-summary.json`；
- `output/playwright/t01/frontend-summary.json`。

这些行为验收已封存，PB04 不重复数据库/API/浏览器流程。`PB04-REFERENCE-CODES-HOST-001` 只执行一次无写入所有权检查，证明：

1. 两份封存证据仍为通过且字典夹具为零；
2. 应用字典仍只有固定 Controller/Validate/Logic/Model 链；
3. 类型改名事务同步、占用拒删和启用数据选择器的关键 wiring 仍存在；
4. 字典 Runtime 未导入核心 Reference Codes 或核心三表；
5. 中英文删除文案与拒绝级联语义一致。

执行命令：

```bash
cd server
/opt/homebrew/opt/php@8.3/bin/php tests/Productization/ReferenceCodesHostTest.php
```

实现 owner 另运行一次新增 PHP lint 与一次最终 `git diff --check`。不重跑 T01、LikeAdmin parity、数据库写入、Web build/typecheck 或浏览器。

## 7. 停止线

通过只表示应用租户字典与系统参考码层的唯一 owner、核心未消费边界与测试证据已固定。它不批准核心 Reference Codes 下游采用，不修改核心 P1 状态，不迁 Tenant schema，不开始文件/素材、任务、运维或产品字典定义迁移。

## 8. 实施证据

- 应用与核心各自完成限定只读图谱：应用仅有 DictType/DictData 一条 Runtime；核心为不等价的 Tenant 三表版本化候选，且无 Peanut Admin 下游采用授权。
- PHP 8.3 下新增测试 lint 通过，`PB04-REFERENCE-CODES-HOST-001` 一次通过。
- 测试只读取封存 T01 证据和当前源码/文案，没有数据库、API 或浏览器写入；未重复 T01 或 parity 验收。
- 中英文确认文案已从“级联删除”改为“存在数据时拒绝删除”，与现有 Runtime 和用户手册一致。
- 核心仓未修改，既有 `.playwright-cli/` 未触碰；应用 Dict Runtime、schema、路由与权限字符未修改。
