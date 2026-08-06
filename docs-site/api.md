---
title: API 约定
description: Peanut Admin API 的公开响应、认证与权限约定。
---

# API 约定

## 响应格式

所有接口通过统一 JSON 响应服务返回：

```json
{
  "code": 20000,
  "msg": "success",
  "data": {}
}
```

列表接口的 `data` 使用 `lists`、`count`、`pageNo`、`pageSize` 字段；详情和配置接口返回 `data` 对象。业务错误为 `40000`，未登录为 `40100`，无权限为 `40300`。

## 路由前缀

- `api/admin/*`：管理端接口，统一经过登录、权限和操作日志中间件。
- `api/*`：会员端公开接口和需要会员令牌的接口。
- `api/payment/notify/*`、`api/wechat/official-account/callback`：第三方回调入口，进入后仍需按业务规则验签。

登录路由不挂鉴权。管理端前端和其他客户端默认以 `/api` 作为后端前缀。

## 认证

管理端请求使用：

```http
Authorization: Bearer <token>
```

管理端令牌和会员端令牌不能混用。会话会检查过期时间、登录 IP 和账号状态；账号被禁用或编辑后，服务端会使相关会话失效。

## 权限标识

管理端权限标识由请求路径去掉 `api/admin/` 得到。例如：

```text
api/admin/menu/lists -> menu/lists
```

新增管理接口时，应同时登记路由、菜单和按钮/API 权限，并确认角色获得最小必要授权。权限不足时接口返回 `40300`，前端会隐藏没有权限的按钮。

## 敏感字段

操作日志会对 `password`、`token`、`secret`、证书和私钥等敏感字段脱敏。不要在请求、备注、日志导出或工单中提交真实密钥。
