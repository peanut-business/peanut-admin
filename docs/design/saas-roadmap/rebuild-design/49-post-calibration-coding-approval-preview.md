# Peanut Admin 校准后编码放行预览

> 状态：Approved and Consumed（2026-07-15）
>
> 用途：不依赖技术术语，说明批准后会做什么、P0 最终得到什么
>
> 用户已经使用文末完整批准语放行 P0-A；P0-A01 执行结果见 50 号文档

## 1. 我们现在要做的到底是什么

Peanut Admin 是一个干净、可复用的多租户管理后台底座，同时提供前后端可安装依赖包、项目脚手架和参考管理后台。

它不包含 DCS 的商品、库存、门店经营，也不包含 Finance Manager 的财务业务。DCS 和后续新项目可以按需要基于它开发自己的 Module；Finance Manager 已基于 LikeAdmin PHP 开发并基本成型，继续沿用现有基线，不迁移 Peanut Admin。

## 2. 用户怎样进入系统

```text
邮箱和密码
-> 找到 Account
-> 选择 Tenant
-> 找到 TenantMember
-> 加载这个成员的角色、功能权限和数据权限
-> 进入 Tenant 已开通的 Module
```

平台管理人员使用另一套平台入口和会话，只管理 Tenant、Module 和平台治理，不自动进入客户业务。

## 3. Tenant 里能有什么

Tenant 表示使用系统的公司、团队或经营组织，不表示某一家门店或仓库。

一个 Tenant 可以同时有：

- 多个门店；
- 多个仓库；
- 多个供应商；
- 多个批发商；
- 以后由不同 Module 定义的其他类别和实例。

即使一个项目只有一家公司和一家门店，仍是“一个 Tenant + 一个 Store”。

## 4. 一个成员怎样管理多个目标

一个成员只保留一条 TenantMember，不会因为管理三家门店而复制三条身份。

权限可以表达：

```text
查看：门店 A、B、C
修改：门店 A
查看：仓库 W1、W2
调整：仓库 W1
```

成员可以长期拥有多个目标的权限，但每一次普通写操作仍明确一个主要目标。跨多个目标的汇总默认只读；需要把一份策略发到多个目标时，保存一份策略并记录逐目标发布结果；通用批量跨目标修改在 P0 关闭。

## 5. 页面怎样表现

| 当前可用目标 | 页面行为 |
| --- | --- |
| 0 | 显示没有可用目标，不允许手填 ID 绕过 |
| 1 | 自动选择，可隐藏选择器；请求仍明确携带目标 |
| 多个 | 显示搜索/分页选择器；跨目标列表显示归属 |

门店、仓库等选择只属于当前页面和操作，不进入登录会话。切 Tenant、关闭 Module 或退出后必须清空。

## 6. 全站和 Tenant 数据怎样共存

Peanut Admin 区分：

- 平台治理数据；
- Tenant 私有数据；
- 门店、仓库等业务目标数据；
- 有统一真相但归属和作用范围不同的共享主档。

共享主档只保留一套记录和一套 ID。以 DCS 商品为业务解释时，平台建立的商品和门店自采商品都进入统一 Product/SKU 主档，通过创建者、归属者、维护者、可见、可采购、可销售和可库存范围区分；不是两张商品表，也不由前端 UNION。

Peanut Admin P0 不实现商品，而用虚构 ReferenceItem 证明这项通用契约。

## 7. Module 怎样组织

P0 使用三个虚构 Module 验证边界：

```text
example.target
  -> 拥有 Project / Queue 目标类型

example.reference
  -> 依赖 target
  -> 拥有统一 ReferenceItem 主档和作用范围

example.work-item
  -> 依赖 target / reference
  -> 拥有 WorkItem、策略和发布事实
```

同一个 Module 服务 Project A/B/C，不为每个 Project 安装一套代码。Module 之间只调用公开 Contract，不直接读写或 JOIN 对方内部表。

## 8. P0 完成后得到什么

P0 完成后，将得到一个可以继续开发真实项目的内部 alpha 底座：

- ThinkPHP 8 + PHP 8.3 后端；
- Vue 3 + TypeScript + Vite + Element Plus Admin Web；
- Account、Credential、Tenant、TenantMember；
- Department、Role、Permission、DataPermission；
- 平台和租户独立登录、会话与管理工作区；
- Module/TenantModule、菜单、迁移和扩展契约；
- typed target、多同类目标、单目标写和共享主档示例；
- 审计、幂等、错误协议和 OpenAPI；
- 可复用 PHP/npm 包、脚手架模板和参考 Admin Shell；
- 开发文档站、安装、升级、备份恢复和安全测试。

P0 不是 LikeAdmin 级成熟公开版。手机号、邀请、文件、任务管理、消息、代码生成、插件市场等在 P1/P2 完成。

## 9. 批准后怎样执行

1. 在 `/Users/xing/Documents/company-os/repositories/peanut-admin/` 使用独立干净 Git 仓库。
2. 创建新的 Git 历史，不继承旧 base-framework 问题历史。
3. 按 P0-A01 至 P0-D05 串行执行 24 个受控任务。
4. 每个任务只修改白名单文件，先写失败测试，验证后独立提交。
5. 同一时间只运行一个写任务；可以并行做固定 commit 的只读复审。
6. P0 完成后再做 Runtime 九角色复审，不自动发布 package 或 release。

## 10. 还没有做的事情

- 真实代码目录和干净 Git 历史已经创建。
- GitHub 公开仓库已经创建，默认分支为 `dev`。
- P0-A01 只建立治理和许可证基线，没有创建 backend/frontend/packages/templates/examples。
- 运行时代码、数据库、API 和页面尚未实现。
- Composer/npm package 尚未发布。
- DCS 尚未迁移到 Peanut Admin；Finance Manager 已明确继续使用 LikeAdmin PHP，不列入迁移计划。

当前完成的是详细设计、任务卡、复审和 P0-A01 仓库基线，还不是可运行的软件。

## 11. 编码批准语

用户已于 2026-07-15 明确回复：

```text
批准按 48 号复审结论开始 P0-A 运行时代码；Peanut Admin 顶层许可证采用 Apache-2.0。
```

该批准已经生效。后续仍必须按 45 号从 P0-A02 起串行执行，不得并行写任务或跳过白名单。
