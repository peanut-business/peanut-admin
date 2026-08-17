---
title: 身份与租户边界
description: Account、PlatformOperator、TenantMember、业务会员和入口租户解析的边界合同。
---

# 身份与租户边界

## 5 分钟速读

Peanut Admin 有三条身份链：平台操作身份、租户管理成员、业务客户会员。它们可以在业务上关联，但
Session、RBAC、Token 和数据范围不能互换。

```text
PlatformOperator session -> PlatformRole -> platform.*
Account/Credential      -> TenantMember -> TenantRole -> tenant.*
pa_member login         -> Member token -> customer/member domain
```

`Account` 只表示登录载体；`TenantMember` 表示某个账号加入某个 Tenant；`Role/Permission` 决定该成员在
当前 Tenant 能做什么；`pa_member` 是独立的业务客户档案。客户等级、积分、供应商资质和合同不能写入 Account。

## 术语表

| 术语 | 人话解释 | 典型误解 |
| --- | --- | --- |
| Application Instance | 一套独立部署、数据库和生命周期 | 不是一个 Tenant，也不是前端菜单分组 |
| Platform | 管理当前实例中 Tenant/入口/Module 的独立控制面 | 不是 Tenant 后台的子应用，也不是跨实例运营平台 |
| Account | 可验证的登录账号 | 不等于客户、供应商或员工档案 |
| TenantMember | Account 加入某个 Tenant 的成员关系 | 一个 Account 可以有多条，所以可以切换多个 Tenant |
| Tenant Role/RBAC | 成员在当前 Tenant 的功能权限 | 不会自动赋予业务数据的全部行权限 |
| `pa_member` | 当前应用的客户侧业务会员 | 不能用会员 Token 登录管理端 |
| TenantModule | 某个 Tenant 是否开通一个 Module | 不等于 Plugin 已安装，也不等于成员已有权限 |

## 当前支持与边界

| 能力 | 当前仓库 | 说明 |
| --- | --- | --- |
| 平台身份 | 当前已支持 | 独立会话、PlatformRole、权限、审计；不隐式进入 Tenant |
| TenantMember | 当前已支持 | Account 可加入多个 Tenant；每个 Tenant 单独角色和授权版本 |
| Owner | 当前已支持 | `core.tenant-owner` 内置角色；邀请、接受、追加 Owner 和最后 Owner 保护 |
| 客户业务身份 | 当前已支持 | `pa_member` 独立注册、登录、标签、余额和 Tenant 隔离 |
| Supplier/Store/Customer 业务主体 | 推荐新增到派生应用 | Peanut 不内建 Party、Relationship、合同或供应商成员管理 |
| 跨应用身份联邦 | 暂不建议 | 只有多个真实实例稳定协作并有独立生命周期后再设计 |

## Tenant 选择、切换和域名

登录后看到的 Tenant 列表来自该 Account 的 active `TenantMember` 关系，不来自 PlatformOperator 记录。
选择和切换会重新校验 Tenant 状态、成员状态、角色权限版本和会话版本。域名入口绑定是持续访问边界，
不是一次性的登录预选，也不能替用户加入 Tenant：绑定 Host 上只能建立和使用该 Tenant 的管理会话，
不能切换到其他 Tenant；未绑定的公共入口才允许在该账号的 active 成员列表中选择和切换。
“未绑定”不等于接受任意域名：公共入口必须列入 `TENANT_ADMIN_HOSTS`，其他未知 Host
fail closed；Platform API 则只接受 `PLATFORM_HOSTS`。

推荐的请求顺序是：

1. 根据 Host/client 解析绑定 Tenant；未绑定入口才接受成员选择；
2. 校验 Tenant active；
3. 校验 Token 对应的 Account/TenantMember；
4. 校验 Role/Permission 和数据权限；
5. 业务 Service 只接收可信 `TenantContext`。

因此二者不会冲突：Host 先给出允许的 Tenant 范围，Tenant 切换只能在这个范围内进行。Alpha
绑定域名携带 Beta Token、在 Alpha challenge 中选择 Beta、或从 Alpha 会话发起切换，后端都会
拒绝；前端隐藏切换入口只是体验优化，不是安全边界。

业务关系存在不等于数据权限。供应商 Tenant 参与一张采购单，只能根据采购模块的 participant grant 读取
采购单允许字段，不能由 `supplier_tenant_id` 直接读取采购方所有表。

### 同一实例中的跨 Tenant 业务关系

Peanut Admin 只提供 Tenant、成员和授权原语；下面是派生应用应采用的边界，不是当前内建采购 Runtime：

1. 采购方 Tenant 拥有采购单权威记录和状态机。
2. 供应商 Tenant 通过显式 Relationship/Contract/ParticipantGrant 被授权参与同一业务单据。
3. 供应商读取的是采购模块允许的字段或只读投影，不是采购方数据库权限。
4. 商品授权、价格、确认、收货和库存命令每次都校验双方 Tenant、关系状态、有效期和最小权限。
5. 收货成功后由采购模块调用库存公开命令；库存拥有余额、预占和流水，采购模块不能直接改库存表。

若双方必须独立发布、独立数据库或处于不同安全边界，才拆为多个应用实例并通过 API/事件协作。
跨实例 global subject -> local tenant 映射是另一项复杂能力，当前不实现。

## 三类租户映射

| 类型 | 用途 | 当前结论 |
| --- | --- | --- |
| legacy Admin/Role/Dept -> Core | 旧单租户升级兼容 | 仅迁移需要；2.0 fresh-only Runtime/Schema 不保留 |
| Supplier/Store/Customer -> Tenant/Member | 同一应用的业务主体和成员关系 | 推荐由 DCS 显式建模；关系、合同、授权和有效期独立于 Tenant 类型 |
| global subject -> local tenant | 多个应用实例之间识别同一组织 | 暂不建议；不是当前 Runtime 通用合同 |

## 最小权限检查清单

- 任何业务表的 `tenant_id` 必须由可信上下文写入或参与复合查询条件。
- 模块私有表只能由模块自己的 Repository/Service 访问。
- 跨 Tenant 读取必须有明确的 participant/target grant、有效期和审计；不能接受前端提交的 `tenant_id` 作为授权。
- PlatformOperator 不能调用租户业务 Service；如未来需要代运营，应另建限时、双边审计的 SupportSession。
- 停用 Tenant、成员或角色后，已有会话和异步任务必须在下一次授权检查中失败。
