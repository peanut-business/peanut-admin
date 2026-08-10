---
title: API 与扩展
description: Peanut Admin API、公开包与稳定 Host 覆盖约定。
---

# API 与扩展

Peanut Admin 的 HTTP API、两个公开运行包和 Host 覆盖共同构成扩展面。内部领域目录不是可单独安装的包；应用扩展不能通过修改依赖目录或复制核心源码完成。

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

## 两个公开运行包

| 生态 | 包与入口 | 使用范围 |
|---|---|---|
| Composer | `peanut-admin/core` | 已采用的认证、权限等产品无关 PHP 契约和原语 |
| npm | `@peanut-admin/admin` | 管理端公共入口，以及 `./client`、`./client/nuxt`、`./client/uniapp` 无 UI 客户端入口 |

应用只通过 registry 与公开入口安装这两个包。核心仓的 Settings、File、Task、Notification 等目录是包内模块，不是可独立安装的 package；会员/财务、内容/装修、支付/OAuth/渠道继续由应用 Module 拥有。

## Host 与覆盖

- PHP 覆盖通过公开 interface、应用实现和显式 Provider 装配；启动时校验类型、重复 key 与版本约束。
- Web 覆盖通过稳定 key 和 `peanut.overrides.ts` 注册；Vite alias 不能代替业务覆盖协议。
- PC 与 UniApp 从同一无 UI client 注入 transport、token storage、导航和反馈适配器；页面与平台 SDK 留在对应端。
- 禁止修改 `vendor/`、`node_modules/`，禁止复制核心类或增加双路由、双字段、双实现兼容层。

新增覆盖点属于公共 API，需要明确 owner、版本约束、默认实现、错误边界和最小测试；没有第二消费者和稳定发布节奏时，不拆第三个公开包。

## 外部回调停止线

支付、公众号和 OAuth 回调必须先验签/校验 state，再进入产品状态机。仓库测试只能证明签名、幂等和回跳合同；真实商户、微信平台域名/白名单、证书轮换和资金到账必须在部署环境完成低风险 smoke 后才能宣称可用。
