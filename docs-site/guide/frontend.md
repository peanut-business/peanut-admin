---
title: 前端开发
description: Admin、Platform、PC 与 UniApp 的边界和聚焦命令。
---

# 前端开发

## 前置条件

后端 DTO、权限和 Tenant 行为已明确。前端隐藏不能替代后端授权。

## 选择客户端

| 客户端 | 目录 | 类型检查 / 构建 |
| --- | --- | --- |
| Admin | `web/` | `pnpm type:check`, `pnpm build` |
| Platform | `platform/` | `npm run type:check`, `npm run build` |
| PC | `pc/` | `npm run typecheck`, `npm run generate` |
| H5 / UniApp | `uniapp/` | `npm run type-check`, `npm run build:h5` |

命令在对应目录执行。只构建被修改的客户端；共享 DTO 变化才扩展到直接消费者。

## 步骤

1. 使用既有 API client 和 DTO，不在页面重新发明权限或 Tenant 规则。
2. Admin 功能按 Module contribution 和路由约定接入；平台能力只进入 `platform/`。
3. 保持 loading、empty、error、disabled 和窄屏状态可理解。
4. 更新受影响任务页或 API 示例，不复制后端清单。

## 验证

执行上表中受影响客户端的一次类型检查和一次构建；交互变化再运行其现有聚焦浏览器或合同检查。

## 下一步

交付前阅读[测试与排错](/guide/testing)。
