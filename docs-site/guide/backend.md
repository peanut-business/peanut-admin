---
title: 后端开发
description: ThinkPHP 后端的路由、服务、数据、命令与验证路径。
---

# 后端开发

## 前置条件

已确认数据 owner、Tenant 边界和目标 Module。HTTP 变更还要确认 `docs/api/openapi.yaml` 是否覆盖该合同。

## 步骤

1. 在 `server/route/` 的对应文件定位入口，在所属 Module 或 Host controller 处理传输层。
2. 把业务编排放到 owner 的 service/application 层；不要让 controller 跨 Module 写表。
3. 数据变更按当前基线规则进入 Schema 或增量 migration，并同步数据 owner 合同。
4. 新命令放在现有 command 装配点，保持 `--help` 是参数事实源。
5. HTTP、命令、配置或 Schema 改动按 docs-impact 更新最小文档。

错误响应、授权和 Tenant 缺失应 fail closed。不要用默认 Tenant、系统 actor 或测试开关吞掉缺失上下文。

## 验证

运行仓库规则指定的 PHP 静态检查与受影响功能聚焦测试。HTTP 合同变化至少运行：

```bash
./scripts/check-openapi
```

文档或示例变化同时运行 `./scripts/docs-governance check`。

## 下一步

需要 UI 时转到[前端开发](/guide/frontend)；新增独立能力时转到[Module 开发](/guide/module-development)。
